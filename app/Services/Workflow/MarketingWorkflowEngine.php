<?php

namespace App\Services\Workflow;

use App\Contracts\MarketingMailer;
use App\Contracts\MarketingStorage;
use Illuminate\Support\Str;

class MarketingWorkflowEngine
{
    public function __construct(
        private readonly MarketingStorage $storage,
        private readonly MarketingMailer $mailer
    ) {}

    public function run(int $windowMinutes, int $maxAttempts): void
    {
        $cutoff = now()->subMinutes($windowMinutes);

        $contacts = $this->storage->listContacts();

        foreach ($contacts as $c) {
            $activity = $this->storage->getLatestActivity($c->contact_id);

            $lastSendTime = $activity?->last_messaging_date;
            $hasComplied = $this->storage->hasComplied(
                $c->contact_id,
                $activity?->tracking_id ?? null,
                $lastSendTime
            );

            if ($hasComplied) {
                // stop loop for this contact
                continue;
            }

            if (! $activity) {
                $this->sendStep($c->contact_id, $c->personal_email, $c->first_name, 1, 'WELCOME_EMAIL');

                continue;
            }

            if ((int) $activity->attempts >= $maxAttempts) {
                continue;
            }

            if ($activity->last_messaging_date && $activity->last_messaging_date > $cutoff) {
                continue;
            }

            $nextAttempt = ((int) $activity->attempts) + 1;
            $this->sendStep($c->contact_id, $c->personal_email, $c->first_name, $nextAttempt, 'FOLLOWUP_EMAIL');
        }
    }

    private function sendStep(string $contactId, string $toEmail, ?string $firstName, int $attempt, string $stepKey): void
    {
        $trackingId = (string) Str::uuid();

        $subject = "POC Follow-up | Attempt {$attempt} | ID: {$trackingId}";
        $ctaUrl = "https://example.com/?tid={$trackingId}";

        $html = view('emails.welcome', [
            'firstName' => $firstName,
            'trackingId' => $trackingId,
            'stepKey' => $stepKey,
            'ctaUrl' => $ctaUrl,
        ])->render();

        $result = $this->mailer->send([
            'to' => $toEmail,
            'subject' => $subject,
            'html' => $html,
            'trackingId' => $trackingId,
        ]);

        // record activity state
        $this->storage->insertActivity([
            'activity_id' => (string) Str::uuid(),
            'contact_id' => $contactId,
            'tracking_id' => $trackingId,
            'activity_type' => 'EMAIL_SENT',
            'activity_channel' => 'EMAIL',
            'last_messaging_contents' => $stepKey,
            'last_messaging_date' => now(),
            'attempts' => $attempt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // (optional) you can also log $result if you want, but keep engine simple for now
    }

    public function markComplied(string $email): bool
    {
        $contact = $this->storage->findContactByEmail($email);
        if (! $contact) {
            return false;
        }

        $latestActivity = $this->storage->getLatestActivity($contact->contact_id);
        $trackingId = $latestActivity?->tracking_id;

        $this->storage->insertEngagement([
            'engagement_id' => (string) Str::uuid(),
            'contact_id' => $contact->contact_id,
            'engagement_type' => 'COMPLIED',
            'engagement_status' => 'YES',
            'engagement_channel' => 'EMAIL',
            'tracking_id' => $trackingId,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->storage->updateContactOnComply($contact->contact_id, [
            'lead_status' => env('COMPLIED_LEAD_STATUS', 'Engaged'),
            'lifecycle_stage' => env('COMPLIED_LIFECYCLE_STAGE', $contact->lifecycle_stage),
            'updated_at' => now(),
        ]);

        return true;
    }
}

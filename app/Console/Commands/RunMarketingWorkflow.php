<?php

namespace App\Console\Commands;

use App\Contracts\MarketingMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RunMarketingWorkflow extends Command
{
    protected $signature = 'marketing:run {--minutes=5 : Demo wait window in minutes} {--maxAttempts=3}';

    protected $description = 'Runs the demo marketing workflow: send -> wait -> resend unless complied';

    public function handle(MarketingMailer $mailer): int
    {
        $windowMinutes = (int) $this->option('minutes');
        $maxAttempts = (int) $this->option('maxAttempts');

        $now = now();
        $cutoff = $now->copy()->subMinutes($windowMinutes);

        // Pull contacts
        $contacts = DB::table('contacts')->get();

        foreach ($contacts as $c) {
            // Find latest activity for this contact
            $activity = DB::table('contact_activities')
                ->where('contact_id', $c->contact_id)
                ->orderByDesc('last_messaging_date')
                ->first();

            // Has complied? (any YES engagement after last send)
            $lastSendTime = $activity?->last_messaging_date;
            $hasComplied = DB::table('contact_engagements')
                ->where('contact_id', $c->contact_id)
                ->where('engagement_status', 'YES')
                ->when($activity?->tracking_id, fn ($q) => $q->where('tracking_id', $activity->tracking_id)) // If we have a tracking_id, only consider engagements for that ID. If not, consider all engagements but only after the last send time (for backward compatibility)
                ->when(! $activity?->tracking_id && $lastSendTime, fn ($q) => $q->where('occurred_at', '>=', $lastSendTime)) // Only consider engagements after the last send if we don't have a tracking_id (for backward compatibility)
                ->exists();

            if ($hasComplied) {
                $this->line("{$c->personal_email} already complied. Skipping.");

                continue;
            }

            // If no activity yet, send first email
            if (! $activity) {
                $this->sendStep($mailer, $c->contact_id, $c->personal_email, $c->first_name, 1, 'WELCOME_EMAIL');

                continue;
            }

            // If attempts exceeded, stop
            if ((int) $activity->attempts >= $maxAttempts) {
                $this->line("{$c->personal_email} reached max attempts ({$activity->attempts}).");

                continue;
            }

            // If not yet due (still within window), skip
            if ($activity->last_messaging_date && $activity->last_messaging_date > $cutoff) {
                $this->line("{$c->personal_email} not due yet (last send: {$activity->last_messaging_date}).");

                continue;
            }

            // Due and not complied -> resend
            $nextAttempt = ((int) $activity->attempts) + 1;
            $this->sendStep($mailer, $c->contact_id, $c->personal_email, $c->first_name, $nextAttempt, 'FOLLOWUP_EMAIL');
        }

        $this->info("Done. Check storage/logs/laravel.log for log-mode 'emails'.");

        return self::SUCCESS;
    }

    private function sendStep(
        MarketingMailer $mailer,
        string $contactId,
        string $toEmail,
        ?string $firstName,
        int $attempt,
        string $stepKey
    ): void {
        $trackingId = (string) Str::uuid();

        $subject = "POC Follow-up | Attempt {$attempt} | ID: {$trackingId}";
        $ctaUrl = "https://example.com/?tid={$trackingId}";

        $html = view('emails.welcome', [
            'firstName' => $firstName,
            'trackingId' => $trackingId,
            'stepKey' => $stepKey,
            'ctaUrl' => $ctaUrl,
        ])->render();

        $result = $mailer->send([
            'to' => $toEmail,
            'subject' => $subject,
            'html' => $html,
            'trackingId' => $trackingId,
        ]);

        // Persist activity so we can “resume” later without a workflow_runs table
        DB::table('contact_activities')->insert([
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

        $this->line("📨 SENT({$result['provider']}) to {$toEmail} attempt={$attempt} step={$stepKey}");
    }
}

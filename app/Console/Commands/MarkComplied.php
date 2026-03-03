<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarkComplied extends Command
{
    protected $signature = 'marketing:complied {email : Contact email to mark as complied}';

    protected $description = 'Marks a contact as complied by inserting an engagement record';

    public function handle(): int
    {
        $email = $this->argument('email');

        $contact = DB::table('contacts')->where('personal_email', $email)->first();
        if (! $contact) {
            $this->error("No contact found with email: {$email}");

            return self::FAILURE;
        }

        $latestActivity = DB::table('contact_activities')
            ->where('contact_id', $contact->contact_id)
            ->orderByDesc('last_messaging_date')
            ->first();

        $trackingId = $latestActivity?->tracking_id; // can be null if no activity yet

        $alreadyComplied = DB::table('contact_engagements')
            ->where('contact_id', $contact->contact_id)
            ->where('engagement_type', 'COMPLIED')
            ->where('engagement_status', 'YES')
            ->when($trackingId, fn ($q) => $q->where('tracking_id', $trackingId))
            ->exists();

        if ($alreadyComplied) {
            $this->warn('Already complied for this contact'.($trackingId ? " (tracking_id={$trackingId})" : '').'. No new engagement inserted.');

            return self::SUCCESS;
        }

        DB::table('contact_engagements')->insert([
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

        DB::table('contacts')->where('contact_id', $contact->contact_id)->update([
            'lead_status' => env('COMPLIED_LEAD_STATUS', 'Engaged'),
            'lifecycle_stage' => env('COMPLIED_LIFECYCLE_STAGE', $contact->lifecycle_stage),
            'updated_at' => now(),
        ]);

        $this->line('');
        $this->info('COMPLIED COMMAND');
        $this->line("Input email: {$email}");
        $this->line("Contact found: contact_id={$contact->contact_id}");

        if ($latestActivity) {
            $this->line(
                'Latest activity: tracking_id='.($latestActivity->tracking_id ?? 'null').
                ' last_sent='.($latestActivity->last_messaging_date ?? 'null').
                ' attempts='.($latestActivity->attempts ?? '0').
                ' step='.($latestActivity->last_messaging_contents ?? 'null')
            );
        } else {
            $this->line('Latest activity: none (no previous sends recorded)');
        }

        $this->line('Engagement inserted: type=COMPLIED status=YES tracking_id='.($trackingId ?? 'null'));
        $this->line(
            'Contact updated: lead_status='.env('COMPLIED_LEAD_STATUS', 'Engaged').
            ' lifecycle_stage='.env('COMPLIED_LIFECYCLE_STAGE', $contact->lifecycle_stage)
        );

        $this->line('');
        $this->line('Next: run `php artisan marketing:run --minutes=1 --maxAttempts=3` to confirm it is skipped.');
        $this->line('');

        return self::SUCCESS;
    }
}

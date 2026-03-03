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

        $this->info("Marked complied for {$email}");

        return self::SUCCESS;
    }
}

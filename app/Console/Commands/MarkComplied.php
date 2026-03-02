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

        DB::table('contact_engagements')->insert([
            'engagement_id' => (string) Str::uuid(),
            'contact_id' => $contact->contact_id,
            'engagement_type' => 'COMPLIED',
            'engagement_status' => 'YES',
            'engagement_channel' => 'EMAIL',
            'tracking_id' => null,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("Marked complied for {$email}");

        return self::SUCCESS;
    }
}

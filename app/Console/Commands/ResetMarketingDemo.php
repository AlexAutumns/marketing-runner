<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class ResetMarketingDemo extends Command
{
    // Optional: --reseed will wipe/reseed contacts too
    protected $signature = 'marketing:reset {--reseed : Also reset demo contacts back to seeded defaults}';

    protected $description = '[DEMO ONLY] Resets demo workflow state (activities + engagements).';

    public function handle(): int
    {
        // Safety: only allow in local/dev environments
        if (! App::environment(['local', 'development', 'testing'])) {
            $this->error('This command is DEMO-ONLY and can only run in local/dev/testing environments.');

            return self::FAILURE;
        }

        DB::transaction(function () {
            // Always reset workflow state tables
            DB::table('contact_engagements')->delete();
            DB::table('contact_activities')->delete();

            if ($this->option('reseed')) {
                DB::table('contacts')->delete();
            }
        });

        if ($this->option('reseed')) {
            // Re-seed contacts to keep demo emails stable
            $this->call('db:seed', ['--class' => 'DemoContactsSeeder']);
            $this->info('Reset activities/engagements AND reseeded demo contacts.');
        } else {
            $this->info('Reset activities/engagements. Contacts kept as-is.');
        }

        $this->line('Tip: run `php artisan marketing:run --minutes=1` to generate new sends.');

        return self::SUCCESS;
    }
}

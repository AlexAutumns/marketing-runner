<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetMarketingDemo extends Command
{
    // Optional: --reseed will wipe/reseed contacts too
    protected $signature = 'marketing:reset
    {--reseed : Also reset demo contacts back to seeded defaults}
    {--force : Skip confirmation prompt (for scripts)}';

    protected $description = '[DEMO ONLY] Resets demo workflow state (activities + engagements).';

    // Keeping old handle method here for reference until we verify new implementation works as expected.
    // public function handle(): int
    // {
    //     // Safety: only allow in local/dev environments
    //     if (! App::environment(['local', 'development', 'testing'])) {
    //         $this->error('This command is DEMO-ONLY and can only run in local/dev/testing environments.');

    //         return self::FAILURE;
    //     }

    //     DB::transaction(function () {
    //         // Always reset workflow state tables
    //         DB::table('contact_engagements')->delete();
    //         DB::table('contact_activities')->delete();

    //         if ($this->option('reseed')) {
    //             DB::table('contacts')->delete();
    //         }
    //     });

    //     if ($this->option('reseed')) {
    //         // Re-seed contacts to keep demo emails stable
    //         $this->call('db:seed', ['--class' => 'DemoContactsSeeder']);
    //         $this->info('Reset activities/engagements AND reseeded demo contacts.');
    //     } else {
    //         $this->info('Reset activities/engagements. Contacts kept as-is.');
    //     }

    //     $this->line('Tip: run `php artisan marketing:run --minutes=1` to generate new sends.');

    //     return self::SUCCESS;
    // }

    // New handle method refactored to use MarketingWorkflowEngine service class for better separation of concerns and testability. The command now only handles resetting state and seeding, while the workflow engine handles the actual workflow logic.
    public function handle(): int
    {
        // DEMO safety: never run outside local/dev/testing
        if (! App::environment(['local', 'development', 'testing'])) {
            $this->error('This command is DEMO-ONLY and can only run in local/dev/testing environments.');

            return self::FAILURE;
        }

        // Option B: Make sure tables exist (prevents confusing DB errors)
        if (
            ! Schema::hasTable('contacts') ||
            ! Schema::hasTable('contact_activities') ||
            ! Schema::hasTable('contact_engagements')
        ) {
            $this->error('Demo tables not found. Run migrations first: php artisan migrate');
            $this->line('Expected tables: contacts, contact_activities, contact_engagements');

            return self::FAILURE;
        }

        // Option A: confirmation (skippable with --force)
        if (! $this->option('force')) {
            if (! $this->confirm('This will DELETE demo workflow state (activities + engagements). Continue?')) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        DB::transaction(function () {
            DB::table('contact_engagements')->delete();
            DB::table('contact_activities')->delete();

            if ($this->option('reseed')) {
                DB::table('contacts')->delete();
            }
        });

        if ($this->option('reseed')) {
            $this->call('db:seed', ['--class' => 'DemoContactsSeeder']);
            $this->info('Reset activities/engagements AND reseeded demo contacts.');
        } else {
            $this->info('Reset activities/engagements. Contacts kept as-is.');
        }

        $this->line('Next: run `php artisan marketing:run --minutes=1` to generate new sends.');

        return self::SUCCESS;
    }
}

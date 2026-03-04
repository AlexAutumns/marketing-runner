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

    // Note: this command is intentionally simple/straightforward to avoid confusion and potential mistakes.
    public function handle(): int
    {
        // DEMO safety: never run outside local/dev/testing
        if (! App::environment(['local', 'development', 'testing'])) {
            $this->error('This command is DEMO-ONLY and can only run in local/dev/testing environments.');

            return self::FAILURE;
        }

        // DEMO safety: check for expected tables before running
        if (
            ! Schema::hasTable('contacts') ||
            ! Schema::hasTable('contact_activities') ||
            ! Schema::hasTable('contact_engagements')
        ) {
            $this->error('Demo tables not found. Run migrations first: php artisan migrate');
            $this->line('Expected tables: contacts, contact_activities, contact_engagements');

            return self::FAILURE;
        }

        // Confirmation prompt (unless --force)
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

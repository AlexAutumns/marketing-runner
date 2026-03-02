<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoContactsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = [
                'contact_id' => (string) Str::uuid(),
                'personal_email' => "test{$i}@example.com",
                'first_name' => "Test{$i}",
                'lifecycle_stage' => 'Interest',
                'lead_status' => 'In progress',
                'cilos_substage_id' => 'N1',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('contacts')->insert($rows);
    }
}

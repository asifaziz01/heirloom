<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The demo families sign in with the password "password". Never load them into a
        // production database by accident (e.g. `migrate --seed --force` in a deploy script).
        if (app()->isProduction() && ! filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('Skipping the demo family: APP_ENV is production. Set SEED_DEMO_DATA=true to load it anyway.');

            return;
        }

        $this->call([DemoFamilySeeder::class, SampleDataSeeder::class]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production databases are seeded with nothing. Local environments get the labelled demo estate.
     */
    public function run(): void
    {
        if (app()->environment(['local', 'development', 'testing'])) {
            $this->call(DemoSeeder::class);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ResponsibleUserSeeder::class,
            DemoUsersSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
        ]);

        if (config('app.performance_testing')) {
            $this->call(PerformanceSeeder::class);
        }
    }
}

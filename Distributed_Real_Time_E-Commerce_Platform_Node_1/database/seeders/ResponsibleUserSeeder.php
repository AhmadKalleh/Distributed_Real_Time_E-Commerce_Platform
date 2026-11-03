<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ResponsibleUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SEED_RESPONSIBLE_EMAIL', 'mohahmk03@gmail.com');

        DB::table('users')->updateOrInsert(
            ['email' => $email],
            [
                'name' => env('SEED_RESPONSIBLE_NAME', 'System Responsible'),
                'password' => Hash::make(env('SEED_RESPONSIBLE_PASSWORD', 'password')),
                'role' => 'Responsible',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

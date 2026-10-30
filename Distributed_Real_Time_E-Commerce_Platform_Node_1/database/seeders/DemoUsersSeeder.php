<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $password = Hash::make('password');

        $users = [
            ['name' => 'Demo Customer One', 'email' => 'ahmadhkalleh@gmail.com'],
            ['name' => 'Demo Customer Two', 'email' => 'customer2@example.test'],
            ['name' => 'Demo Customer Three', 'email' => 'customer3@example.test'],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => $password,
                    'role' => 'Customer',
                    'email_verified_at' => now(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}

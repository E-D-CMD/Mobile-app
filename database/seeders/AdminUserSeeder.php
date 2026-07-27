<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Predictable admin login for testing the Admin Dashboard. See
        // OrderSeeder for why the plaintext password below is correct
        // (User::casts() hashes 'password' automatically on save).
        User::firstOrCreate(
            ['email' => 'admin@perfumehub.test'],
            [
                'name' => 'PerfumeHub Admin',
                'password' => 'password',
                'role' => 'admin',
            ]
        );
    }
}

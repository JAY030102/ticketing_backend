<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'fo11dswdticketingsystem@gmail.com'],
            [
                'first_name' => 'Super',
                'middle_name' => null,
                'last_name' => 'Admin',
                'username' => 'superadmin',
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
                'status' => 'approved',
                'email_verified_at' => now(),
                'approved_at' => now(),
                'approved_by' => null,
            ]
        );
    }
}

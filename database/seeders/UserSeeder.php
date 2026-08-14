<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::create([
            'uuid'               => Str::uuid(),
            'full_name'          => 'Admin',
            'email'              => 'admin@skincek.com',
            'password'           => Hash::make('password123'),
            'privacy_consent_at' => now(),
            'is_active'          => true,
        ]);
        $admin->assignRole('admin');

        $doctor = User::create([
            'uuid'               => Str::uuid(),
            'full_name'          => 'dr. Example',
            'email'              => 'doctor@skincek.com',
            'password'           => Hash::make('password123'),
            'privacy_consent_at' => now(),
            'is_active'          => true,
        ]);
        $doctor->assignRole('doctor');

        $user = User::create([
            'uuid'               => Str::uuid(),
            'full_name'          => 'User',
            'email'              => 'user@skincek.com',
            'password'           => Hash::make('password123'),
            'privacy_consent_at' => now(),
            'is_active'          => true,
        ]);
        $user->assignRole('user');
    }
}

<?php

namespace Database\Seeders;

use App\Enums\SubscriptionStatus;
use App\Enums\VerificationStatus;
use App\Models\DoctorVerification;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::create([
            'uuid' => Str::uuid(),
            'full_name' => 'Admin',
            'email' => 'admin@skincek.com',
            'password' => Hash::make('password123'),
            'privacy_consent_at' => now(),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        $doctor = User::create([
            'uuid' => Str::uuid(),
            'full_name' => 'dr. Example',
            'email' => 'doctor@skincek.com',
            'password' => Hash::make('password123'),
            'privacy_consent_at' => now(),
            'is_active' => true,
        ]);
        $doctor->assignRole('doctor');
        DoctorVerification::firstOrCreate(
            ['doctor_id' => $doctor->id],
            [
                'specialization' => 'Dermatologi Klinik',
                'verification_status' => VerificationStatus::APPROVED,
            ]
        );

        $user = User::create([
            'uuid' => Str::uuid(),
            'full_name' => 'User',
            'email' => 'user@skincek.com',
            'password' => Hash::make('password123'),
            'privacy_consent_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole('user');

        if (app()->environment('local', 'staging')) {
            $dev = User::create([
                'uuid' => Str::uuid(),
                'full_name' => 'Adam Raga',
                'email' => 'adamxraga@gmail.com',
                'password' => Hash::make('developer123'),
                'email_verified_at' => now(),
                'date_of_birth' => '2006-01-12',
                'gender' => 'laki_laki',
                'privacy_consent_at' => now(),
                'is_active' => true,
            ]);
            $dev->assignRole('user');

            Subscription::firstOrCreate(
                ['user_id' => $dev->id, 'plan_code' => 'pro_lifetime'],
                [
                    'uuid' => Str::uuid(),
                    'period' => 'lifetime',
                    'status' => SubscriptionStatus::ACTIVE,
                    'amount' => 0,
                    'currency' => 'IDR',
                    'starts_at' => now(),
                ]
            );
        }

        $aura = User::firstOrCreate(
            ['email' => config('ai.bot_email', 'aura@skincek.com')],
            [
                'uuid' => Str::uuid(),
                'full_name' => 'Aura Skin',
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'privacy_consent_at' => now(),
                'is_active' => true,
                'ai_bot' => true,
            ]
        );
        $aura->assignRole('doctor');
        DoctorVerification::firstOrCreate(
            ['doctor_id' => $aura->id],
            [
                'specialization' => 'Asisten Kecerdasan Buatan SkinCek',
                'verification_status' => VerificationStatus::APPROVED,
            ]
        );
    }
}

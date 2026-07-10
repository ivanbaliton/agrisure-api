<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Barangay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | MAO Accounts
        |--------------------------------------------------------------------------
        */

        $maoAccounts = [
            ['MAO', 'One', 'christopherlancefrias@gmail.com', '09170000001'],
            ['MAO', 'Two', 'balitonivan0@gmail.com', '09170000002'],
            ['MAO', 'Three', 'mao3@agrisure.com', '09170000003'],
            ['MAO', 'Four', 'mao4@agrisure.com', '09170000004'],
        ];

        foreach ($maoAccounts as [$firstName, $lastName, $email, $phone]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'middle_name' => null,
                    'last_name' => $lastName,
                    'extension_name' => null,
                    'sex' => 'Male',
                    'phone_number' => $phone,
                    'password' => Hash::make('mao12345'),
                    'role' => User::ROLE_MAO,
                    'barangay_id' => null,
                    'account_status' => User::STATUS_VERIFIED,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Barangay Accounts
        |--------------------------------------------------------------------------
        */

        $barangayAccounts = [
            ['Camarag', 'christopherlance.j.frias@isu.edu.ph', '09280000001'],
            ['Rizal', 'rizal@agrisure.com', '09280000002'],
            ['Salinungan', 'salinungan@agrisure.com', '09280000003'],
            ['Villaflor', 'villaflor@agrisure.com', '09280000004'],
            ['Masaya', 'masaya@agrisure.com', '09280000005'],
        ];

        foreach ($barangayAccounts as [$barangayName, $email, $phone]) {
            $barangay = Barangay::updateOrCreate(
                ['name' => $barangayName],
                ['name' => $barangayName]
            );

            User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $barangayName,
                    'middle_name' => null,
                    'last_name' => 'Barangay',
                    'extension_name' => null,
                    'sex' => 'Male',
                    'phone_number' => $phone,
                    'password' => Hash::make('barangay123'),
                    'role' => User::ROLE_BARANGAY,
                    'barangay_id' => $barangay->id,
                    'account_status' => User::STATUS_VERIFIED,
                ]
            );
        }
    }
}
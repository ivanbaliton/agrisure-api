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
        | Each MAO account has its own unique credentials.
        */

        $maoAccounts = [
            [
                'first_name' => 'MAO',
                'last_name' => 'One',
                'email' => 'christopherlancefrias@gmail.com',
                'phone' => '09170000001',
                'password' => 'mao_one123',
            ],
            [
                'first_name' => 'MAO',
                'last_name' => 'Two',
                'email' => 'balitonivan0@gmail.com',
                'phone' => '09170000002',
                'password' => 'mao_two123',
            ],
            [
                'first_name' => 'MAO',
                'last_name' => 'Three',
                'email' => 'ivanruel.o.baliton@isu.edu.ph',
                'phone' => '09170000003',
                'password' => 'mao_three123',
            ],
            [
                'first_name' => 'MAO',
                'last_name' => 'Four',
                'email' => 'mao4@agrisure.com',
                'phone' => '09170000004',
                'password' => 'mao_four123',
            ],
        ];

        foreach ($maoAccounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'first_name' => $account['first_name'],
                    'middle_name' => null,
                    'last_name' => $account['last_name'],
                    'extension_name' => null,
                    'sex' => 'Male',
                    'phone_number' => $account['phone'],
                    'password' => Hash::make($account['password']),
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
        | Each Barangay account has its own unique credentials
        | and is connected to exactly one Barangay.
        */

        $barangayAccounts = [
            [
                'barangay' => 'Sto Niño',
                'email' => 'christopherlance.j.frias@isu.edu.ph',
                'phone' => '09280000001',
                'password' => 'stonino123',
            ],
            [
                'barangay' => 'Masaya Sur',
                'email' => 'rizal@agrisure.com',
                'phone' => '09280000002',
                'password' => 'masayasur123',
            ],
            [
                'barangay' => 'Mapalad',
                'email' => 'mapalad@agrisure.com',
                'phone' => '09280000003',
                'password' => 'mapalad123',
            ],
            [
                'barangay' => 'Dabubu Pequeño',
                'email' => 'Dabubupequeño@agrisure.com',
                'phone' => '09280000004',
                'password' => 'Dabubu123',
            ],
            [
                'barangay' => 'Dabubu Grande',
                'email' => 'dabubugrande@agrisure.com',
                'phone' => '09280000005',
                'password' => 'dabubugrande123',
            ],
        ];

        foreach ($barangayAccounts as $account) {
            /*
             * Create or retrieve exactly one Barangay record.
             */
            $barangay = Barangay::updateOrCreate(
                ['name' => $account['barangay']],
                ['name' => $account['barangay']]
            );

            /*
             * Create or update exactly one User account
             * for this Barangay.
             */
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'first_name' => $account['barangay'],
                    'middle_name' => null,
                    'last_name' => 'Barangay',
                    'extension_name' => null,
                    'sex' => 'Male',
                    'phone_number' => $account['phone'],
                    'password' => Hash::make($account['password']),
                    'role' => User::ROLE_BARANGAY,
                    'barangay_id' => $barangay->id,
                    'account_status' => User::STATUS_VERIFIED,
                ]
            );
        }
    }
}
<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

use Illuminate\Support\Facades\Storage;

class fakeUserShipments extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $name =  'ShipCash Guest';
        $email = 'guest@shipcash.net';

        $merchant = [
            'name' => $name,
            'email' => $email,
            'phone' => '1234567890',
            'country_code' => 'JO',
            'currency_code' => 'JOD',
            'domestic_rates' => collect(json_decode(Storage::disk('local')->get('template/domestic_rates.json'), true)),
            'express_rates' => collect(json_decode(Storage::disk('local')->get('template/express_rates.json'), true)),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];

        $user = [
            'merchant_id' => 1,
            'name' => $name,
            'email' => $email,
            'phone' => null,
            'role' => 'admin',
            'is_owner' => true,
            'password' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];
        DB::table('merchants')->insert($merchant);
        DB::table('users')->insert($user);


        $user = [
            'merchant_id' => null,
            'name' => 'Super Admin',
            'email' => 'admin@shipcash.net',
            'phone' => null,
            'role' => 'super_admin',
            'is_owner' => true,
            'password' => Hash::make('super_admin'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];
        DB::table('users')->insert($user);

        $carriersArr = ['aramex', 'dhl', 'fedex'];
        for ($i = 0; $i < 3; $i++) {
            $carriers = [
                'name' => $carriersArr[$i],
                'email' => 'info@' . $carriersArr[$i] . '.com',
                'phone' => Str::random(10),
                'balance' => 0,
                'country_code' => 'JO',
                'currency_code' => 'JOD',
                'is_email_verified' => 1,
                'is_phone_verified' => 1,
                'is_documents_verified' => 1,
                'is_active' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
            DB::table('carriers')->insert($carriers);
        }
    }
}

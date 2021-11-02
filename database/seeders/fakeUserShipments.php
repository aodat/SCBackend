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
        $name =  'Tareq Fawakhiri';// Str::random(10);
        $email = 'tareq.fw@shipcash.net'; // Str::random(10).'@gmail.com';
        $phone = '0772170353';// Str::random(10);

        $merchant = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'domestic_rates' => collect(json_decode(Storage::disk('local')->get('template/domestic_rates.json'),true)),
            'express_rates' => collect(json_decode(Storage::disk('local')->get('template/express_rates.json'),true)),
            'created_at' => Carbon::now(),
            'updated_at'=> Carbon::now()
        ];

        $user = [
            'merchant_id' => 1,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make('123456789'),
            'created_at' => Carbon::now(),
            'updated_at'=> Carbon::now()
        ];
        DB::table('merchants')->insert($merchant);
        DB::table('users')->insert($user);
        
        $carriersArr = ['aramex','dhl','stripe','fedex'];
        for($i=0;$i<4;$i++){
            $carriers = [
                'name' => $carriersArr[$i],
                'email' => Str::random(10).'@gmail.com',
                'phone' => Str::random(10),
                'balance' => 1000,
                'country_code' => 'JO',
                'currency_code' => 'JOD',
                'is_email_verified' => 1,
                'is_phone_verified' => 1,
                'is_documents_verified' => 1,
                'is_active' => 1,
                'created_at' => Carbon::now(),
                'updated_at'=> Carbon::now()
            ];
            DB::table('carriers')->insert($carriers);
        }
        $status = ['DRAFT','PROCESSING','COMPLETED'];
        for($i=1;$i<15;$i++){
            $shipments = [
                'internal_awb' => randomNumber(),

                'sender_name' => Str::random(10),
                'sender_email'=> Str::random(10).'@gmail.com',
                'sender_phone'=> Str::random(10),
                'sender_country'=> 'JO',
                'sender_city'=> 'Amman',
                'sender_area'=> 'Amman',
                'sender_address_description'=> 'sender address description',


                'consignee_name' => Str::random(10),
                'consignee_email'=> Str::random(10).'@gmail.com',
                'consignee_phone'=> Str::random(10),
                'consignee_country'=> 'JO',
                'consignee_city'=> 'Amman',
                'consignee_area'=> 'Amman',
                'consignee_address_description'=> 'consignee address description',

                'pieces' => rand(1,5),
                'content' => Str::random(25),
                'actual_weight' => 1,
                'content' => Str::random(25),
                'cod' => 10.23,
                'group' => 'DOM',
                'status' => Arr::random($status, 1)[0],                
                'created_by' => 1,
                'merchant_id' => 1,
                'carrier_id' => 1,
                'created_at' => Carbon::now(),
                'updated_at'=> Carbon::now()
            ];
            DB::table('shipments')->insert($shipments);
        }

        $type = ['CASHOUT','CASHIN'];
        $status = ['PROCESSING','COMPLETED','REJECTED'];
        for($i=1;$i<15;$i++){
            $transactions = [
                'type' => Arr::random($type, 1)[0],
                'merchant_id' => 1,
                'fk_id' => rand(1,15),
                'amount' => rand(1,2000),
                'balance_after' => rand(1,2000),
                'status' => Arr::random($status, 1)[0],
                'source' => 'order',
                'created_by' => 1,
                'created_at' => Carbon::now(),
                'updated_at'=> Carbon::now()
            ];
            DB::table('transactions')->insert($transactions);
        }
    }
}

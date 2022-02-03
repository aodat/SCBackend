<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\User;
use App\Traits\CarriersManager;
use App\Traits\ResponseHandler;
use App\Traits\SystemRules;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    use ResponseHandler, CarriersManager;
    use SystemRules;

    public function unauthenticated()
    {
        return $this->error('unauthenticated', 403);
    }

    public function migration()
    {
        DB::transaction(function () {
            $lists = collect(json_decode(Storage::disk('local')->get('template/rates/jo.json'), true)['tareqfw']);
            $lists->map(function ($list) use (&$not_found) {
                $merchant = Merchant::where('id', $list['company_id'])->first();
                if ($merchant) {
                    if (!User::where('email', $list['email'])->exists()) {
                        User::create(
                            [
                                'merchant_id' => $list['company_id'],
                                'name' => $list['name'],
                                'email' => $list['email'],
                                'password' => $list['password'],
                                'phone' => $list['phone_number'],
                                'is_owner' => true,
                                'role' => $list['role'] ? $list['role'] : 'member',
                            ]
                        );
                    }

                } else {
                    $not_found[] = $list['company_id'];
                }

            });

            return true;
        });

        return DB::transaction(function () {

            $merchants = DB::table('v1_companies')->select(
                'id',
                'name',
                'phone',
                'papers as documents'
            )->get();

            collect($merchants)->map(function ($data) {
                $phone = $this->phone($data->phone);
                Merchant::create(
                    [
                        'id' => $data->id,
                        'name' => $data->name ?? '--',
                        'email' => $data->name ?? '--',
                        'phone' => ($phone == '') ? null : '+962' . $phone,
                        'country_code' => 'JO',
                        'currency_code' => 'JOD',
                        'documents' => $data->documents,
                    ]
                );
            });

            $users = DB::table('v1_users')->select(
                'id',
                'name',
                'email',
                'password',
                'phone_number',
                'company_id as merchant_id',
                'is_manager as is_owner',
                'role',
                'created_at',
                'updated_at'
            )->get();
            $users->map(function ($user) {
                $mer = Merchant::findOrFail($user->merchant_id);
                // check the email if exists
                $email = $user->email;
                $isExist = User::where('email', $email)->exists();
                if ($isExist) {
                    $exEmail = (explode('@', $email));
                    if (!isset($exEmail[1])) {
                        $email = 'user+' . rand() . '@shipcash.net';
                    } else {
                        $email = $exEmail[0] . '+' . rand() . '@' . $exEmail[1];
                    }

                }
                User::create(
                    [
                        'merchant_id' => $user->merchant_id,
                        'name' => ($user->name == '-') ? $mer->name : $user->name,
                        'email' => $email,
                        'password' => $user->password,
                        'phone' => $mer->phone ?? 'del-' . rand(),
                        'is_owner' => ($user->role == 'admin') ? true : $user->is_owner,
                        'role' => ($user->role == '') ? 'member' : $user->role,
                        'role_member' => ($user->role != 'admin') ? 'shipping,payment' : null,
                    ]
                );
            });

            $merchants = Merchant::whereNotNull('documents')->get();
            $merchants->map(function ($merchant) {
                $counter = 1;
                $data = [];
                $doc = ($merchant->documents);
                foreach ($doc as $id => $value) {
                    $data[] = [
                        'id' => $counter++,
                        'type' => $value['key'],
                        'url' => $value['path'],
                        'status' => 'pending',
                        'verified_at' => null,
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    ];
                }
                $merchant->update(['documents' => $data]);
            });

            $zones = DB::table('v1_zones')->pluck('name_en', 'id');
            $areas = DB::table('v1_areas')->pluck('name_en', 'id');

            $addresss = collect(DB::table('v1_company_addresses')->get())->groupBy('company_id');
            $addresss->map(function ($address, $merchantID) use ($areas, $zones) {
                $comp = DB::table('v1_companies')->where('id', $merchantID)->first();
                $shippers = json_decode($comp->shippers);
                $counter = 1;
                $data = [];
                foreach ($address->toArray() as $key => $value) {
                    $zone = $zones[$value->zone_id];
                    $area = $areas[$value->area_id];
                    $data[] = [
                        'id' => $counter++,
                        'name' => $shippers[$key]->name ?? 'No Title',
                        'country_code' => 'JO',
                        'country' => 'Jordan',
                        'city_code' => $zone,
                        'city' => $zone,
                        'area' => $area,
                        'phone' => $shippers[$key]->phone ?? '',
                        'description' => $value->text,
                        'is_default' => false,
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    ];
                }

                $mer = Merchant::findOrFail($merchantID);
                $mer->addresses = collect($data);
                $mer->save();
            });

            $dom_rates = DB::table('v1_company_zones')->get()->groupBy('company_id');
            $dom_rates->map(function ($data, $merchantID) {
                $json["1"] = [];
                $counter = 1;
                foreach ($data->toArray() as $key => $value) {
                    $json["1"][] = [
                        "id" => $counter++,
                        "code" => $value->name_en,
                        "name_en" => $value->name_en,
                        "name_ar" => $value->name_ar,
                        "price" => floatval($value->price),
                    ];
                }
                $mer = Merchant::findOrFail($merchantID);
                $mer->domestic_rates = collect($json);
                $mer->save();
            });

            Merchant::whereNull('express_rates')->update([
                'express_rates' => collect(json_decode(Storage::disk('local')->get('template/express_rates.json'), true)),
            ]);
            collect(json_decode(Storage::disk('local')->get('template/domestic_rates.json'), true));

            $methods = collect(json_decode(Storage::disk('local')->get('template/payment_providers.json'), true)['JO']);
            $payments = collect(DB::table('v1_company_payment_methods')->get())->groupBy('company_id');
            $payments->map(function ($payment, $merchantID) use ($methods) {
                $counter = 1;
                $data = [];
                foreach ($payment->toArray() as $key => $value) {

                    if ($value->bank_name) {
                        $name = $value->bank_beneficiary_name;
                        $iban = str_replace(' ', '', $value->bank_iban);
                        $provider_code = $methods->where('name_en', $value->bank_name)->first()['code'] ?? null;
                        if ($iban == '') {
                            $provider_code = '';
                        }

                    } else {
                        $name = ucfirst($value->method) . ' - ' . $value->wallet_provider;
                        $iban = $this->phone($value->wallet_number);
                        $provider_code = $methods->where('name_en', $value->wallet_provider)->first()['code'] ?? '';
                    }
                    if ($provider_code != '') {
                        $data[] = [
                            'id' => $counter++,
                            'name' => $name,
                            'provider_code' => $provider_code,
                            'iban' => $iban,
                            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        ];
                    }

                }

                $mer = Merchant::findOrFail($merchantID);
                $mer->payment_methods = collect($data);
                $mer->save();
            });
            return 'done';
        });
    }

    public function phone($phone)
    {
        $phone = str_replace(' ', '', $phone) ?? '';
        $phone = str_replace('00962', '+962', $phone) ?? '';
        if ($phone != '' && strpos('00962', $phone) === false) {
            $phone = str_replace('+962', '', $phone);
        }

        return $phone;
    }
}

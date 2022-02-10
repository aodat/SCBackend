<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Utilities\Shipcash;
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

    public function json()
    {
        DB::transaction(function () {
            $zones = DB::table('v1_zones')->pluck('name_en', 'id');
            $areas = DB::table('v1_areas')->pluck('name_en', 'id');
            $methods = collect(json_decode(Storage::disk('local')->get('template/payment_providers.json'), true)['JO']);

            DB::table('v1_companies')->get()->map(function ($company) use ($areas, $zones, $methods) {
                $shippers = json_decode($company->shippers);
                $method_counter = 1;
                $payment_methods = [];

                foreach (DB::table('v1_company_payment_methods')->where('company_id', $company->id)->get()->toArray() as $key => $value) {

                    if ($value->bank_name) {
                        $name = $value->bank_beneficiary_name;
                        $iban = str_replace(' ', '', $value->bank_iban);
                        $provider_code = $methods->where('name_en', $value->bank_name)->first() ?? null;
                        if ($iban == '') {
                            $provider_code = '';
                        }
                    } else {
                        $name = ucfirst($value->method) . ' - ' . $value->wallet_provider;
                        $code = $value->wallet_provider;
                        $codes[] = $value->wallet_provider;
                        if ($value->wallet_provider == 'zain') {
                            $code = 'zc';
                        } else if ($value->wallet_provider == 'orange') {
                            $code = 'om';
                        } else if ($value->wallet_provider == 'dinarak') {
                            $code = 'dn';
                        } else if ($value->wallet_provider == 'umniah') {
                            $code = 'um';
                        }

                        $iban = shipcash::phone($value->wallet_number);
                        $provider_code = $methods->where('code', $code)->first() ?? [];
                    }

                    if (isset($provider_code['code'])) {
                        $payment_methods[] = [
                            'id' => $method_counter++,
                            'name' => $name,
                            'provider_code' => $provider_code['code'],
                            'name_ar' => $provider_code['name_ar'],
                            'name_en' => $provider_code['name_en'],
                            'provider_name' => $provider_code['code'],
                            'iban' => $iban,
                            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        ];
                    }

                }

                $dom["1"] = [];
                $dom_counter = 1;
                foreach (DB::table('v1_company_zones')->where('company_id', $company->id)->get()->toArray() as $key => $value) {
                    $dom["1"][] = [
                        "id" => $dom_counter++,
                        "code" => $value->name_en,
                        "name_en" => $value->name_en,
                        "name_ar" => $value->name_ar,
                        "price" => floatval($value->price),
                        "additional" => 1.5,
                    ];
                }

                $add_counter = 1;
                $addresses = [];
                foreach (DB::table('v1_company_addresses')->where('company_id', $company->id)->get()->toArray() as $key => $value) {
                    $zone = $zones[$value->zone_id];
                    $area = $areas[$value->area_id];
                    $addresses[] = [
                        'id' => $add_counter++,
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

                $papers = [];
                if ($company->papers) {
                    $doc = json_decode($company->papers);
                    $paper_counter = 1;
                    foreach ($doc as $id => $value) {
                        $papers[] = [
                            'id' => $paper_counter++,
                            'type' => $value->key,
                            'url' => $value->path,
                            'status' => 'pending',
                            'verified_at' => null,
                            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        ];
                    }
                }

                $mercahnt = Merchant::create(
                    [
                        'id' => $company->id,
                        'name' => $company->name ?? 'No-Name',
                        'email' => str_replace(' ', '-', $company->name ?? 'no-name') . '+fake@shipcash.net',
                        'type' => 'individual',
                        'phone' => shipcash::phone($company->phone),
                        'documents' => collect($papers),
                        'addresses' => collect($addresses),
                        'payment_methods' => collect($payment_methods),
                        'country_code' => 'JO',
                        'currency_code' => 'JOD',
                        'domestic_rates' => collect($dom),
                        'express_rates' => collect(json_decode(Storage::disk('local')->get('template/express_rates.json'), true)),
                    ]
                );

                DB::table('v1_users')->where('company_id', $company->id)->get()->map(function ($user) use ($mercahnt) {
                    User::create(
                        [
                            'merchant_id' => $user->company_id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'password' => $user->password,
                            'phone' => Shipcash::phone($user->phone_number),
                            'is_owner' => true,
                            'role' => $user->role ?: 'admin',
                        ]
                    );
                });
            });
        });
    }
}

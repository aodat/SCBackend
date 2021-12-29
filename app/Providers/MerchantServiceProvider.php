<?php

namespace App\Providers;

use App\Models\Carriers;
use App\Models\Merchant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Countries;

use Illuminate\Support\Str;

use Illuminate\Support\Facades\Storage;

class MerchantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('merchantInfo', function () {
            if (Request()->user() === null)
                return [
                    'country_code' => 'JO',
                    'domestic_rates' => collect(json_decode(Storage::disk('local')->get('template/domestic_rates.json'), true)),
                    'express_rates' => collect(json_decode(Storage::disk('local')->get('template/express_rates.json'), true))
                ];
            return Merchant::findOrFail(Auth::user()->merchant_id);
        });


        $this->app->singleton('merchantCarriers', function () {
            if (!Auth::user())
                return [];
            return collect(Merchant::findOrFail(Auth::user()->merchant_id)->carriers);
        });


        $this->app->singleton('merchantAddresses', function () {
            if (!Auth::user())
                return [];
            return collect(Merchant::findOrFail(Auth::user()->merchant_id)->addresses);
        });

        $this->app->singleton('merchantRules', function () {
            if (!Auth::user())
                return [];
            return collect(Merchant::findOrFail(Auth::user()->merchant_id)->rules)->where('is_active', true);
        });


        $this->app->singleton('Countrieslookup', function () {
            return Countries::lookup('en', true);
        });
        
        $this->app->singleton('request_id' ,function () {
            return Str::orderedUuid()->toString();
        });


        $this->app->singleton('carriers' ,function () {
            return Carriers::all();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}

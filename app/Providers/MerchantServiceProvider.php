<?php

namespace App\Providers;

use App\Models\Merchant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Countries;
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
            if (!Auth::user())
                return [];
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


        $this->app->singleton('merchantRules', function () {
            if (!Auth::user())
                return [];
            return collect(Merchant::findOrFail(Auth::user()->merchant_id)->rules)->where('is_active', true);
        });

        $this->app->singleton('country', function () {
            return collect(json_decode(Storage::disk('local')->get('template/city.json'), true));
        });

        $this->app->singleton('Countrieslookup', function () {
            return Countries::lookup('en', true);
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

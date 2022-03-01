<?php

namespace App\Providers;

use App\Models\Carriers;
use App\Models\Merchant;
use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Str;


class MerchantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $merchantID = Request()->user() ? Request()->user()->merchant_id : env('GUEST_MERCHANT_ID');

        $this->app->singleton('merchantInfo', function () use ($merchantID) {
            return Merchant::findOrFail($merchantID);
        });

        $this->app->singleton('merchantCarriers', function () use ($merchantID) {
            return collect(Merchant::findOrFail($merchantID)->carriers);
        });

        $this->app->singleton('merchantAddresses', function () use ($merchantID) {
            return collect(Merchant::findOrFail($merchantID)->addresses);
        });

        $this->app->singleton('merchantRules', function () use ($merchantID) {
            return collect(Merchant::findOrFail($merchantID)->rules)->where('is_active', true);
        });

        $this->app->singleton('request_id', function () {
            return Str::orderedUuid()->toString();
        });

        $this->app->singleton('carriers', function () {
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

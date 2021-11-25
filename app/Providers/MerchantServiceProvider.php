<?php

namespace App\Providers;

use App\Models\Merchant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

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
            return Merchant::find(Auth::user()->merchant_id);
        });


        $this->app->singleton('merchantCarriers', function () {
            if (!Auth::user())
                return [];
            return collect(Merchant::find(Auth::user()->merchant_id)->carriers);
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

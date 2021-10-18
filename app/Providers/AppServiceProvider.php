<?php

namespace App\Providers;

use App\Http\Repositories\User\IUserRepo;
use App\Http\Repositories\User\DBUserRepo;

use App\Http\Repositories\Merchant\IMerchantRepo;
use App\Http\Repositories\Merchant\DBMerchantRepo;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(IUserRepo::class, DBUserRepo::class);
        $this->app->bind(IMerchantRepo::class, DBMerchantRepo::class);
    }
}

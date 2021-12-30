<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;


use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use Countries;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        set_time_limit('0');
        $this->call([
            fakeUserShipments::class
        ]);
    }
}

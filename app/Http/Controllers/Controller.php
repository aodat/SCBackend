<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Traits\CarriersManager;
use App\Traits\ResponseHandler;
use App\Traits\SystemRules;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

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
        $merchants = Merchant::get();
        $merchants->map(function ($merchant) {
            $new[] = [
                'carrier_id' => 1,
                'carrier_name' => 'Aramex',
                'weight' => 10,
                'zones' => $merchant->domestic_rates
            ];
            dd($new);
            dd($merchant->domestic_rates);
        });
        die('Stop Work');
    }
}

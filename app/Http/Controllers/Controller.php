<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Utilities\Shipcash;
use App\Models\Merchant;
use App\Models\Shipment;
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
}

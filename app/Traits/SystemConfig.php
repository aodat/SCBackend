<?php

namespace App\Traits;

use App\Jobs\Sms;
use App\Models\Merchant;
use App\Models\PinCode;
use Carbon\Carbon;

trait SystemConfig
{
    public function countries()
    {
        return [];
    }
}

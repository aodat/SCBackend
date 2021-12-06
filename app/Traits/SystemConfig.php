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

    public function verifyMerchantPhoneNumber($merchant_id, $type)
    {
        $PinCode = PinCode::where('Merchant_id', $merchant_id)
            ->where('type', '=', $type)
            ->where('status', '=', 'is_active')
            ->orderBy('created_at', 'desc')
            ->first();

        $diff = isset($PinCode->created_at) ? $PinCode->created_at->diffInSeconds() : false;

        if ($diff !== false)
            if ($diff <=  300)
                return $this->error("is verify is send");

        $randomPinCode = mt_rand(111111, 999999);
        $Merchant = Merchant::where('id', '=', $merchant_id)->first();
        Sms::dispatch($randomPinCode, $Merchant->phone);

        PinCode::create([
            "code" => $randomPinCode,
            "type" =>  $type,
            'Merchant_id' =>  $Merchant->id,
            "status" => 'is_active',
        ]);

        return $this->successful('Pin code was sent check your mobile');
    }

    public function cheakVerifyMerchantPhoneNumber($code, $merchant_id, $type)
    {
        $PinCode = PinCode::where('Merchant_id', $merchant_id)
            ->where('type', '=', $type)
            ->where('status', '=', 'is_active')
            ->where('code', $code)
            ->orderBy('created_at', 'desc')
            ->first();

        $diff = isset($PinCode->created_at) ? $PinCode->created_at->diffInSeconds() : false;

        if ($diff === false)
            return false;
        if ($diff >  300)
            return false;

        $PinCode->status = 'use';
        $PinCode->save();
        return  $PinCode;
    }
}

<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

class CheckIbanWalletCharRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    private $provider_code;
    public function __construct($provider_code)
    {
        //
        $this->provider_code = $provider_code;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $country_code = App::make('merchantInfo')->country_code;
        $providers =  (json_decode(Storage::disk('local')->get('template/payment_providers.json'), true));

        if (!isset($providers[$country_code]))
            return false;

        $provider =   collect($providers[$country_code])
            ->where('code', $this->provider_code)
            ->first();
        if ($provider) {
            $type = $provider['type'];
            if ($type == 'bank' && (strlen($value) > 14 && strlen($value) <= 32))
                return true;
            else if ($type == 'wallet' && (strlen($value) == 10))
                return true;
        }
        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return "Invalid Number Of Characters For IBAN Code / Wallet Number";
    }
}

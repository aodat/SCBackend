<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

class ProviderCodeRule implements Rule
{
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

        if (isset($providers[$country_code]))
            return collect($providers[$country_code])->where('code', $value)->first();
        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Invalid Provider Code.';
    }
}

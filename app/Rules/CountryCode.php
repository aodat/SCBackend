<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\App;
use Countries;

class CountryCode implements Rule
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
        $Countries =  Countries::lookup('en', true);
        $reqCountry = Request('country');
        $countryCode = isset($Countries[$reqCountry]) ? $Countries[$reqCountry] : null;
        return  $value ===  $countryCode;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Invalid country code';
    }
}

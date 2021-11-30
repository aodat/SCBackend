<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Countries;
use Illuminate\Support\Facades\App;

class CountryCode implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
        $Countries =  App::make('Countrieslookup');
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
        return 'Country Code does not valid.';
    }
}

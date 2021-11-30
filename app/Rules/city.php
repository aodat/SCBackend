<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\App;

class City implements Rule
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
        $country =  App::make('country');
        $reqCountry = Request('country');
        $city = ($country->has($reqCountry)) ? $country[$reqCountry] : false;
        if ($city !== false)
            return   in_array($value, $country[$reqCountry]);
        else
            return  false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'invalid City  attribute :attribute ';
    }
}

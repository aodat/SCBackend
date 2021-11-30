<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class Phone implements Rule
{
    private $country_code;
    private $country_keys = [
        'jo' => '+962',
        'ksa' => '+966'
    ];
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($country_code = null)
    {
        $this->country_code = $country_code;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $phone)
    {
        $key = $this->country_keys[strtolower($this->country_code)] ?? null;
        if ($key == false)
            return false;
        return (substr($phone, 0, 4) == $key);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Phone Number Not Matched With Country Prefix Key.';
    }
}

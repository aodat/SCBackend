<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Pincode;

class PincodeVerification implements Rule
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

        $pincode = Pincode::where('code', $value)
            ->orderBy('id', 'desc')->first();

        if (
            isset($pincode->created_at) && $pincode->created_at->diffInSeconds() < 300
        ) {
            $pincode->status = 'used';
            $pincode->save();
            
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
        return 'Pincode is invalid.';
    }
}

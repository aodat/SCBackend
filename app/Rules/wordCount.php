<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class wordCount implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    private $min, $max;

    public function __construct(int $min, int $max = 6)
    {
        $this->min = $min;
        $this->max = $max;
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
        $numWords = count(explode(' ', trim($value)));
        return ($numWords >= $this->min && $numWords <= $this->max);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The field must have between ' . $this->min . ' and ' . $this->max . ' words';
    }
}

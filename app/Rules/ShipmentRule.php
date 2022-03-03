<?php

namespace App\Rules;

use AWS\CRT\HTTP\Request;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\App;

class ShipmentRule implements Rule
{
    private $type, $data;
    public function __construct($type, $data = [])
    {
        $this->type = $type;
        $this->data = $data;
    }
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($key, $value)
    {
        $index = explode('.', $key)[0];
        if ($index == $key)
            $index = null;

        $merchantInfo = App::make('merchantInfo');
        if ($this->type == 'carrier') {
            return App::make('carriers')
                ->where('id', $value)
                ->where('is_active', true)
                ->where(
                    (strpos(Request()->route()->uri, 'shipments/domestic/create') !== false) ? 'domestic' : 'express',
                    true
                )->first();
        } else if ($this->type == 'address') {
            $address = App::make('merchantAddresses')->where('id', $value)->first();
            if (is_null($address))
                return false;

            $addressData = [
                'sender_email' => $merchantInfo->email,
                'sender_name' => $address['name'],
                'sender_phone' => $address['phone'],
                'sender_country' => $address['country_code'],
                'sender_city' => $address['city'],
                'sender_area' => $address['area'],
                'sender_address_description' => $address['description'],
            ];


            if (strpos(Request()->route()->uri, 'shipments/domestic/create') !== false) {
                $requests = Request()->all();
                unset($requests[$index]['sender_address_id']);
                $requests[$index] = array_merge($requests[$index], $addressData);
                Request()->merge($requests);
            } else {
                Request()->request->remove('sender_address_id');
                Request()->merge($addressData);
            }
        
        } else if ($this->type == 'word_count') {
            $numWords = count(explode(' ', trim($value)));
            return ($numWords >= $this->data['min'] && $numWords <= $this->data['max']);
        }
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        if ($this->type == 'carrier')
            return 'Invalid Carrier ID';
        else if ($this->type == 'address')
            return 'Invalid Address ID.';
        else if ($this->type == 'word_count')
            return 'The field must have between ' . $this->data['min'] . ' and ' . $this->data['max'] . ' words';
    }
}

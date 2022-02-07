<?php

namespace App\Http\Controllers\Utilities;

class Shipcash
{

    public static function exchange($amount, $from, $to = 'USD')
    {
        $rates = [
            'JOD' => 1.41,
            'SAR' => 0.27,
        ];
        return $rates[$from] * $amount;
    }

    public static function get_string_between($string, $start, $end)
    {
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) {
            return '';
        }

        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    public static function phone($phone)
    {
        $phone = str_replace(' ', '', $phone) ?? '';
        $phone = str_replace('00962', '+962', $phone) ?? '';
        if ($phone != '' && strpos('00962', $phone) === false) {
            $phone = str_replace('+962', '', $phone);
        }

        return $phone;
    }
}

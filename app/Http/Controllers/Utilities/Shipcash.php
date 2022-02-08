<?php

namespace App\Http\Controllers\Utilities;

use Illuminate\Support\Str;

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

    public static function phone($phone, $allow_null = false)
    {
        $phone = intval($phone);
        $phone = str_replace(' ', '', $phone) ?? '';
        $phone = str_replace('-', '', $phone);
        $phone = str_replace('00962', '+962', $phone) ?? '';
        if ($phone != '' && strpos('00962', $phone) === false) {
            $phone = str_replace('+962', '', $phone);
        }
        // Format the number to 962...
        while (Str::startsWith($phone, '0')) {
            $phone = Str::after($phone, '0');
        }

        while (Str::startsWith($phone, '+')) {
            $phone = Str::after($phone, '+');
        }

        if (!Str::startsWith($phone, '962')) {
            $phone = "962$phone";
        }

        if (Str::length($phone) < 10) {
            return null;
        }

        return $phone;
    }
}

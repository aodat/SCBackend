<?php

namespace App\Messenger;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SmsService
{
	public static function sendSMS($body, string $number, $lang = 'ar')
	{
		// Format the number to 962...
		while (Str::startsWith($number, '0'))
			$number = Str::after($number, '0');

		while (Str::startsWith($number, '+'))
			$number = Str::after($number, '+');

		if (!Str::startsWith($number, '962'))
			$number = "962$number";


		$data = [
			'login_name' => 'shipcash',
			'login_password' => 'Cashpass_2032',
			'charset' => 'UTF-8',
			'msg' => $body,
			'mobile_number' => $number,
			'from' => 'ShipCash'
		];
		
		return Http::get("http://sendsms.ngt.jo/http/send_sms_http.php?".http_build_query($data));
	}

	public static function sendPinCode(string $number)
	{
		$randomPinCode = mt_rand(111111, 999999);

		session(['pin_code' => $randomPinCode]);

		if (auth()->check()) {
			auth()->user()->update(['pin_code' => $randomPinCode]);
		}

		self::sendSMS($randomPinCode, $number);
	}

	public static function verifyPinCode($pin)
	{
		if (auth()->check() && auth()->user()->pin_code == $pin || session('pin_code') == $pin) {
			return true;
		}

		return false;
	}
}

<?php

namespace App\Http\Controllers\Utilities;

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

		return Http::get("http://sendsms.ngt.jo/http/send_sms_http.php?" . http_build_query($data));
	}
}

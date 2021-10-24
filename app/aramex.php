<?php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class aramex
{
    private static $CREATE_PICKUP_URL = 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc/json/CreatePickup';

    private $config;
    function __construct()
    {
        $this->config = [
            'aramex_username' => config('aramex.ARAMEX_USERNAME'),
            'aramex_password' => config('aramex.ARAMEX_PASSWORD'),
            'aramex_account_number' => config('aramex.ARAMEX_ACCOUNT_NUMBER'),
            'aramex_pin' => config('aramex.ARAMEX_PIN'),
            'aramex_account_entity' => config('aramex.ARAMEX_ACCOUNT_ENTITY'),
            'aramex_account_country_code' => config('aramex.ARAMEX_ACCOUNT_COUNTRY_CODE'),
            'version' => config('aramex.VERSION'),
            'source' => config('aramex.SOURCE')
        ];
    }

    public function createPickup($data)
    {
        $payload = json_decode(Storage::disk('local')->get('template/aramex/pickup.create.json'),true);
        $payload['ClientInfo'] = $this->config;

        $payload['Pickup']['PickupAddress']['Line1'] = '';
        $payload['Pickup']['PickupAddress']['Line2'] = '';
        $payload['Pickup']['PickupAddress']['Line3'] = '';
        $payload['Pickup']['PickupAddress']['City'] = '';
        
        $payload['Pickup']['PickupContact']['PersonName'] = '';
        $payload['Pickup']['PickupContact']['CompanyName'] = '';
        $payload['Pickup']['PickupContact']['PhoneNumber1'] = '';
        $payload['Pickup']['PickupContact']['CellPhone'] = '';
        $payload['Pickup']['PickupContact']['EmailAddress'] = '';

        $payload['Pickup']['PickupLocation'] = '';
        $payload['Pickup']['PickupDate'] = '';
        $payload['Pickup']['ReadyTime'] = '';
        $payload['Pickup']['LastPickupTime'] = '';
        $payload['Pickup']['ClosingTime'] = '';
        $payload['Pickup']['ClosingTime'] = '';
        $payload['Pickup']['Reference1'] = '';
        $payload['Pickup']['PickupItems']['NumberOfPieces'] = 0;
        $payload['Pickup']['PickupItems']['Comments'] = '';

        
        $response = Http::post(self::$CREATE_PICKUP_URL, $payload);
        dd($response);
        if (! $response->successful()) {
            dd($response->successful());
            // throw new AramexException("Aramex Create Pickup – Something Went Wrong", 1);
        }

        if ($response->json()['HasErrors']) {
            dd($response->json()['HasErrors']);
            // throw new AramexException("Cannot create pickup please call 06 535 8855", null, null, $response->json()['Notifications']);

        }

        return $response->json();
    }
}
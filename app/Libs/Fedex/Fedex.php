<?php

namespace Libs;

use App\Exceptions\CarriersException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class Fedex
{
    private $config;

    private static $stagingUrl = 'https://apis-sandbox.fedex.com/';
    private static $productionUrl = 'https://apis.fedex.com/';

    private $base_url;
    function __construct()
    {
        $this->access_key =
            $this->account_number = config('fedex.ACCOUNT_NUMBER');
        $this->base_url = self::$stagingUrl;
    }
    public function createPickup($email, $date, $address)
    {
        $payload = $this->bindJsonFile('pickup.create.json');
        $payload['pickupNotificationDetail']['emailDetails']['email'] = $email;

        $payload['originDetail']['pickupLocation']['contact']['companyName'] = $address['name'];
        $payload['originDetail']['pickupLocation']['contact']['personName'] = $address['name_en'];
        $payload['originDetail']['pickupLocation']['contact']['phoneNumber'] = $address['phone'];

        $payload['originDetail']['pickupLocation']['address']['streetLines'][0] = $address['description'];
        $payload['originDetail']['pickupLocation']['address']['city'] = $address['name_en'];
        $payload['originDetail']['pickupLocation']['address']['countryCode'] = $address['country_code'];
        $payload['originDetail']['pickupLocation']['accountNumber']['value'] = $this->account_number;

        $payload['originDetail']['readyDateTimestamp'] = '2020-04-21T11:00:00Z';
        $payload['originDetail']['customerCloseTime'] = '15:00:00';
        $payload['originDetail']['buildingPartDescription'] = $address['area'];



        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])
            ->withToken($this->access_key)
            ->asForm()
            ->post($this->base_url . '/pickup/v1/pickups', $payload);

        if (!$response->successful())
            throw new CarriersException('Fedex Create Pickup – Something Went Wrong');

        dd($response->json());
        // $final = $response->json();
        // return ['id' => $final['ProcessedPickup']['ID'], 'guid' => $final['ProcessedPickup']['GUID']];
    }

    public function cancelPickup($pickupInfo)
    {
        $payload = $this->bindJsonFile('pickup.cancel.json');
        $payload['pickupConfirmationCode'] = $pickupInfo->hash;
        $payload['scheduledDate'] = $pickupInfo->pickup_date;


        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])
            ->withToken($this->access_key)
            ->asForm()
            ->put($this->base_url . '/pickup/v1/pickups/cancel', $payload);

        if (!$response->successful())
            throw new CarriersException('Fedex Create Pickup – Something Went Wrong');

        dd($response->json());
    }

    public function printLabel()
    {
    }

    public function createShipment()
    {
    }

    public function bindJsonFile($file)
    {
        $payload = json_decode(file_get_contents(storage_path() . '/../Fedex/Libs/DHL/' . $file), true);
        $payload['associatedAccountNumber']['value'] = $this->account_number;
        return $payload;
    }
}

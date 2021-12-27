<?php

trait ShpcahePaymentAPI
{

    public function payment_with_api($total, $token, $BearerToken)
    {
        $curl = curl_init();
        $data = json_encode(array(
            "amount" => 100 * (float) $total,
            "token" =>  $token,
        ));
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-dev.shipcash.net/api/merchant/transactions/deposit',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $BearerToken,
                'Content-Type: application/json'
            ),
        ));

        $response = (array)(curl_exec($curl));
        $response =  (array)  json_decode($response[0]);
        curl_close($curl);

        $code  = (int) $response['meta']->code ?? 400;

        if ($code !== 200)
            return  array("error" => true, "message" => $response['meta']->msg);
        else
            return  array("error" => false, "message" => $response['meta']->msg);
    }

    public function  payement_card_stripe($data_card)
    {

        $card_number =   $data_card['card_number'];
        $exp_month = $data_card['exp_month'];
        $exp_year = $data_card['exp_year'];
        $cvc = $data_card['cvc'];

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.stripe.com/v1/tokens',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'card%5Bnumber%5D=' . $card_number . '&card%5Bexp_month%5D=' . $exp_month . '&card%5Bexp_year%5D=' . $exp_year . '&card%5Bcvc%5D=' . $cvc,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer sk_test_51IXEvJHIHNy1at3SPIR8zJdgrX2W1OtyL1Kb7GTH3LOQa1wPbmFuhWBjgZPvgZlOnqyRhsRdKNhOfUL4ySuPcXG100nALYRbXU',
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));
        $response = (array)(curl_exec($curl));
        $response =  (array) json_decode($response[0]);
        curl_close($curl);

        if (!isset($response['id']) || $response['id'] === null)
            return  array('data' => null, "error" => true, "message" => $response['error']->message);
        else
            return  array('data' => $response['id'], "error" => false, "message" => '');
    }
}

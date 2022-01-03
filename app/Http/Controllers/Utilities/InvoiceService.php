<?php

namespace App\Http\Controllers\Utilities;

use LaravelDaily\Invoices\Invoice;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Classes\InvoiceItem;

class InvoiceService
{
    public static function commercial($data)
    {
        // dd($data);
        $client = new Party([
            'name'          => $data->sender_name,
            'phone'         => $data->sender_phone,
            'custom_fields' => [
                $data->sender_email,
                $data->sender_country . ' , ' . $data->sender_city,
                $data->sender_area,
                $data->sender_address_description
            ],
        ]);

        $customer = new Buyer([
            'name'          => $data->consignee_name,
            'phone'         => $data->consignee_phone,
            'custom_fields' => [
                $data->consignee_email,
                $data->consignee_country . ' , ' . $data->consignee_city,
                $data->consignee_area . ' ' . $data->consignee_zip_code,
                $data->consignee_address_description
            ],
        ]);

        $item = (new InvoiceItem())->title($data->content)
            ->quantity($data->pieces)
            ->subTotalPrice($data->declared_value)
            ->pricePerUnit($data->declared_value);

        $invoice = Invoice::make()
            ->buyer($customer)
            ->seller($client)
            ->currencySymbol('$')
            ->currencyCode('USD')
            ->currencyFormat('{SYMBOL}{VALUE}')
            ->addItem($item);

        if ($data->consignee_notes != '')
            $invoice->notes($data->consignee_notes);
        return $invoice->save('s3')->url();
    }
}

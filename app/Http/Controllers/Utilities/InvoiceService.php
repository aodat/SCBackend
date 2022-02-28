<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Utilities\Shipcash;
use App\Models\Merchant;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Invoice;

class InvoiceService
{
    public static function commercial($data)
    {
        $mertchatInfo = Merchant::findOrFail($data->merchant_id);

        $client = new Party([
            'name' => $data->sender_name,
            'phone' => $data->sender_phone,
            'custom_fields' => [
                $data->sender_email,
                $data->sender_country . ' , ' . $data->sender_city,
                $data->sender_area,
                $data->sender_address_description,
            ],
        ]);

        $customer = new Buyer([
            'name' => $data->consignee_name,
            'phone' => $data->consignee_phone,
            'custom_fields' => [
                $data->consignee_email,
                $data->consignee_country . ' , ' . $data->consignee_city,
                $data->consignee_area . ' ' . $data->consignee_zip_code,
                $data->consignee_address_description,
            ],
        ]);

        $price = Shipcash::exchange($data->declared_value, $mertchatInfo->currency_code);

        $item = (new InvoiceItem())->title($data->content)
            ->quantity($data->pieces)
            ->subTotalPrice($price)
            ->pricePerUnit($price);

        $invoice = Invoice::make()
            ->series($data->awb)
            ->buyer($customer)
            ->seller($client)
            ->currencySymbol('$')
            ->currencyCode('USD')
            ->currencyFormat('{SYMBOL}{VALUE}')
            ->filename('invoices/' . md5(time()))
            ->addItem($item);

        if ($data->consignee_notes != '') {
            $invoice->notes($data->consignee_notes);
        }

        return $invoice->save('s3')->url();
    }

    public static function invoice($merchant_id, $trx_id, $amount, $description)
    {
        $mertchatInfo = Merchant::findOrFail($merchant_id);

        $client = new Party([
            'id' => $mertchatInfo->id,
            'name' => $mertchatInfo->name,
            'phone' => $mertchatInfo->phone,
        ]);

        $customer = new Buyer([
            'name' => 'ShipCash System',
        ]);

        $item = (new InvoiceItem())->title($description)
            ->quantity(1)
            ->subTotalPrice($amount)
            ->pricePerUnit($amount);

        $invoice = Invoice::make('receipt')
            ->template('bundle_cashin')
            ->series($trx_id)
            ->seller($client)
            ->buyer($customer)
            ->currencySymbol(' JOD ')
            ->currencyCode(' JOD ')
            ->currencyFormat('{SYMBOL}{VALUE}')
            ->filename('invoices/' . md5(time()))
            ->addItem($item);

        return $invoice->save('s3')->url();
    }
}

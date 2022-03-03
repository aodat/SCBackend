<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

class ShipmentExport implements FromCollection, WithMapping, WithHeadings, ShouldAutoSize
{
    protected $shipments;

    function __construct($shipments)
    {
        $this->shipments = $shipments;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->shipments;
    }

    public function headings(): array
    {
        return [
            'Sender Name',
            'Consignee Name',
            'Consignee Phone Number',
            'City',
            'Area',
            'Address',
            'COD',
            'Delivery Date',
            'Pieces',
            'Shipment Content',
            'Status',
            'Created Date',
        ];
    }

    public function map($shipment): array
    {
        return [
            $shipment->sender_name,
            $shipment->consignee_name,
            $shipment->consignee_phone,
            $shipment->consignee_city,
            $shipment->consignee_area,
            $shipment->consignee_address_description,
            ($shipment->cod == '') ?? 0,
            (!is_null($shipment->delivered_at)) ? date('Y-m-d', strtotime($shipment->delivered_at)) : '',
            $shipment->pieces,
            $shipment->content,
            $shipment->status,
            date('Y-m-d', strtotime($shipment->created_at)),
        ];
    }
}

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
            'Consignee name',
            'Consignee phone number',
            'City',
            'Area',
            'Address',
            'COD',
            'Delivery Date',
            'Pieces',
            'Shipment Content',
            'Status',
            'Created At',
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
            $shipment->cod,
            date('Y-m-d', strtotime($shipment->delivered_at)),
            $shipment->pieces,
            $shipment->content,
            $shipment->status,
            date('Y-m-d', strtotime($shipment->created_at)),
        ];
    }
}

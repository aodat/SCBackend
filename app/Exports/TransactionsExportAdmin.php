<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TransactionsExportAdmin implements FromCollection, WithMapping, WithHeadings, ShouldAutoSize
{
    protected $transactions;

    public function __construct($transactions)
    {
        $this->transactions = $transactions;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->transactions;
    }

    public function headings(): array
    {
        return [
            'Transaction ID',
            'Merchant ID',
            'Payment Name',
            'Payment IBAN',
            'Amount',
            'Date',
        ];
    }

    public function map($transaction): array
    {
        return [
            $transaction->id,
            $transaction->merchant_id,
            $transaction->payment_method['name_en'] ?? '--',
            $transaction->payment_method['iban'] ?? '--',
            $transaction->amount,
            date('Y-m-d', strtotime($transaction->created_at)),
        ];
    }
}

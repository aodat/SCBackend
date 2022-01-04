<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

class TransactionsExport implements FromCollection, WithMapping, WithHeadings, ShouldAutoSize
{
    protected $transactions;
    
    function __construct($transactions)
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
            'Type',
            'Amount',
            'Balance After',
            'Description',
            'Notes',
            'Status',
            'Source',
            'Create AT',
        ];
    }

    public function map($transaction): array
    {
        return [
            $transaction->type,
            $transaction->amount,
            $transaction->balance_after,
            $transaction->description,
            $transaction->notes,
            $transaction->status,
            $transaction->source,
            date('Y-m-d', strtotime($transaction->created_at)),
        ];
    }
}
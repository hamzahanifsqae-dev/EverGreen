<?php

namespace App\Filament\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ColdStorageCustomerLedgerExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(protected array $rows, protected float $openingBalance) {}

    public function headings(): array
    {
        return ['Date', 'Description', 'Reference', 'Debit', 'Credit', 'Balance'];
    }

    public function array(): array
    {
        $rows = [['Opening balance', '', '', '', '', $this->openingBalance]];

        foreach ($this->rows as $row) {
            $rows[] = [
                $row['date'],
                $row['description'],
                $row['reference'] ?? '',
                $row['debit'] > 0 ? $row['debit'] : '',
                $row['credit'] > 0 ? $row['credit'] : '',
                $row['balance'],
            ];
        }

        return $rows;
    }
}

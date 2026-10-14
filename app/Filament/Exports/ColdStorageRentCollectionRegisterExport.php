<?php

namespace App\Filament\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ColdStorageRentCollectionRegisterExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(protected array $rows) {}

    public function headings(): array
    {
        return ['Date', 'Customer', 'Withdrawal ref.', 'Bill ref.', 'Bags', 'Rate', 'Charged', 'Collected', 'Due', 'Method'];
    }

    public function array(): array
    {
        return array_map(fn (array $row): array => [
            $row['date'],
            $row['customer'],
            $row['withdrawal_reference'] ?? '',
            $row['bill_reference'] ?? '',
            $row['bags'],
            $row['rate'] ?? '',
            $row['charged'] ?? '',
            $row['collected'] ?? '',
            $row['due'] ?? '',
            $row['method'] ?? '',
        ], $this->rows);
    }
}

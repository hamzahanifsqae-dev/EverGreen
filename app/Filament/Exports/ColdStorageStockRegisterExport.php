<?php

namespace App\Filament\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ColdStorageStockRegisterExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @param  list<array<string, mixed>>  $groups
     */
    public function __construct(protected array $groups) {}

    public function headings(): array
    {
        return ['Customer', 'Lot', 'Product', 'Date', 'Inward', 'Outward', 'Adjustment', 'Closing', 'Reference'];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->groups as $group) {
            foreach ($group['rows'] as $row) {
                $rows[] = [
                    $group['customer'],
                    $group['lot_number'],
                    $group['product'],
                    $row['date'],
                    $row['inward'] > 0 ? $row['inward'] : 'NIL',
                    $row['outward'] > 0 ? $row['outward'] : 'NIL',
                    $row['adjustment'] != 0 ? $row['adjustment'] : 'NIL',
                    $row['closing'],
                    implode(', ', $row['references']),
                ];
            }
        }

        return $rows;
    }
}

<?php

namespace App\Filament\ColdStorage;

use App\Models\ColdStorageReceiptItem;
use App\Support\ColdStorageAccess;

class LotOptions
{
    /**
     * @return array<string, string>
     */
    public static function forDocument(callable $get): array
    {
        $merchantId = ColdStorageAccess::merchantId();

        return ColdStorageReceiptItem::query()
            ->with('product')
            ->whereHas('receipt', function ($query) use ($merchantId, $get): void {
                $query->where('status', 'posted');

                if ($merchantId) {
                    $query->where('merchant_id', $merchantId);
                }

                if ($get('../customer_id')) {
                    $query->where('customer_id', $get('../customer_id'));
                }

                if ($get('../branch_id')) {
                    $query->where('branch_id', $get('../branch_id'));
                }
            })
            ->get()
            ->mapWithKeys(fn (ColdStorageReceiptItem $item): array => [
                $item->id => $item->lot_number.' · '.($item->product?->name ?? 'Product'),
            ])
            ->all();
    }
}

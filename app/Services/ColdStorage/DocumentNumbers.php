<?php

namespace App\Services\ColdStorage;

use Illuminate\Support\Facades\DB;

class DocumentNumbers
{
    public static function next(string $table, string $column, string $merchantId, string $prefix): string
    {
        $base = $prefix.'-'.now()->format('Ymd').'-';

        $last = DB::table($table)
            ->where('merchant_id', $merchantId)
            ->where($column, 'like', $base.'%')
            ->lockForUpdate()
            ->orderByDesc($column)
            ->value($column);

        $sequence = 1;

        if (is_string($last) && preg_match('/(\d+)$/', $last, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $base.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}

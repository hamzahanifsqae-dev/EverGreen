<?php

namespace App\Models;

use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageBillLine extends Model implements Auditable
{
    use IsAuditedUuid;

    protected $fillable = [
        'bill_id', 'receipt_item_id', 'rate_card_id', 'lot_number', 'period_start', 'period_end',
        'charge_basis', 'charge_period', 'quantity_days', 'billable_days', 'rate', 'line_total',
        'bill_arrival_day', 'bill_departure_day', 'rounding_mode', 'season_length_days', 'minimum_charge',
        'calculation_note',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'quantity_days' => 'decimal:3',
        'rate' => 'decimal:4',
        'line_total' => 'decimal:2',
        'minimum_charge' => 'decimal:2',
        'bill_arrival_day' => 'boolean',
        'bill_departure_day' => 'boolean',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(ColdStorageBill::class, 'bill_id');
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(ColdStorageReceiptItem::class, 'receipt_item_id');
    }

    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(ColdStorageRateCard::class, 'rate_card_id');
    }
}

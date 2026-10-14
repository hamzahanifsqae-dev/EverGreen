<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColdStorageBilledEntitlement extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'bill_line_id', 'receipt_item_id', 'occurred_on', 'quantity',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'quantity' => 'decimal:3',
    ];

    public function billLine(): BelongsTo
    {
        return $this->belongsTo(ColdStorageBillLine::class, 'bill_line_id');
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(ColdStorageReceiptItem::class, 'receipt_item_id');
    }
}

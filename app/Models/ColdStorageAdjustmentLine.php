<?php

namespace App\Models;

use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageAdjustmentLine extends Model implements Auditable
{
    use IsAuditedUuid;

    protected $fillable = [
        'adjustment_id', 'receipt_item_id', 'chamber_id', 'location_id', 'package_delta', 'weight_delta',
    ];

    protected $casts = [
        'package_delta' => 'decimal:3',
        'weight_delta' => 'decimal:3',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(ColdStorageAdjustment::class, 'adjustment_id');
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(ColdStorageReceiptItem::class, 'receipt_item_id');
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(ColdStorageChamber::class, 'chamber_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ColdStorageLocation::class, 'location_id');
    }
}

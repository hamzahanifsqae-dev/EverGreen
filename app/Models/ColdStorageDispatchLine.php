<?php

namespace App\Models;

use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageDispatchLine extends Model implements Auditable
{
    use IsAuditedUuid;

    protected $fillable = [
        'dispatch_id', 'receipt_item_id', 'chamber_id', 'location_id', 'package_count', 'net_weight',
    ];

    protected $casts = [
        'package_count' => 'decimal:3',
        'net_weight' => 'decimal:3',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(ColdStorageDispatch::class, 'dispatch_id');
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

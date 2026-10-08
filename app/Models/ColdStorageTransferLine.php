<?php

namespace App\Models;

use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageTransferLine extends Model implements Auditable
{
    use IsAuditedUuid;

    protected $fillable = [
        'transfer_id', 'receipt_item_id', 'from_chamber_id', 'from_location_id', 'to_chamber_id', 'to_location_id',
        'package_count', 'net_weight',
    ];

    protected $casts = [
        'package_count' => 'decimal:3',
        'net_weight' => 'decimal:3',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(ColdStorageTransfer::class, 'transfer_id');
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(ColdStorageReceiptItem::class, 'receipt_item_id');
    }

    public function fromChamber(): BelongsTo
    {
        return $this->belongsTo(ColdStorageChamber::class, 'from_chamber_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(ColdStorageLocation::class, 'from_location_id');
    }

    public function toChamber(): BelongsTo
    {
        return $this->belongsTo(ColdStorageChamber::class, 'to_chamber_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(ColdStorageLocation::class, 'to_location_id');
    }
}

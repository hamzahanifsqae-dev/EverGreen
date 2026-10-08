<?php

namespace App\Models;

use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $receipt_item_id
 * @property string $chamber_id
 * @property string|null $location_id
 * @property string|float $package_count
 * @property string|float $net_weight
 */
class ColdStorageReceiptAllocation extends Model implements Auditable
{
    use IsAuditedUuid;

    protected $fillable = [
        'receipt_item_id', 'chamber_id', 'location_id', 'package_count', 'net_weight',
    ];

    protected $casts = [
        'package_count' => 'decimal:3',
        'net_weight' => 'decimal:3',
    ];

    public function item(): BelongsTo
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

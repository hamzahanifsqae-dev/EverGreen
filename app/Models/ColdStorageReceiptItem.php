<?php

namespace App\Models;

use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $receipt_id
 * @property string $product_id
 * @property string|null $product_variant_id
 * @property string $lot_number
 * @property string|float $package_count
 * @property string|float $net_weight
 * @property string $weight_unit
 * @property string|null $notes
 * @property-read ColdStorageReceipt|null $receipt
 * @property-read Collection<int, ColdStorageReceiptAllocation> $allocations
 */
class ColdStorageReceiptItem extends Model implements Auditable
{
    use IsAuditedUuid;

    protected $fillable = [
        'receipt_id', 'product_id', 'product_variant_id', 'lot_number', 'package_count', 'net_weight', 'weight_unit', 'notes',
    ];

    protected $casts = [
        'package_count' => 'decimal:3',
        'net_weight' => 'decimal:3',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(ColdStorageReceipt::class, 'receipt_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return HasMany<ColdStorageReceiptAllocation, ColdStorageReceiptItem>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ColdStorageReceiptAllocation::class, 'receipt_item_id');
    }

    /**
     * @return HasMany<ColdStorageMovement, ColdStorageReceiptItem>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(ColdStorageMovement::class, 'receipt_item_id');
    }
}

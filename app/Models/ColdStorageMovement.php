<?php

namespace App\Models;

use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageMovement extends Model implements Auditable
{
    use IsAuditedUuid;

    protected $fillable = [
        'merchant_id', 'business_id', 'branch_id', 'customer_id', 'product_id', 'product_variant_id',
        'receipt_id', 'receipt_item_id', 'lot_number', 'chamber_id', 'location_id', 'movement_type',
        'package_delta', 'weight_delta', 'weight_unit', 'capacity_delta', 'capacity_unit',
        'reference_type', 'reference_id', 'reverses_movement_id', 'reason', 'occurred_on', 'created_by',
    ];

    protected $casts = [
        'package_delta' => 'decimal:3',
        'weight_delta' => 'decimal:3',
        'capacity_delta' => 'decimal:3',
        'occurred_on' => 'date',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

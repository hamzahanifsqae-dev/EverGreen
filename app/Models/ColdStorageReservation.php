<?php

namespace App\Models;

use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageReservation extends Model implements Auditable
{
    use IsAuditedUuid;
    use SoftDeletes;

    protected $fillable = [
        'merchant_id', 'business_id', 'branch_id', 'customer_id', 'chamber_id', 'reservation_no',
        'reserved_from', 'reserved_until', 'expected_packages', 'expected_weight', 'weight_unit',
        'expected_capacity', 'capacity_unit', 'status', 'notes', 'receipt_id',
        'confirmed_at', 'confirmed_by', 'fulfilled_at', 'fulfilled_by',
        'cancelled_at', 'cancelled_by', 'cancellation_reason', 'created_by',
    ];

    protected $casts = [
        'reserved_from' => 'date',
        'reserved_until' => 'date',
        'expected_packages' => 'decimal:3',
        'expected_weight' => 'decimal:3',
        'expected_capacity' => 'decimal:3',
        'confirmed_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(ColdStorageChamber::class, 'chamber_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(ColdStorageReceipt::class, 'receipt_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isFulfilled(): bool
    {
        return $this->status === 'fulfilled';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}

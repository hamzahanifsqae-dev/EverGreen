<?php

namespace App\Models;

use App\Models\Concerns\GuardsPostedColdStorageDocuments;
use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $merchant_id
 * @property string $business_id
 * @property string $branch_id
 * @property string $customer_id
 * @property string $receipt_no
 * @property Carbon|null $received_on
 * @property string|null $vehicle_number
 * @property string|null $notes
 * @property string $status
 * @property Carbon|null $posted_at
 * @property string|null $posted_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property string|null $created_by
 * @property-read Collection<int, ColdStorageReceiptItem> $items
 * @property-read Customer|null $customer
 * @property-read Branch|null $branch
 * @property-read Business|null $business
 */
class ColdStorageReceipt extends Model implements Auditable
{
    use GuardsPostedColdStorageDocuments;
    use IsAuditedUuid;
    use SoftDeletes;

    protected $fillable = [
        'merchant_id', 'business_id', 'branch_id', 'customer_id', 'receipt_no', 'received_on',
        'vehicle_number', 'notes', 'status', 'posted_at', 'posted_by', 'cancelled_at', 'cancelled_by',
        'cancellation_reason', 'created_by',
    ];

    protected $casts = [
        'received_on' => 'date',
        'posted_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ColdStorageReceiptItem, ColdStorageReceipt>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ColdStorageReceiptItem::class, 'receipt_id');
    }

    /**
     * @return HasMany<ColdStorageMovement, ColdStorageReceipt>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(ColdStorageMovement::class, 'receipt_id');
    }
}

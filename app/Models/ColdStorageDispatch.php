<?php

namespace App\Models;

use App\Models\Concerns\GuardsPostedColdStorageDocuments;
use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageDispatch extends Model implements Auditable
{
    use GuardsPostedColdStorageDocuments;
    use IsAuditedUuid;
    use SoftDeletes;

    protected $fillable = [
        'merchant_id', 'business_id', 'branch_id', 'customer_id', 'dispatch_no', 'dispatched_on',
        'recipient_name', 'vehicle_number', 'notes', 'status', 'posted_at', 'posted_by',
        'cancelled_at', 'cancelled_by', 'cancellation_reason', 'created_by',
    ];

    protected $casts = [
        'dispatched_on' => 'date',
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

    public function lines(): HasMany
    {
        return $this->hasMany(ColdStorageDispatchLine::class, 'dispatch_id');
    }
}

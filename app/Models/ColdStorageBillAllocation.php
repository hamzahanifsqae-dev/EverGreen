<?php

namespace App\Models;

use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageBillAllocation extends Model implements Auditable
{
    use IsAuditedUuid;

    protected $fillable = [
        'merchant_id', 'bill_id', 'payment_id', 'amount', 'allocated_on', 'created_by',
        'reversed_at', 'reversed_by', 'reversal_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocated_on' => 'date',
        'reversed_at' => 'datetime',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(ColdStorageBill::class, 'bill_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}

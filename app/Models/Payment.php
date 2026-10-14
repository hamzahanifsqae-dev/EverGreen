<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'merchant_id',
        'paymentable_type',
        'paymentable_id',
        'party_type',
        'party_id',
        'direction',
        'entry_type',
        'amount',
        'unallocated_amount',
        'payment_date',
        'method',
        'reference_no',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'unallocated_amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ColdStorageBillAllocation::class, 'payment_id');
    }

    public function isUnallocatedAdvance(): bool
    {
        return $this->entry_type === 'advance';
    }
}

<?php

namespace App\Models;

use App\Exceptions\ColdStorageException;
use App\Models\Concerns\IsAuditedUuid;
use App\Services\ColdStorage\Quantities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageLocation extends Model implements Auditable
{
    use IsAuditedUuid;
    use SoftDeletes;

    protected $fillable = [
        'chamber_id', 'merchant_id', 'business_id', 'branch_id', 'name', 'code', 'capacity_quantity', 'is_active',
    ];

    protected $casts = [
        'capacity_quantity' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $location): void {
            $chamber = $location->chamber()->first();

            if (! $chamber) {
                return;
            }

            $location->merchant_id = $chamber->merchant_id;
            $location->business_id = $chamber->business_id;
            $location->branch_id = $chamber->branch_id;

            if (! $location->exists || ! $location->isDirty('capacity_quantity') || $location->capacity_quantity === null) {
                return;
            }

            $occupied = Quantities::roundQuantity((float) ColdStorageMovement::query()
                ->where('location_id', $location->id)
                ->sum('capacity_delta'));

            if ($occupied - (float) $location->capacity_quantity > 0.0005) {
                throw ColdStorageException::make('Location capacity cannot be lower than the stock already stored there.');
            }
        });
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(ColdStorageChamber::class, 'chamber_id');
    }

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
}

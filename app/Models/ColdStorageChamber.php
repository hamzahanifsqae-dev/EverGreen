<?php

namespace App\Models;

use App\Exceptions\ColdStorageException;
use App\Models\Concerns\IsAuditedUuid;
use App\Services\ColdStorage\Quantities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageChamber extends Model implements Auditable
{
    use IsAuditedUuid;
    use SoftDeletes;

    protected $fillable = [
        'merchant_id', 'business_id', 'branch_id', 'name', 'code', 'capacity_quantity',
        'capacity_unit', 'min_temperature', 'max_temperature', 'temperature_unit', 'is_active', 'notes',
    ];

    protected $casts = [
        'capacity_quantity' => 'decimal:3',
        'min_temperature' => 'decimal:2',
        'max_temperature' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $chamber): void {
            if ($chamber->isDirty('capacity_unit') && $chamber->movements()->exists()) {
                throw ColdStorageException::make('The capacity unit cannot change after stock has been stored in this chamber.');
            }

            if (! $chamber->isDirty('capacity_quantity')) {
                return;
            }

            $occupied = Quantities::roundQuantity((float) $chamber->movements()->sum('capacity_delta'));

            if ($occupied - (float) $chamber->capacity_quantity > 0.0005) {
                throw ColdStorageException::make('Capacity cannot be lower than the stock already in this chamber.');
            }
        });
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

    public function locations(): HasMany
    {
        return $this->hasMany(ColdStorageLocation::class, 'chamber_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ColdStorageMovement::class, 'chamber_id');
    }

    public function temperatureReadings(): HasMany
    {
        return $this->hasMany(ColdStorageTemperatureReading::class, 'chamber_id');
    }
}

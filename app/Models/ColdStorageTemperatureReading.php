<?php

namespace App\Models;

use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageTemperatureReading extends Model implements Auditable
{
    use IsAuditedUuid;
    use SoftDeletes;

    protected $fillable = [
        'merchant_id', 'business_id', 'branch_id', 'chamber_id', 'recorded_at', 'temperature',
        'temperature_unit', 'min_temperature', 'max_temperature', 'is_out_of_range', 'source',
        'sensor_reference', 'notes', 'recorded_by', 'acknowledged_at', 'acknowledged_by',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'temperature' => 'decimal:2',
        'min_temperature' => 'decimal:2',
        'max_temperature' => 'decimal:2',
        'is_out_of_range' => 'boolean',
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

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(ColdStorageChamber::class, 'chamber_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}

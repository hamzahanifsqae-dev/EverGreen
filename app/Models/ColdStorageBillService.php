<?php

namespace App\Models;

use App\Models\Concerns\IsAuditedUuid;
use App\Services\ColdStorage\BillingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageBillService extends Model implements Auditable
{
    use IsAuditedUuid;

    protected $table = 'cold_storage_bill_services';

    protected $fillable = [
        'bill_id', 'name', 'basis', 'quantity', 'rate', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'rate' => 'decimal:4',
        'line_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $service): void {
            $service->line_total = app(BillingService::class)->serviceAmount(
                (string) $service->basis,
                (float) $service->quantity,
                (float) $service->rate,
            );
        });
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(ColdStorageBill::class, 'bill_id');
    }
}

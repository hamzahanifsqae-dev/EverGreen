<?php

namespace App\Models;

use App\Exceptions\ColdStorageException;
use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageRateCard extends Model implements Auditable
{
    use IsAuditedUuid;
    use SoftDeletes;

    protected $fillable = [
        'merchant_id', 'business_id', 'branch_id', 'customer_id', 'charge_basis', 'charge_period', 'rate',
        'currency', 'effective_from', 'effective_to', 'minimum_charge', 'bill_arrival_day', 'bill_departure_day',
        'rounding_mode', 'season_length_days', 'notes', 'created_by',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'minimum_charge' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'bill_arrival_day' => 'boolean',
        'bill_departure_day' => 'boolean',
        'season_length_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $card): void {
            $card->currency ??= (string) config('cold-storage.currency');

            if ($card->effective_to && $card->effective_from && $card->effective_to->lt($card->effective_from)) {
                throw ColdStorageException::make('The rate end date must be on or after the start date.');
            }

            $end = $card->effective_to?->toDateString() ?? '9999-12-31';
            $start = $card->effective_from?->toDateString();

            $overlap = self::query()
                ->where('merchant_id', $card->merchant_id)
                ->where('customer_id', $card->customer_id)
                ->where('charge_basis', $card->charge_basis)
                ->where('charge_period', $card->charge_period)
                ->when(
                    $card->branch_id,
                    fn (Builder $query) => $query->where('branch_id', $card->branch_id),
                    fn (Builder $query) => $query->whereNull('branch_id'),
                )
                ->when($card->exists, fn (Builder $query) => $query->whereKeyNot($card->id))
                ->where('effective_from', '<=', $end)
                ->where(function (Builder $query) use ($start): void {
                    $query->whereNull('effective_to')->orWhere('effective_to', '>=', $start);
                })
                ->exists();

            if ($overlap) {
                throw ColdStorageException::make('This rate overlaps another rate for the same customer, basis, and period.');
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

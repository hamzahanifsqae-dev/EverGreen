<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasUuids;
    use SoftDeletes;

    /** @var bool $incrementing */
    public $incrementing = false;

    /** @var string[] $fillable */
    protected $fillable = [
        'merchant_id', 'name', 'phone', 'email', 'city', 'reference', 'account_code', 'storage_billing_preference', 'country_id', 'city_id', 'postal_code', 'address','occupation',
    ];

    public const BILLING_PREFERENCE_PERIODIC = 'periodic';

    public const BILLING_PREFERENCE_ON_WITHDRAWAL = 'on_withdrawal';

    public static function storageBillingPreferenceOptions(): array
    {
        return [
            self::BILLING_PREFERENCE_PERIODIC => 'Bill periodically (while goods are still stored)',
            self::BILLING_PREFERENCE_ON_WITHDRAWAL => 'Bill on withdrawal only',
        ];
    }

    public function storageBillingPreferenceLabel(): ?string
    {
        return self::storageBillingPreferenceOptions()[$this->storage_billing_preference] ?? null;
    }

    /** @var string $keyType */
    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            if (! filled($customer->postal_code)) {
                $customer->postal_code = '54000';
            }
        });
    }

    /**
     * @return BelongsTo
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo
     */

    /**
     * @return BelongsTo
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(
            Business::class,
            'customer_businesses',
        )->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(
            Branch::class,
            'customer_branches',
        )
            ->withPivot('business_id')
            ->withTimestamps();
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'party');
    }

    public function cashFlows(): MorphMany
    {
        return $this->morphMany(CashFlow::class, 'party');
    }

    public function legacyReferences(): HasMany
    {
        return $this->hasMany(ColdStorageCustomerLegacyReference::class, 'customer_id');
    }

}

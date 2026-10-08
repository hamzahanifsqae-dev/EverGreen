<?php

namespace App\Models;

use App\Models\Concerns\IsAuditedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ColdStorageAlertEmailSetting extends Model implements Auditable
{
    use IsAuditedUuid;

    protected $fillable = [
        'merchant_id',
        'recipient_emails',
        'is_enabled',
        'include_temperature',
        'include_bills',
        'include_reservations',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'recipient_emails' => 'array',
            'is_enabled' => 'boolean',
            'include_temperature' => 'boolean',
            'include_bills' => 'boolean',
            'include_reservations' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return list<string>
     */
    public function normalizedRecipientEmails(): array
    {
        return collect($this->recipient_emails ?? [])
            ->map(fn (mixed $email): string => strtolower(trim((string) $email)))
            ->filter(fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}

<?php

namespace App\Models\Concerns;

use App\Exceptions\ColdStorageException;

trait GuardsPostedColdStorageDocuments
{
    public static function bootGuardsPostedColdStorageDocuments(): void
    {
        static::updating(function (self $model): void {
            if ($model->getOriginal('status') !== 'posted') {
                return;
            }

            $allowed = [
                'status',
                'cancelled_at',
                'cancelled_by',
                'cancellation_reason',
                'paid_amount',
                'due_amount',
                'updated_at',
            ];

            $illegal = array_diff(array_keys($model->getDirty()), $allowed);

            if ($illegal !== []) {
                throw ColdStorageException::make('Posted cold storage records cannot be edited. Cancel them with a reversal instead.');
            }

            if ($model->isDirty('status') && $model->status !== 'cancelled') {
                throw ColdStorageException::make('A posted cold storage record can only be cancelled.');
            }
        });
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }
}

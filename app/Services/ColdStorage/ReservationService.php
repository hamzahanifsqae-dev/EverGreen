<?php

namespace App\Services\ColdStorage;

use App\Exceptions\ColdStorageException;
use App\Models\ColdStorageReservation;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function confirm(ColdStorageReservation $reservation, ?string $actorId): ColdStorageReservation
    {
        return DB::transaction(function () use ($reservation, $actorId): ColdStorageReservation {
            $reservation = ColdStorageReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! $reservation->isDraft()) {
                throw ColdStorageException::make('Only a draft reservation can be confirmed.');
            }

            $reservation->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by' => $actorId,
            ]);

            return $reservation->refresh();
        });
    }

    public function fulfill(ColdStorageReservation $reservation, ?string $receiptId, ?string $actorId): ColdStorageReservation
    {
        return DB::transaction(function () use ($reservation, $receiptId, $actorId): ColdStorageReservation {
            $reservation = ColdStorageReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! $reservation->isConfirmed()) {
                throw ColdStorageException::make('Only a confirmed reservation can be marked fulfilled.');
            }

            $reservation->update([
                'status' => 'fulfilled',
                'receipt_id' => $receiptId,
                'fulfilled_at' => now(),
                'fulfilled_by' => $actorId,
            ]);

            return $reservation->refresh();
        });
    }

    public function cancel(ColdStorageReservation $reservation, ?string $actorId, string $reason): ColdStorageReservation
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ColdStorageException::make('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($reservation, $actorId, $reason): ColdStorageReservation {
            $reservation = ColdStorageReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($reservation->isFulfilled() || $reservation->isCancelled()) {
                throw ColdStorageException::make('This reservation can no longer be cancelled.');
            }

            $reservation->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $actorId,
                'cancellation_reason' => $reason,
            ]);

            return $reservation->refresh();
        });
    }
}

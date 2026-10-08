<?php

namespace App\Services\ColdStorage;

use App\Exceptions\ColdStorageException;
use App\Models\ColdStorageTransfer;
use Illuminate\Support\Facades\DB;

class TransferService
{
    public function __construct(
        private StockLedger $ledger,
        private DocumentReversal $reversal,
    ) {}

    public function post(ColdStorageTransfer $transfer, ?string $actorId): ColdStorageTransfer
    {
        return DB::transaction(function () use ($transfer, $actorId): ColdStorageTransfer {
            $transfer = ColdStorageTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if (! $transfer->isDraft()) {
                throw ColdStorageException::make('Only a draft transfer can be posted.');
            }

            $transfer->load('lines');

            if ($transfer->lines->isEmpty()) {
                throw ColdStorageException::make('Add at least one transfer line before posting.');
            }

            foreach ($transfer->lines as $line) {
                if ($line->from_chamber_id === $line->to_chamber_id && $line->from_location_id === $line->to_location_id) {
                    throw ColdStorageException::make('Choose a different destination location.');
                }

                $payload = [
                    'merchant_id' => $transfer->merchant_id,
                    'business_id' => $transfer->business_id,
                    'branch_id' => $transfer->branch_id,
                    'customer_id' => $transfer->customer_id,
                    'receipt_item_id' => $line->receipt_item_id,
                    'occurred_on' => $transfer->transferred_on->toDateString(),
                    'created_by' => $actorId,
                    'reference' => $transfer,
                    'reason' => $transfer->notes,
                ];

                $this->ledger->post([
                    ...$payload,
                    'chamber_id' => $line->from_chamber_id,
                    'location_id' => $line->from_location_id,
                    'movement_type' => 'transfer_out',
                    'package_delta' => ((float) $line->package_count) * -1,
                    'weight_delta' => ((float) $line->net_weight) * -1,
                ]);

                $this->ledger->post([
                    ...$payload,
                    'chamber_id' => $line->to_chamber_id,
                    'location_id' => $line->to_location_id,
                    'movement_type' => 'transfer_in',
                    'package_delta' => (float) $line->package_count,
                    'weight_delta' => (float) $line->net_weight,
                ]);
            }

            $transfer->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $actorId,
            ]);

            return $transfer->refresh();
        });
    }

    public function cancel(ColdStorageTransfer $transfer, ?string $actorId, string $reason): ColdStorageTransfer
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ColdStorageException::make('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($transfer, $actorId, $reason): ColdStorageTransfer {
            $transfer = ColdStorageTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if (! $transfer->isPosted()) {
                throw ColdStorageException::make('Only a posted transfer can be cancelled.');
            }

            $this->reversal->reverse($transfer, $actorId, $reason, now()->toDateString());

            $transfer->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $actorId,
                'cancellation_reason' => $reason,
            ]);

            return $transfer->refresh();
        });
    }
}

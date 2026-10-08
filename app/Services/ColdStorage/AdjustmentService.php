<?php

namespace App\Services\ColdStorage;

use App\Exceptions\ColdStorageException;
use App\Models\ColdStorageAdjustment;
use Illuminate\Support\Facades\DB;

class AdjustmentService
{
    public function __construct(
        private StockLedger $ledger,
        private DocumentReversal $reversal,
    ) {}

    public function post(ColdStorageAdjustment $adjustment, ?string $actorId): ColdStorageAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actorId): ColdStorageAdjustment {
            $adjustment = ColdStorageAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if (! $adjustment->isDraft()) {
                throw ColdStorageException::make('Only a draft adjustment can be posted.');
            }

            if (trim((string) $adjustment->reason) === '') {
                throw ColdStorageException::make('A reason is required for damage and stock adjustments.');
            }

            $adjustment->load('lines');

            if ($adjustment->lines->isEmpty()) {
                throw ColdStorageException::make('Add at least one adjustment line before posting.');
            }

            foreach ($adjustment->lines as $line) {
                $packages = Quantities::roundQuantity((float) $line->package_delta);
                $weight = Quantities::roundQuantity((float) $line->weight_delta);

                if ($packages === 0.0 && $weight === 0.0) {
                    throw ColdStorageException::make('An adjustment line cannot be zero.');
                }

                if ($adjustment->kind === 'damage' && ($packages > 0 || $weight > 0)) {
                    throw ColdStorageException::make('Damage must reduce stock.');
                }

                $this->ledger->post([
                    'merchant_id' => $adjustment->merchant_id,
                    'business_id' => $adjustment->business_id,
                    'branch_id' => $adjustment->branch_id,
                    'customer_id' => $adjustment->customer_id,
                    'receipt_item_id' => $line->receipt_item_id,
                    'chamber_id' => $line->chamber_id,
                    'location_id' => $line->location_id,
                    'movement_type' => $adjustment->kind === 'damage' ? 'damage' : 'adjustment',
                    'package_delta' => $packages,
                    'weight_delta' => $weight,
                    'reference' => $adjustment,
                    'reason' => $adjustment->reason,
                    'occurred_on' => $adjustment->adjusted_on->toDateString(),
                    'created_by' => $actorId,
                ]);
            }

            $adjustment->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $actorId,
            ]);

            return $adjustment->refresh();
        });
    }

    public function cancel(ColdStorageAdjustment $adjustment, ?string $actorId, string $reason): ColdStorageAdjustment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ColdStorageException::make('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($adjustment, $actorId, $reason): ColdStorageAdjustment {
            $adjustment = ColdStorageAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if (! $adjustment->isPosted()) {
                throw ColdStorageException::make('Only a posted adjustment can be cancelled.');
            }

            $this->reversal->reverse($adjustment, $actorId, $reason, now()->toDateString());

            $adjustment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $actorId,
                'cancellation_reason' => $reason,
            ]);

            return $adjustment->refresh();
        });
    }
}

<?php

namespace App\Services\ColdStorage;

use App\Exceptions\ColdStorageException;
use App\Models\ColdStorageDispatch;
use Illuminate\Support\Facades\DB;

class DispatchService
{
    public function __construct(
        private StockLedger $ledger,
        private DocumentReversal $reversal,
    ) {}

    public function post(ColdStorageDispatch $dispatch, ?string $actorId): ColdStorageDispatch
    {
        return DB::transaction(function () use ($dispatch, $actorId): ColdStorageDispatch {
            $dispatch = ColdStorageDispatch::query()->whereKey($dispatch->id)->lockForUpdate()->firstOrFail();

            if (! $dispatch->isDraft()) {
                throw ColdStorageException::make('Only a draft dispatch can be posted.');
            }

            $dispatch->load('lines');

            if ($dispatch->lines->isEmpty()) {
                throw ColdStorageException::make('Add at least one dispatch line before posting.');
            }

            if (trim((string) $dispatch->recipient_name) === '') {
                throw ColdStorageException::make('A recipient is required.');
            }

            foreach ($dispatch->lines as $line) {
                if ((float) $line->package_count <= 0 && (float) $line->net_weight <= 0) {
                    throw ColdStorageException::make('Each dispatch line needs a quantity or a weight.');
                }

                $this->ledger->post([
                    'merchant_id' => $dispatch->merchant_id,
                    'business_id' => $dispatch->business_id,
                    'branch_id' => $dispatch->branch_id,
                    'customer_id' => $dispatch->customer_id,
                    'receipt_item_id' => $line->receipt_item_id,
                    'chamber_id' => $line->chamber_id,
                    'location_id' => $line->location_id,
                    'movement_type' => 'dispatch',
                    'package_delta' => ((float) $line->package_count) * -1,
                    'weight_delta' => ((float) $line->net_weight) * -1,
                    'reference' => $dispatch,
                    'reason' => null,
                    'occurred_on' => $dispatch->dispatched_on->toDateString(),
                    'created_by' => $actorId,
                ]);
            }

            $dispatch->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $actorId,
            ]);

            return $dispatch->refresh();
        });
    }

    public function cancel(ColdStorageDispatch $dispatch, ?string $actorId, string $reason): ColdStorageDispatch
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ColdStorageException::make('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($dispatch, $actorId, $reason): ColdStorageDispatch {
            $dispatch = ColdStorageDispatch::query()->whereKey($dispatch->id)->lockForUpdate()->firstOrFail();

            if (! $dispatch->isPosted()) {
                throw ColdStorageException::make('Only a posted dispatch can be cancelled.');
            }

            $this->reversal->reverse($dispatch, $actorId, $reason, now()->toDateString());

            $dispatch->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $actorId,
                'cancellation_reason' => $reason,
            ]);

            return $dispatch->refresh();
        });
    }
}

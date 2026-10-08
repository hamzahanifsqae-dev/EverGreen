<?php

namespace App\Services\ColdStorage;

use App\Models\ColdStorageMovement;
use Illuminate\Database\Eloquent\Model;

class DocumentReversal
{
    public function __construct(private StockLedger $ledger) {}

    public function reverse(Model $document, ?string $actorId, string $reason, string $occurredOn): void
    {
        $movements = ColdStorageMovement::query()
            ->where('reference_type', $document->getMorphClass())
            ->where('reference_id', $document->getKey())
            ->whereNull('reverses_movement_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($movements as $movement) {
            $alreadyReversed = ColdStorageMovement::query()
                ->where('reverses_movement_id', $movement->id)
                ->exists();

            if ($alreadyReversed) {
                continue;
            }

            $this->ledger->reverse($movement, $document, $actorId, $reason, $occurredOn);
        }
    }
}

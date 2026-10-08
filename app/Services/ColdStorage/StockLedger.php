<?php

namespace App\Services\ColdStorage;

use App\Exceptions\ColdStorageException;
use App\Models\ColdStorageChamber;
use App\Models\ColdStorageLocation;
use App\Models\ColdStorageMovement;
use App\Models\ColdStorageReceiptItem;
use App\Support\ColdStorageAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockLedger
{
    /**
     * @param  array{
     *     merchant_id: string,
     *     business_id: string,
     *     branch_id: string,
     *     customer_id: string,
     *     receipt_item_id: string,
     *     chamber_id: string,
     *     location_id: ?string,
     *     movement_type: string,
     *     package_delta: float|int|string,
     *     weight_delta: float|int|string,
     *     reference: Model,
     *     reason?: ?string,
     *     occurred_on: string,
     *     created_by?: ?string,
     *     reverses_movement_id?: ?string
     * }  $data
     */
    public function post(array $data): ColdStorageMovement
    {
        return DB::transaction(function () use ($data): ColdStorageMovement {
            $item = ColdStorageReceiptItem::query()
                ->with('receipt')
                ->lockForUpdate()
                ->findOrFail($data['receipt_item_id']);

            $receipt = $item->receipt;
            $chamber = ColdStorageChamber::query()->lockForUpdate()->findOrFail($data['chamber_id']);
            $location = null;

            if (filled($data['location_id'] ?? null)) {
                $location = ColdStorageLocation::query()->lockForUpdate()->findOrFail($data['location_id']);
            }

            $this->assertOwnership($data, $item, $chamber, $location);
            ColdStorageAccess::assertActorCanUseBranch(
                $data['created_by'] ?? null,
                (string) $data['branch_id'],
                (string) $data['business_id'],
            );

            $this->lockBalanceRows($data);

            if (filled($data['reverses_movement_id'] ?? null)) {
                $this->assertReversalIsOpen((string) $data['reverses_movement_id']);
            }

            $packages = Quantities::roundQuantity((float) $data['package_delta']);
            $weight = Quantities::roundQuantity((float) $data['weight_delta']);
            $balance = $this->balance($data);

            if (Quantities::isNegative($balance['packages'] + $packages) || Quantities::isNegative($balance['weight'] + $weight)) {
                throw ColdStorageException::make('This withdrawal is above the available customer stock for the selected lot and location.');
            }

            $capacityDelta = Quantities::capacityDelta(
                (string) $chamber->capacity_unit,
                $packages,
                $weight,
                (string) $item->weight_unit,
            );

            $movement = ColdStorageMovement::query()->create([
                'merchant_id' => $data['merchant_id'],
                'business_id' => $data['business_id'],
                'branch_id' => $data['branch_id'],
                'customer_id' => $data['customer_id'],
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'receipt_id' => $receipt->id,
                'receipt_item_id' => $item->id,
                'lot_number' => $item->lot_number,
                'chamber_id' => $chamber->id,
                'location_id' => $location?->id,
                'movement_type' => $data['movement_type'],
                'package_delta' => $packages,
                'weight_delta' => $weight,
                'weight_unit' => $item->weight_unit,
                'capacity_delta' => $capacityDelta,
                'capacity_unit' => $chamber->capacity_unit,
                'reference_type' => $data['reference']->getMorphClass(),
                'reference_id' => $data['reference']->getKey(),
                'reverses_movement_id' => $data['reverses_movement_id'] ?? null,
                'reason' => $data['reason'] ?? null,
                'occurred_on' => $data['occurred_on'],
                'created_by' => $data['created_by'] ?? null,
            ]);

            $this->assertCapacity($chamber, $location);

            return $movement;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{packages: float, weight: float}
     */
    public function balance(array $data): array
    {
        $query = $this->balanceQuery($data);

        return [
            'packages' => Quantities::roundQuantity((float) $query->sum('package_delta')),
            'weight' => Quantities::roundQuantity((float) (clone $this->balanceQuery($data))->sum('weight_delta')),
        ];
    }

    public function reverse(ColdStorageMovement $movement, Model $reference, ?string $actorId, string $reason, string $occurredOn): ColdStorageMovement
    {
        if (ColdStorageMovement::query()->where('reverses_movement_id', $movement->id)->exists()) {
            throw ColdStorageException::make('This movement has already been reversed.');
        }

        return $this->post([
            'merchant_id' => $movement->merchant_id,
            'business_id' => $movement->business_id,
            'branch_id' => $movement->branch_id,
            'customer_id' => $movement->customer_id,
            'receipt_item_id' => $movement->receipt_item_id,
            'chamber_id' => $movement->chamber_id,
            'location_id' => $movement->location_id,
            'movement_type' => 'reversal',
            'package_delta' => ((float) $movement->package_delta) * -1,
            'weight_delta' => ((float) $movement->weight_delta) * -1,
            'reference' => $reference,
            'reason' => $reason,
            'occurred_on' => $occurredOn,
            'created_by' => $actorId,
            'reverses_movement_id' => $movement->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertOwnership(array $data, ColdStorageReceiptItem $item, ColdStorageChamber $chamber, ?ColdStorageLocation $location): void
    {
        $receipt = $item->receipt;

        if ($receipt->merchant_id !== $data['merchant_id'] || $receipt->customer_id !== $data['customer_id']) {
            throw ColdStorageException::make('Stock cannot be moved for a different customer.');
        }

        if ($receipt->branch_id !== $data['branch_id'] || $receipt->business_id !== $data['business_id']) {
            throw ColdStorageException::make('Stock cannot be moved outside the receipt branch.');
        }

        if ($receipt->status === 'cancelled') {
            throw ColdStorageException::make('This goods receipt has been cancelled.');
        }

        if (! in_array($data['movement_type'], ['receive', 'reversal'], true) && $receipt->status !== 'posted') {
            throw ColdStorageException::make('Goods can only leave a posted receipt.');
        }

        if ($chamber->merchant_id !== $data['merchant_id'] || $chamber->branch_id !== $data['branch_id'] || $chamber->business_id !== $data['business_id']) {
            throw ColdStorageException::make('The chamber belongs to a different business or branch.');
        }

        if (! $chamber->is_active) {
            throw ColdStorageException::make('The selected chamber is inactive.');
        }

        if ($location && ($location->chamber_id !== $chamber->id || $location->branch_id !== $chamber->branch_id)) {
            throw ColdStorageException::make('The location does not belong to the selected chamber.');
        }

        if ($location && ! $location->is_active) {
            throw ColdStorageException::make('The selected location is inactive.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function lockBalanceRows(array $data): void
    {
        $this->balanceQuery($data)->lockForUpdate()->get(['id']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function balanceQuery(array $data): Builder
    {
        return ColdStorageMovement::query()
            ->where('merchant_id', $data['merchant_id'])
            ->where('branch_id', $data['branch_id'])
            ->where('customer_id', $data['customer_id'])
            ->where('receipt_item_id', $data['receipt_item_id'])
            ->where('chamber_id', $data['chamber_id'])
            ->when(
                filled($data['location_id'] ?? null),
                fn (Builder $query) => $query->where('location_id', $data['location_id']),
                fn (Builder $query) => $query->whereNull('location_id'),
            );
    }

    private function assertReversalIsOpen(string $movementId): void
    {
        $exists = ColdStorageMovement::query()
            ->whereKey($movementId)
            ->lockForUpdate()
            ->exists();

        if (! $exists) {
            throw ColdStorageException::make('The movement being reversed could not be found.');
        }

        $alreadyReversed = ColdStorageMovement::query()
            ->where('reverses_movement_id', $movementId)
            ->lockForUpdate()
            ->exists();

        if ($alreadyReversed) {
            throw ColdStorageException::make('This movement has already been reversed.');
        }
    }

    private function assertCapacity(ColdStorageChamber $chamber, ?ColdStorageLocation $location): void
    {
        $occupied = Quantities::roundQuantity((float) ColdStorageMovement::query()
            ->where('chamber_id', $chamber->id)
            ->sum('capacity_delta'));

        if ($occupied - (float) $chamber->capacity_quantity > 0.0005) {
            throw ColdStorageException::make('This receipt exceeds the available capacity of '.$chamber->name.'.');
        }

        if ($location?->capacity_quantity === null) {
            return;
        }

        $locationOccupied = Quantities::roundQuantity((float) ColdStorageMovement::query()
            ->where('location_id', $location->id)
            ->sum('capacity_delta'));

        if ($locationOccupied - (float) $location->capacity_quantity > 0.0005) {
            throw ColdStorageException::make('This receipt exceeds the available capacity of location '.$location->name.'.');
        }
    }
}

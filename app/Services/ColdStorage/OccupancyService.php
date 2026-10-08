<?php

namespace App\Services\ColdStorage;

use App\Models\ColdStorageChamber;
use App\Models\ColdStorageMovement;

class OccupancyService
{
    /**
     * @return array{occupied: float, available: float, capacity: float, unit: string}
     */
    public function forChamber(ColdStorageChamber $chamber): array
    {
        $occupied = Quantities::roundQuantity((float) ColdStorageMovement::query()
            ->where('chamber_id', $chamber->id)
            ->sum('capacity_delta'));

        $capacity = (float) $chamber->capacity_quantity;

        return [
            'occupied' => $occupied,
            'available' => Quantities::roundQuantity($capacity - $occupied),
            'capacity' => $capacity,
            'unit' => (string) $chamber->capacity_unit,
        ];
    }
}

<?php

namespace App\Services\ColdStorage;

use App\Models\ColdStorageChamber;
use App\Models\ColdStorageTemperatureReading;
use App\Support\ColdStorageAccess;
use Carbon\CarbonInterface;

class TemperatureService
{
    public function record(
        ColdStorageChamber $chamber,
        float $temperature,
        CarbonInterface $recordedAt,
        ?string $actorId,
        string $source = 'manual',
        ?string $sensorReference = null,
        ?string $notes = null,
    ): ColdStorageTemperatureReading {
        ColdStorageAccess::assertActorCanUseBranch($actorId, $chamber->branch_id, $chamber->business_id);

        $minimum = $chamber->min_temperature !== null ? (float) $chamber->min_temperature : null;
        $maximum = $chamber->max_temperature !== null ? (float) $chamber->max_temperature : null;
        $outOfRange = ($minimum !== null && $temperature < $minimum) || ($maximum !== null && $temperature > $maximum);

        return ColdStorageTemperatureReading::query()->create([
            'merchant_id' => $chamber->merchant_id,
            'business_id' => $chamber->business_id,
            'branch_id' => $chamber->branch_id,
            'chamber_id' => $chamber->id,
            'recorded_at' => $recordedAt,
            'temperature' => $temperature,
            'temperature_unit' => $chamber->temperature_unit ?: 'C',
            'min_temperature' => $minimum,
            'max_temperature' => $maximum,
            'is_out_of_range' => $outOfRange,
            'source' => $source,
            'sensor_reference' => $sensorReference,
            'notes' => $notes,
            'recorded_by' => $actorId,
        ]);
    }
}

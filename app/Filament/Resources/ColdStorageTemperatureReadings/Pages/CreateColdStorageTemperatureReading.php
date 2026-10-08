<?php

namespace App\Filament\Resources\ColdStorageTemperatureReadings\Pages;

use App\Filament\Resources\ColdStorageTemperatureReadings\ColdStorageTemperatureReadingResource;
use App\Models\ColdStorageChamber;
use App\Services\ColdStorage\TemperatureService;
use App\Support\ColdStorageAccess;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CreateColdStorageTemperatureReading extends CreateRecord
{
    protected static string $resource = ColdStorageTemperatureReadingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $chamber = ColdStorageAccess::scope(ColdStorageChamber::query())->findOrFail($data['chamber_id']);

        return app(TemperatureService::class)->record(
            $chamber,
            (float) $data['temperature'],
            Carbon::parse($data['recorded_at']),
            ColdStorageAccess::actorId(),
            (string) ($data['source'] ?? 'manual'),
            $data['sensor_reference'] ?? null,
            $data['notes'] ?? null,
        );
    }
}

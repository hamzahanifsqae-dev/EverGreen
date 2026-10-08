<?php

namespace App\Enums;

enum ColdStorageTemperatureSource: string
{
    case Manual = 'manual';
    case Sensor = 'sensor';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Sensor => 'Sensor',
        };
    }
}

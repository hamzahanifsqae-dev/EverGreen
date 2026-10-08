<?php

namespace App\Services\ColdStorage;

use App\Exceptions\ColdStorageException;

class Quantities
{
    public static function roundQuantity(float $value): float
    {
        return round($value, 3);
    }

    public static function isNegative(float $value): bool
    {
        return self::roundQuantity($value) < 0;
    }

    public static function exceeds(float $requested, float $available): bool
    {
        return self::roundQuantity($requested) - self::roundQuantity($available) > 0.0005;
    }

    public static function kilograms(float $weight, string $unit): float
    {
        return match ($unit) {
            'tonne' => $weight * 1000,
            'kilogram' => $weight,
            default => throw ColdStorageException::make("Weight unit [{$unit}] cannot be converted."),
        };
    }

    public static function capacityDelta(string $capacityUnit, float $packages, float $weight, string $weightUnit): float
    {
        return match ($capacityUnit) {
            'bag', 'pallet' => self::roundQuantity($packages),
            'kilogram' => self::roundQuantity(self::kilograms($weight, $weightUnit)),
            'tonne' => self::roundQuantity(self::kilograms($weight, $weightUnit) / 1000),
            default => throw ColdStorageException::make("Capacity unit [{$capacityUnit}] is not supported."),
        };
    }

    public static function chargeQuantity(string $basis, float $packages, float $weight, string $weightUnit): float
    {
        return self::capacityDelta($basis, $packages, $weight, $weightUnit);
    }

    public static function money(float $amount, string $mode): float
    {
        $scaled = $amount * 100;

        $rounded = match ($mode) {
            'up' => ceil($scaled - 0.0000001),
            'down' => floor($scaled + 0.0000001),
            default => round($scaled),
        };

        return round($rounded / 100, 2);
    }
}

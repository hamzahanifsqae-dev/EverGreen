<?php

namespace App\Enums;

enum ColdStorageChargePeriod: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';
    case Seasonal = 'seasonal';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Monthly => 'Monthly',
            self::Seasonal => 'Seasonal',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}

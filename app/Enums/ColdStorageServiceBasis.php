<?php

namespace App\Enums;

enum ColdStorageServiceBasis: string
{
    case Flat = 'flat';
    case PerBag = 'per_bag';
    case PerKilogram = 'per_kilogram';
    case PerTonne = 'per_tonne';
    case PerPallet = 'per_pallet';

    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Flat amount',
            self::PerBag => 'Per bag',
            self::PerKilogram => 'Per kilogram',
            self::PerTonne => 'Per tonne',
            self::PerPallet => 'Per pallet',
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

<?php

namespace App\Enums;

enum ColdStorageChargeBasis: string
{
    case Bag = 'bag';
    case Kilogram = 'kilogram';
    case Tonne = 'tonne';
    case Pallet = 'pallet';

    public function label(): string
    {
        return match ($this) {
            self::Bag => 'Per bag',
            self::Kilogram => 'Per kilogram',
            self::Tonne => 'Per tonne',
            self::Pallet => 'Per pallet',
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

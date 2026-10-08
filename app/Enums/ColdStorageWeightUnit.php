<?php

namespace App\Enums;

enum ColdStorageWeightUnit: string
{
    case Kilogram = 'kilogram';
    case Tonne = 'tonne';

    public function label(): string
    {
        return match ($this) {
            self::Kilogram => 'Kilograms',
            self::Tonne => 'Tonnes',
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

<?php

namespace App\Enums;

enum ColdStorageAdjustmentKind: string
{
    case Damage = 'damage';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Damage => 'Damage',
            self::Adjustment => 'Adjustment',
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

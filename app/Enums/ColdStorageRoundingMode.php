<?php

namespace App\Enums;

enum ColdStorageRoundingMode: string
{
    case Nearest = 'nearest';
    case Up = 'up';
    case Down = 'down';

    public function label(): string
    {
        return match ($this) {
            self::Nearest => 'Nearest',
            self::Up => 'Round up',
            self::Down => 'Round down',
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

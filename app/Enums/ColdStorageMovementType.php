<?php

namespace App\Enums;

enum ColdStorageMovementType: string
{
    case Receive = 'receive';
    case Dispatch = 'dispatch';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Damage = 'damage';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Receive => 'Receiving',
            self::Dispatch => 'Return',
            self::TransferIn => 'Transfer in',
            self::TransferOut => 'Transfer out',
            self::Damage => 'Damage',
            self::Adjustment => 'Adjustment',
            self::Reversal => 'Reversal',
        };
    }
}

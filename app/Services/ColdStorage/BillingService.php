<?php

namespace App\Services\ColdStorage;

use App\Exceptions\ColdStorageException;
use App\Models\Branch;
use App\Models\ColdStorageBill;
use App\Models\ColdStorageMovement;
use App\Models\ColdStorageRateCard;
use App\Models\ColdStorageReceiptItem;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BillingService
{
    /**
     * @param  array{
     *     branch_id: string,
     *     customer_id: string,
     *     period_start: string,
     *     period_end: string,
     *     charge_basis?: string,
     *     charge_period?: string,
     *     notes?: ?string,
     *     bill_no?: string
     * }  $data
     */
    public function createDraftFromStock(array $data, ?string $actorId): ColdStorageBill
    {
        $branch = Branch::query()->whereKey($data['branch_id'])->firstOrFail();

        $bill = ColdStorageBill::query()->create([
            'merchant_id' => $branch->merchant_id,
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
            'customer_id' => $data['customer_id'],
            'bill_no' => $data['bill_no'] ?? ('SB-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -6))),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'charge_basis' => $data['charge_basis'] ?? 'bag',
            'charge_period' => $data['charge_period'] ?? 'daily',
            'status' => 'draft',
            'storage_total' => 0,
            'service_total' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'due_amount' => 0,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actorId,
        ]);

        return $bill->refresh();
    }

    /**
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     storage_total: float,
     *     service_total: float,
     *     total: float
     * }
     */
    public function preview(ColdStorageBill $bill): array
    {
        $bill->loadMissing('services');
        $this->assertHeader($bill);

        $lines = $this->storageLines($bill);
        $storageTotal = round(collect($lines)->sum('line_total'), 2);
        $serviceTotal = round($bill->services->sum(fn ($service): float => $this->serviceAmount(
            (string) $service->basis,
            (float) $service->quantity,
            (float) $service->rate,
        )), 2);

        return [
            'lines' => $lines,
            'storage_total' => $storageTotal,
            'service_total' => $serviceTotal,
            'total' => round($storageTotal + $serviceTotal, 2),
        ];
    }

    public function post(ColdStorageBill $bill, ?string $actorId): ColdStorageBill
    {
        return DB::transaction(function () use ($bill, $actorId): ColdStorageBill {
            $bill = ColdStorageBill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if (! $bill->isDraft()) {
                throw ColdStorageException::make('Only a draft storage bill can be posted.');
            }

            $preview = $this->preview($bill);

            if ($preview['lines'] === [] && $preview['service_total'] <= 0) {
                throw ColdStorageException::make('There is no storage quantity or service charge to bill for this period.');
            }

            $bill->lines()->delete();

            foreach ($preview['lines'] as $line) {
                $bill->lines()->create($line);
            }

            foreach ($bill->services as $service) {
                $service->update([
                    'line_total' => $this->serviceAmount((string) $service->basis, (float) $service->quantity, (float) $service->rate),
                ]);
            }

            $bill->update([
                'status' => 'posted',
                'storage_total' => $preview['storage_total'],
                'service_total' => $preview['service_total'],
                'total_amount' => $preview['total'],
                'paid_amount' => 0,
                'due_amount' => $preview['total'],
                'posted_at' => now(),
                'posted_by' => $actorId,
            ]);

            return $bill->refresh();
        });
    }

    public function cancel(ColdStorageBill $bill, ?string $actorId, string $reason): ColdStorageBill
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ColdStorageException::make('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($bill, $actorId, $reason): ColdStorageBill {
            $bill = ColdStorageBill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if (! $bill->isPosted()) {
                throw ColdStorageException::make('Only a posted storage bill can be cancelled.');
            }

            if ((float) $bill->paid_amount > 0) {
                throw ColdStorageException::make('Refund recorded payments before cancelling this bill.');
            }

            $bill->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $actorId,
                'cancellation_reason' => $reason,
                'due_amount' => 0,
            ]);

            return $bill->refresh();
        });
    }

    public function recordPayment(ColdStorageBill $bill, float $amount, string $date, ?string $method, ?string $actorId): Payment
    {
        return DB::transaction(function () use ($bill, $amount, $date, $method, $actorId): Payment {
            $bill = ColdStorageBill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();
            $amount = round($amount, 2);

            if (! $bill->isPosted()) {
                throw ColdStorageException::make('Payments can only be recorded against a posted bill.');
            }

            if ($amount <= 0) {
                throw ColdStorageException::make('Payment amount must be greater than zero.');
            }

            if ($amount - (float) $bill->due_amount > 0.009) {
                throw ColdStorageException::make('Payment cannot exceed the outstanding balance.');
            }

            $account = $this->cashAccountForMethod($method);

            $payment = $bill->payments()->create([
                'merchant_id' => $bill->merchant_id,
                'party_type' => Customer::class,
                'party_id' => $bill->customer_id,
                'direction' => 'in',
                'entry_type' => 'payment',
                'amount' => $amount,
                'payment_date' => $date,
                'method' => $account,
                'reference_no' => $bill->bill_no,
                'notes' => 'Cold storage bill '.$bill->bill_no,
                'created_by' => $actorId,
            ]);

            $paid = round((float) $bill->payments()->sum('amount'), 2);

            $bill->update([
                'paid_amount' => $paid,
                'due_amount' => round(max(0, (float) $bill->total_amount - $paid), 2),
            ]);

            $merchant = Merchant::query()->whereKey($bill->merchant_id)->lockForUpdate()->firstOrFail();
            $merchant->update([
                $account => round((float) ($merchant->{$account} ?? 0) + $amount, 2),
            ]);

            return $payment;
        });
    }

    /**
     * Map a recorded payment method to the merchant cash account column.
     */
    public function cashAccountForMethod(?string $method): string
    {
        return match ($method) {
            'cash', 'cash_in_hand' => 'cash_in_hand',
            'bank', 'bank_transfer', 'cash_in_bank' => 'cash_in_bank',
            default => throw ColdStorageException::make('Select Cash in hand or Bank for this payment.'),
        };
    }

    public function serviceAmount(string $basis, float $quantity, float $rate): float
    {
        if ($basis === 'flat') {
            return round($rate, 2);
        }

        return round($quantity * $rate, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function storageLines(ColdStorageBill $bill): array
    {
        $items = ColdStorageReceiptItem::query()
            ->whereHas('receipt', function ($query) use ($bill): void {
                $query->where('merchant_id', $bill->merchant_id)
                    ->where('branch_id', $bill->branch_id)
                    ->where('customer_id', $bill->customer_id)
                    ->where('status', 'posted');
            })
            ->get();

        $lines = [];

        foreach ($items as $item) {
            $lines = [...$lines, ...$this->linesForItem($bill, $item)];
        }

        return $lines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function linesForItem(ColdStorageBill $bill, ColdStorageReceiptItem $item): array
    {
        $movements = ColdStorageMovement::query()
            ->where('receipt_item_id', $item->id)
            ->where('branch_id', $bill->branch_id)
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        if ($movements->isEmpty()) {
            return [];
        }

        $start = $bill->period_start->copy()->startOfDay();
        $end = $bill->period_end->copy()->startOfDay();
        $segments = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $quantity = $this->billableQuantity($bill, $item, $movements, $day->copy());

            if ($quantity <= 0) {
                continue;
            }

            $card = $this->rateCard($bill, $day->toDateString());
            $key = $card->id;

            if (! isset($segments[$key])) {
                $segments[$key] = [
                    'card' => $card,
                    'quantity_days' => 0.0,
                    'billable_days' => 0,
                    'first' => $day->toDateString(),
                    'last' => $day->toDateString(),
                    'months' => [],
                ];
            }

            $segments[$key]['quantity_days'] += $quantity;
            $segments[$key]['billable_days']++;
            $segments[$key]['last'] = $day->toDateString();
            $monthKey = $day->format('Y-m');
            $segments[$key]['months'][$monthKey]['quantity_days'] = ($segments[$key]['months'][$monthKey]['quantity_days'] ?? 0) + $quantity;
            $segments[$key]['months'][$monthKey]['length'] = $day->daysInMonth;
        }

        $lines = [];

        foreach ($segments as $segment) {
            /** @var ColdStorageRateCard $card */
            $card = $segment['card'];
            $amount = $this->segmentAmount($card, $segment['quantity_days'], $segment['months']);

            if ($amount > 0 && $amount < (float) $card->minimum_charge) {
                $amount = (float) $card->minimum_charge;
            }

            $amount = Quantities::money($amount, (string) $card->rounding_mode);

            $lines[] = [
                'receipt_item_id' => $item->id,
                'rate_card_id' => $card->id,
                'lot_number' => $item->lot_number,
                'period_start' => $segment['first'],
                'period_end' => $segment['last'],
                'charge_basis' => $card->charge_basis,
                'charge_period' => $card->charge_period,
                'quantity_days' => Quantities::roundQuantity($segment['quantity_days']),
                'billable_days' => $segment['billable_days'],
                'rate' => $card->rate,
                'line_total' => $amount,
                'bill_arrival_day' => $card->bill_arrival_day,
                'bill_departure_day' => $card->bill_departure_day,
                'rounding_mode' => $card->rounding_mode,
                'season_length_days' => $card->season_length_days,
                'minimum_charge' => $card->minimum_charge,
                'calculation_note' => $this->note($card, $segment['quantity_days'], $segment['billable_days']),
            ];
        }

        return $lines;
    }

    /**
     * @param  Collection<int, ColdStorageMovement>  $movements
     */
    private function billableQuantity(ColdStorageBill $bill, ColdStorageReceiptItem $item, Collection $movements, Carbon $day): float
    {
        $card = null;

        try {
            $card = $this->rateCard($bill, $day->toDateString());
        } catch (ColdStorageException) {
            $through = $movements->filter(fn (ColdStorageMovement $movement): bool => $movement->occurred_on->toDateString() <= $day->toDateString());

            if ($this->balance($through, $item)['packages'] <= 0 && $this->balance($through, $item)['weight'] <= 0) {
                return 0.0;
            }

            throw ColdStorageException::make('No storage rate covers '.$day->toDateString().'.');
        }

        $through = $movements->filter(fn (ColdStorageMovement $movement): bool => $movement->occurred_on->toDateString() <= $day->toDateString());
        $today = $movements->filter(fn (ColdStorageMovement $movement): bool => $movement->occurred_on->toDateString() === $day->toDateString());
        $balance = $this->balance($through, $item);
        $packages = $balance['packages'];
        $weight = $balance['weight'];

        $firstReceipt = $movements->first(fn (ColdStorageMovement $movement): bool => $movement->movement_type === 'receive');

        if ($firstReceipt && ! $card->bill_arrival_day && $firstReceipt->occurred_on->toDateString() === $day->toDateString()) {
            $received = $this->signed($today, 'receive', false);
            $packages -= $received['packages'];
            $weight -= $received['weight'];
        }

        if ($card->bill_departure_day) {
            $left = $this->signed($today, null, true);
            $packages += $left['packages'];
            $weight += $left['weight'];
        }

        $packages = max(0, $packages);
        $weight = max(0, $weight);

        return Quantities::chargeQuantity((string) $card->charge_basis, $packages, $weight, (string) $item->weight_unit);
    }

    /**
     * @param  Collection<int, ColdStorageMovement>  $movements
     * @return array{packages: float, weight: float}
     */
    private function balance(Collection $movements, ColdStorageReceiptItem $item): array
    {
        return [
            'packages' => Quantities::roundQuantity((float) $movements->sum('package_delta')),
            'weight' => Quantities::roundQuantity((float) $movements->sum('weight_delta')),
        ];
    }

    /**
     * @param  Collection<int, ColdStorageMovement>  $movements
     * @return array{packages: float, weight: float}
     */
    private function signed(Collection $movements, ?string $type, bool $leavingOnly): array
    {
        $filtered = $movements->filter(function (ColdStorageMovement $movement) use ($type, $leavingOnly): bool {
            if ($type !== null) {
                return $movement->movement_type === $type;
            }

            if (! in_array($movement->movement_type, ['dispatch', 'damage', 'adjustment', 'reversal'], true)) {
                return false;
            }

            return $leavingOnly && ((float) $movement->package_delta < 0 || (float) $movement->weight_delta < 0);
        });

        return [
            'packages' => abs((float) $filtered->sum('package_delta')),
            'weight' => abs((float) $filtered->sum('weight_delta')),
        ];
    }

    private function rateCard(ColdStorageBill $bill, string $date): ColdStorageRateCard
    {
        $cards = ColdStorageRateCard::query()
            ->where('merchant_id', $bill->merchant_id)
            ->where('customer_id', $bill->customer_id)
            ->where('charge_basis', $bill->charge_basis)
            ->where('charge_period', $bill->charge_period)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->where(function ($query) use ($bill): void {
                $query->where('branch_id', $bill->branch_id)->orWhereNull('branch_id');
            })
            ->get()
            ->sortByDesc(fn (ColdStorageRateCard $card): int => $card->branch_id === $bill->branch_id ? 1 : 0)
            ->values();

        $card = $cards->first();

        if (! $card) {
            throw ColdStorageException::make('No storage rate covers '.$date.'.');
        }

        return $card;
    }

    /**
     * @param  array<string, array{quantity_days: float, length: int}>  $months
     */
    private function segmentAmount(ColdStorageRateCard $card, float $quantityDays, array $months): float
    {
        $rate = (float) $card->rate;

        return match ($card->charge_period) {
            'monthly' => collect($months)->sum(fn (array $month): float => ($month['quantity_days'] / max(1, $month['length'])) * $rate),
            'seasonal' => ($quantityDays / max(1, (int) $card->season_length_days)) * $rate,
            default => $quantityDays * $rate,
        };
    }

    private function note(ColdStorageRateCard $card, float $quantityDays, int $days): string
    {
        $quantity = number_format($quantityDays, 3);
        $rate = number_format((float) $card->rate, 2);

        return "{$quantity} {$card->charge_basis}-days over {$days} billable days at {$rate} per {$card->charge_period} {$card->charge_basis}. Arrival day ".($card->bill_arrival_day ? 'is' : 'is not').' billed. Departure day '.($card->bill_departure_day ? 'is' : 'is not').' billed.';
    }

    private function assertHeader(ColdStorageBill $bill): void
    {
        if ($bill->period_end->lt($bill->period_start)) {
            throw ColdStorageException::make('The billing period end must be on or after the start.');
        }
    }
}

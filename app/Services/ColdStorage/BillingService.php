<?php

namespace App\Services\ColdStorage;

use App\Exceptions\ColdStorageException;
use App\Models\Branch;
use App\Models\ColdStorageBill;
use App\Models\ColdStorageBillAllocation;
use App\Models\ColdStorageBilledEntitlement;
use App\Models\ColdStorageDispatch;
use App\Models\ColdStorageDispatchLine;
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
     * Draft a bill scoped to one withdrawal and its lots only, instead of every
     * receipt item the customer owns in the branch. Defaults the period to the
     * earliest arrival date among the withdrawn lots through the withdrawal date.
     */
    public function createDraftFromDispatch(ColdStorageDispatch $dispatch, ?string $actorId, ?string $periodStart = null): ColdStorageBill
    {
        $dispatch->loadMissing('lines.receiptItem.receipt');

        if ($dispatch->lines->isEmpty()) {
            throw ColdStorageException::make('This withdrawal has no lines to bill.');
        }

        $periodStart ??= $dispatch->lines
            ->map(fn ($line) => $line->receiptItem?->receipt?->received_on)
            ->filter()
            ->min()
            ?->toDateString();

        $bill = ColdStorageBill::query()->create([
            'merchant_id' => $dispatch->merchant_id,
            'business_id' => $dispatch->business_id,
            'branch_id' => $dispatch->branch_id,
            'customer_id' => $dispatch->customer_id,
            'dispatch_id' => $dispatch->id,
            'bill_no' => 'SB-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -6)),
            'period_start' => $periodStart ?? $dispatch->dispatched_on->toDateString(),
            'period_end' => $dispatch->dispatched_on->toDateString(),
            'charge_basis' => 'bag',
            'charge_period' => 'daily',
            'status' => 'draft',
            'storage_total' => 0,
            'service_total' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'due_amount' => 0,
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
                $daily = $line['daily_breakdown'] ?? [];
                unset($line['daily_breakdown']);

                $billLine = $bill->lines()->create($line);

                foreach ($daily as $occurredOn => $quantity) {
                    $billLine->entitlements()->create([
                        'receipt_item_id' => $line['receipt_item_id'],
                        'occurred_on' => $occurredOn,
                        'quantity' => $quantity,
                    ]);
                }
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

            if ((float) $bill->due_amount <= 0) {
                throw ColdStorageException::make('This bill is already fully paid.');
            }

            if ($amount - (float) $bill->due_amount > 0.009) {
                throw ColdStorageException::make('Payment cannot exceed the outstanding balance of '.number_format((float) $bill->due_amount, 2).'.');
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

            $merchant = Merchant::query()->whereKey($bill->merchant_id)->lockForUpdate()->firstOrFail();
            $merchant->update([
                $account => round((float) ($merchant->{$account} ?? 0) + $amount, 2),
            ]);

            $this->recomputeBillTotals($bill);

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

    /**
     * Record an advance/partial payment that is not yet tied to a specific bill. The
     * cash lands in the merchant's account immediately; applying it to a bill later
     * (allocateAdvanceToBill) is a separate, cash-neutral step so cash is never
     * recorded twice.
     */
    public function recordAdvancePayment(Customer $customer, float $amount, string $date, ?string $method, ?string $actorId): Payment
    {
        return DB::transaction(function () use ($customer, $amount, $date, $method, $actorId): Payment {
            $amount = round($amount, 2);

            if ($amount <= 0) {
                throw ColdStorageException::make('Advance amount must be greater than zero.');
            }

            $account = $this->cashAccountForMethod($method);

            $payment = Payment::query()->create([
                'merchant_id' => $customer->merchant_id,
                'paymentable_type' => Customer::class,
                'paymentable_id' => $customer->id,
                'party_type' => Customer::class,
                'party_id' => $customer->id,
                'direction' => 'in',
                'entry_type' => 'advance',
                'amount' => $amount,
                'unallocated_amount' => $amount,
                'payment_date' => $date,
                'method' => $account,
                'notes' => 'Cold storage advance',
                'created_by' => $actorId,
            ]);

            $merchant = Merchant::query()->whereKey($customer->merchant_id)->lockForUpdate()->firstOrFail();
            $merchant->update([
                $account => round((float) ($merchant->{$account} ?? 0) + $amount, 2),
            ]);

            return $payment;
        });
    }

    /**
     * Apply part or all of an unallocated advance to a posted bill. This never touches
     * the merchant's cash accounts again (the cash was already recorded when the
     * advance was received) — it only moves credit from "unallocated" to "applied to
     * this bill", so the customer statement never counts it twice.
     */
    public function allocateAdvanceToBill(Payment $advance, ColdStorageBill $bill, float $amount, string $date, ?string $actorId): ColdStorageBillAllocation
    {
        return DB::transaction(function () use ($advance, $bill, $amount, $date, $actorId): ColdStorageBillAllocation {
            $advance = Payment::query()->whereKey($advance->id)->lockForUpdate()->firstOrFail();
            $bill = ColdStorageBill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();
            $amount = round($amount, 2);

            if (! $advance->isUnallocatedAdvance()) {
                throw ColdStorageException::make('Only an unallocated advance can be applied to a bill.');
            }

            if ($advance->party_type !== Customer::class || $advance->party_id !== $bill->customer_id) {
                throw ColdStorageException::make('This advance belongs to a different customer.');
            }

            if (! $bill->isPosted()) {
                throw ColdStorageException::make('Only a posted bill can receive an allocation.');
            }

            if ($amount <= 0) {
                throw ColdStorageException::make('Allocation amount must be greater than zero.');
            }

            if ($amount - (float) $advance->unallocated_amount > 0.009) {
                throw ColdStorageException::make('This advance only has '.number_format((float) $advance->unallocated_amount, 2).' left unallocated.');
            }

            if ($amount - (float) $bill->due_amount > 0.009) {
                throw ColdStorageException::make("Allocation cannot exceed the bill's outstanding balance of ".number_format((float) $bill->due_amount, 2).'.');
            }

            $allocation = ColdStorageBillAllocation::query()->create([
                'merchant_id' => $bill->merchant_id,
                'bill_id' => $bill->id,
                'payment_id' => $advance->id,
                'amount' => $amount,
                'allocated_on' => $date,
                'created_by' => $actorId,
            ]);

            $advance->update([
                'unallocated_amount' => round((float) $advance->unallocated_amount - $amount, 2),
            ]);

            $this->recomputeBillTotals($bill);

            return $allocation;
        });
    }

    public function reverseAllocation(ColdStorageBillAllocation $allocation, ?string $actorId, string $reason): ColdStorageBillAllocation
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ColdStorageException::make('A reversal reason is required.');
        }

        return DB::transaction(function () use ($allocation, $actorId, $reason): ColdStorageBillAllocation {
            $allocation = ColdStorageBillAllocation::query()->whereKey($allocation->id)->lockForUpdate()->firstOrFail();

            if ($allocation->isReversed()) {
                throw ColdStorageException::make('This allocation has already been reversed.');
            }

            $advance = Payment::query()->whereKey($allocation->payment_id)->lockForUpdate()->firstOrFail();
            $bill = ColdStorageBill::query()->whereKey($allocation->bill_id)->lockForUpdate()->firstOrFail();

            $allocation->update([
                'reversed_at' => now(),
                'reversed_by' => $actorId,
                'reversal_reason' => $reason,
            ]);

            $advance->update([
                'unallocated_amount' => round((float) $advance->unallocated_amount + (float) $allocation->amount, 2),
            ]);

            $this->recomputeBillTotals($bill);

            return $allocation->refresh();
        });
    }

    /**
     * Refund the still-unallocated remainder of an advance. A portion already applied
     * to a bill must be reversed first (reverseAllocation), so cash is never both
     * refunded and left standing against a bill at the same time.
     */
    public function refundUnallocatedAdvance(Payment $advance, float $amount, ?string $actorId, string $reason): Payment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ColdStorageException::make('A refund reason is required.');
        }

        return DB::transaction(function () use ($advance, $amount, $actorId, $reason): Payment {
            $advance = Payment::query()->whereKey($advance->id)->lockForUpdate()->firstOrFail();
            $amount = round($amount, 2);

            if (! $advance->isUnallocatedAdvance()) {
                throw ColdStorageException::make('Only an advance can be refunded this way.');
            }

            if ($amount <= 0 || $amount - (float) $advance->unallocated_amount > 0.009) {
                throw ColdStorageException::make('Refund cannot exceed the unallocated balance of '.number_format((float) $advance->unallocated_amount, 2).'.');
            }

            $account = $this->cashAccountForMethod($advance->method);
            $merchant = Merchant::query()->whereKey($advance->merchant_id)->lockForUpdate()->firstOrFail();
            $merchant->update([
                $account => round((float) ($merchant->{$account} ?? 0) - $amount, 2),
            ]);

            $advance->update([
                'unallocated_amount' => round((float) $advance->unallocated_amount - $amount, 2),
                'notes' => trim(((string) $advance->notes).' | Refunded '.number_format($amount, 2).' on '.now()->toDateString().': '.$reason),
            ]);

            return $advance->refresh();
        });
    }

    private function recomputeBillTotals(ColdStorageBill $bill): void
    {
        $paid = round(
            (float) $bill->payments()->sum('amount')
                + (float) $bill->allocations()->whereNull('reversed_at')->sum('amount'),
            2
        );

        $bill->update([
            'paid_amount' => $paid,
            'due_amount' => round(max(0, (float) $bill->total_amount - $paid), 2),
        ]);
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
        $query = ColdStorageReceiptItem::query()
            ->whereHas('receipt', function ($query) use ($bill): void {
                $query->where('merchant_id', $bill->merchant_id)
                    ->where('branch_id', $bill->branch_id)
                    ->where('customer_id', $bill->customer_id)
                    ->where('status', 'posted');
            });

        if (filled($bill->dispatch_id)) {
            $lotItemIds = ColdStorageDispatch::query()
                ->whereKey($bill->dispatch_id)
                ->firstOrFail()
                ->lines()
                ->pluck('receipt_item_id');

            $query->whereIn('id', $lotItemIds);
        }

        $items = $query->get();
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
        $alreadyBilled = $this->alreadyBilledByDay($item, $start->toDateString(), $end->toDateString());
        $segments = [];

        $dispatchLine = filled($bill->dispatch_id)
            ? ColdStorageDispatchLine::query()
                ->with('dispatch')
                ->where('dispatch_id', $bill->dispatch_id)
                ->where('receipt_item_id', $item->id)
                ->first()
            : null;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dayKey = $day->toDateString();
            $raw = $dispatchLine
                ? $this->dispatchBillableQuantity($bill, $item, $movements, $day->copy(), $dispatchLine)
                : $this->billableQuantity($bill, $item, $movements, $day->copy());
            $quantity = Quantities::roundQuantity($raw - (float) ($alreadyBilled[$dayKey] ?? 0));

            if ($quantity <= 0) {
                continue;
            }

            $card = $this->rateCard($bill, $dayKey);
            $key = $card->id;

            if (! isset($segments[$key])) {
                $segments[$key] = [
                    'card' => $card,
                    'quantity_days' => 0.0,
                    'billable_days' => 0,
                    'first' => $dayKey,
                    'last' => $dayKey,
                    'months' => [],
                    'daily' => [],
                ];
            }

            $segments[$key]['quantity_days'] += $quantity;
            $segments[$key]['billable_days']++;
            $segments[$key]['last'] = $dayKey;
            $segments[$key]['daily'][$dayKey] = $quantity;
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
                'daily_breakdown' => $segment['daily'],
            ];
        }

        return $lines;
    }

    /**
     * Quantity already billed for this receipt item on each day, from any other bill
     * still in posted status. Cancelling a bill drops its lines out of this lookup,
     * which is what releases the entitlement it had claimed.
     *
     * @return array<string, float>
     */
    private function alreadyBilledByDay(ColdStorageReceiptItem $item, string $from, string $to): array
    {
        return ColdStorageBilledEntitlement::query()
            ->join('cold_storage_bill_lines', 'cold_storage_bill_lines.id', '=', 'cold_storage_billed_entitlements.bill_line_id')
            ->join('cold_storage_bills', 'cold_storage_bills.id', '=', 'cold_storage_bill_lines.bill_id')
            ->where('cold_storage_billed_entitlements.receipt_item_id', $item->id)
            ->where('cold_storage_bills.status', 'posted')
            ->whereDate('cold_storage_billed_entitlements.occurred_on', '>=', $from)
            ->whereDate('cold_storage_billed_entitlements.occurred_on', '<=', $to)
            ->selectRaw('cold_storage_billed_entitlements.occurred_on as day, SUM(cold_storage_billed_entitlements.quantity) as qty')
            ->groupBy('cold_storage_billed_entitlements.occurred_on')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                Carbon::parse($row->day)->toDateString() => (float) $row->qty,
            ])
            ->all();
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
        $isArrivalDay = $firstReceipt && $firstReceipt->occurred_on->toDateString() === $day->toDateString();

        if ($isArrivalDay && ! $card->bill_arrival_day) {
            $received = $this->signed($today, ['receive'], false);
            $packages -= $received['packages'];
            $weight -= $received['weight'];
        }

        // A same-day round trip (received and dispatched on the arrival day) still
        // owes that day's rent when the arrival day is billed: the departure-day
        // exclusion is meant for a later, separate departure day, not this one.
        if ($card->bill_departure_day || ($isArrivalDay && $card->bill_arrival_day)) {
            $left = $this->signed($today, null, true);
            $packages += $left['packages'];
            $weight += $left['weight'];
        }

        $packages = max(0, $packages);
        $weight = max(0, $weight);

        return Quantities::chargeQuantity((string) $card->charge_basis, $packages, $weight, (string) $item->weight_unit);
    }

    /**
     * Bills exactly the quantity this withdrawal took out, for every day it sat
     * in the chamber (arrival through departure, subject to the rate card's
     * arrival/departure day flags) — not whatever the lot's overall chamber
     * balance was that day. This is what lets "Bill this withdrawal" charge
     * for the withdrawn bags even when they arrived and left the same day,
     * independent of how much of the lot remains in storage.
     *
     * @param  Collection<int, ColdStorageMovement>  $movements
     */
    private function dispatchBillableQuantity(ColdStorageBill $bill, ColdStorageReceiptItem $item, Collection $movements, Carbon $day, ColdStorageDispatchLine $dispatchLine): float
    {
        try {
            $card = $this->rateCard($bill, $day->toDateString());
        } catch (ColdStorageException) {
            return 0.0;
        }

        $firstReceipt = $movements->first(fn (ColdStorageMovement $movement): bool => $movement->movement_type === 'receive');

        if (! $firstReceipt) {
            return 0.0;
        }

        $arrivalDay = $firstReceipt->occurred_on->toDateString();
        $departureDay = $dispatchLine->dispatch->dispatched_on->toDateString();
        $dayKey = $day->toDateString();

        if ($dayKey < $arrivalDay || $dayKey > $departureDay) {
            return 0.0;
        }

        if ($dayKey === $arrivalDay && ! $card->bill_arrival_day) {
            return 0.0;
        }

        if ($dayKey === $departureDay && $dayKey !== $arrivalDay && ! $card->bill_departure_day) {
            return 0.0;
        }

        return Quantities::chargeQuantity(
            (string) $card->charge_basis,
            (float) $dispatchLine->package_count,
            (float) $dispatchLine->net_weight,
            (string) $item->weight_unit
        );
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
     * @param  string|list<string>|null  $type
     * @return array{packages: float, weight: float}
     */
    private function signed(Collection $movements, string|array|null $type, bool $leavingOnly): array
    {
        $filtered = $movements->filter(function (ColdStorageMovement $movement) use ($type, $leavingOnly): bool {
            if ($type !== null) {
                return in_array($movement->movement_type, (array) $type, true);
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

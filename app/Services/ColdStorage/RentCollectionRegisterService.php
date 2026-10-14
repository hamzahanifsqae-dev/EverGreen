<?php

namespace App\Services\ColdStorage;

use App\Models\ColdStorageBill;
use App\Models\ColdStorageDispatch;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;
use App\Support\ColdStorageAccess;

class RentCollectionRegisterService
{
    /**
     * Daily register of withdrawn bags, the rent charged for that withdrawal, and
     * the cash actually collected against it. Withdrawals without a linked bill yet
     * still appear (with blank charge/due) so a clerk can see what still needs
     * billing. Advances with no specific withdrawal keep a blank reference rather
     * than inventing one.
     *
     * @param  array{merchant_id: string, customer_id?: ?string, branch_id?: ?string, from?: ?string, to?: ?string}  $filters
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     totals: array{bags: float, charged: float, collected: float, due: float},
     * }
     */
    public function register(array $filters, Merchant|User|null $user = null): array
    {
        $merchantId = (string) $filters['merchant_id'];
        $branchIds = $this->resolveBranchIds($filters, $user);

        $rows = [
            ...$this->withdrawalRows($merchantId, $filters, $branchIds),
            ...$this->periodBillRows($merchantId, $filters, $branchIds),
            ...$this->advanceRows($merchantId, $filters),
        ];

        usort($rows, fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return [
            'rows' => $rows,
            'totals' => [
                'bags' => round((float) collect($rows)->sum('bags'), 3),
                'charged' => round((float) collect($rows)->sum('charged'), 2),
                'collected' => round((float) collect($rows)->sum('collected'), 2),
                'due' => round((float) collect($rows)->sum('due'), 2),
            ],
        ];
    }

    /**
     * @param  array{customer_id?: ?string, from?: ?string, to?: ?string}  $filters
     * @param  list<string>|null  $branchIds
     * @return list<array<string, mixed>>
     */
    private function withdrawalRows(string $merchantId, array $filters, ?array $branchIds): array
    {
        $dispatches = ColdStorageDispatch::query()
            ->where('merchant_id', $merchantId)
            ->where('status', 'posted')
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->when(filled($filters['customer_id'] ?? null), fn ($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('dispatched_on', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('dispatched_on', '<=', $filters['to']))
            ->with(['customer', 'lines'])
            ->get();

        $dispatchScopedBills = ColdStorageBill::query()
            ->whereIn('dispatch_id', $dispatches->pluck('id'))
            ->with(['lines', 'payments'])
            ->get()
            ->keyBy('dispatch_id');

        // A withdrawal billed the older way (period-based "Bill from stock" rather
        // than "Bill this withdrawal") has no dispatch_id to match on. Still point
        // the clerk at whichever posted bill covers that lot and date, but leave
        // the money columns blank rather than guessing a split — the period bill's
        // own row (periodBillRows) carries the real charged/collected/due once,
        // so totals are never double-counted.
        $periodBillsByReceiptItem = ColdStorageBill::query()
            ->where('merchant_id', $merchantId)
            ->where('status', 'posted')
            ->whereNull('dispatch_id')
            ->with('lines')
            ->get()
            ->flatMap(fn (ColdStorageBill $bill) => $bill->lines->map(fn ($line) => [
                'receipt_item_id' => $line->receipt_item_id,
                'period_start' => $line->period_start->toDateString(),
                'period_end' => $line->period_end->toDateString(),
                'bill' => $bill,
            ]));

        return $dispatches->map(function (ColdStorageDispatch $dispatch) use ($dispatchScopedBills, $periodBillsByReceiptItem): array {
            $bill = $dispatchScopedBills->get($dispatch->id);
            $rates = $bill?->lines->pluck('rate')->unique()->values() ?? collect();
            $dispatchedOn = $dispatch->dispatched_on->toDateString();

            $coveringBill = $bill ? null : $periodBillsByReceiptItem
                ->first(fn (array $row) => $dispatch->lines->contains('receipt_item_id', $row['receipt_item_id'])
                    && $row['period_start'] <= $dispatchedOn
                    && $dispatchedOn <= $row['period_end'])['bill'] ?? null;

            return [
                'date' => $dispatchedOn,
                'customer' => $dispatch->customer?->name,
                'withdrawal_reference' => $dispatch->legacy_reference ?: $dispatch->dispatch_no,
                'bill_reference' => $bill?->bill_no ?? $coveringBill?->bill_no,
                'bags' => Quantities::roundQuantity((float) $dispatch->lines->sum('package_count')),
                'rate' => $rates->count() === 1 ? (float) $rates->first() : null,
                'charged' => $bill ? (float) $bill->total_amount : null,
                'collected' => $bill ? (float) $bill->paid_amount : null,
                'due' => $bill ? (float) $bill->due_amount : null,
                'method' => $bill?->payments->pluck('method')->filter()->unique()->implode(', '),
            ];
        })->all();
    }

    /**
     * Posted bills created the period-based way ("Bill from stock"), each as its
     * own row so their real charged/collected/due are counted exactly once in the
     * register's totals, instead of being guessed per withdrawal.
     *
     * @param  array{customer_id?: ?string, from?: ?string, to?: ?string}  $filters
     * @param  list<string>|null  $branchIds
     * @return list<array<string, mixed>>
     */
    private function periodBillRows(string $merchantId, array $filters, ?array $branchIds): array
    {
        return ColdStorageBill::query()
            ->where('merchant_id', $merchantId)
            ->where('status', 'posted')
            ->whereNull('dispatch_id')
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->when(filled($filters['customer_id'] ?? null), fn ($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('period_end', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('period_end', '<=', $filters['to']))
            ->with(['customer', 'lines', 'payments'])
            ->get()
            ->map(function (ColdStorageBill $bill): array {
                $rates = $bill->lines->pluck('rate')->unique()->values();

                return [
                    'date' => $bill->period_end->toDateString(),
                    'customer' => $bill->customer?->name,
                    'withdrawal_reference' => null,
                    'bill_reference' => $bill->bill_no.' (period bill)',
                    'bags' => 0.0,
                    'rate' => $rates->count() === 1 ? (float) $rates->first() : null,
                    'charged' => (float) $bill->total_amount,
                    'collected' => (float) $bill->paid_amount,
                    'due' => (float) $bill->due_amount,
                    'method' => $bill->payments->pluck('method')->filter()->unique()->implode(', '),
                ];
            })
            ->all();
    }

    /**
     * @param  array{customer_id?: ?string, from?: ?string, to?: ?string}  $filters
     * @return list<array<string, mixed>>
     */
    private function advanceRows(string $merchantId, array $filters): array
    {
        return Payment::query()
            ->where('merchant_id', $merchantId)
            ->where('party_type', Customer::class)
            ->where('entry_type', 'advance')
            ->when(filled($filters['customer_id'] ?? null), fn ($q) => $q->where('party_id', $filters['customer_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('payment_date', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('payment_date', '<=', $filters['to']))
            ->with('party')
            ->get()
            ->map(fn (Payment $payment): array => [
                'date' => $payment->payment_date->toDateString(),
                'customer' => $payment->party?->name,
                'withdrawal_reference' => null,
                'bill_reference' => null,
                'bags' => 0.0,
                'rate' => null,
                'charged' => 0.0,
                'collected' => (float) $payment->amount,
                'due' => 0.0,
                'method' => $payment->method,
            ])
            ->all();
    }

    /**
     * @param  array{branch_id?: ?string}  $filters
     * @return list<string>|null
     */
    private function resolveBranchIds(array $filters, Merchant|User|null $user): ?array
    {
        $authorized = ColdStorageAccess::authorizedBranchIds($user);
        $requested = filled($filters['branch_id'] ?? null) ? [(string) $filters['branch_id']] : null;

        if ($requested === null) {
            return $authorized;
        }

        if ($authorized === null) {
            return $requested;
        }

        return array_values(array_intersect($authorized, $requested));
    }
}

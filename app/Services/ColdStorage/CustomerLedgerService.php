<?php

namespace App\Services\ColdStorage;

use App\Models\ColdStorageBill;
use App\Models\ColdStorageBillAllocation;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;
use App\Support\ColdStorageAccess;
use Illuminate\Support\Carbon;

class CustomerLedgerService
{
    /**
     * Build the cold storage financial statement for one customer: opening balance,
     * posted storage charges, payments, advances and allocations, in deterministic
     * chronological order with a running balance.
     *
     * Only one canonical monetary source counts per event: a posted bill is the
     * charge, a Payment row (direct or advance) is the only place cash is credited,
     * and allocating an advance to a bill is a zero-cash memo that never adds a
     * second credit — it only explains which charge an earlier credit paid off.
     *
     * @param  array{branch_id?: ?string, from?: ?string, to?: ?string}  $filters
     * @return array{
     *     opening_balance: float,
     *     closing_balance: float,
     *     unallocated_advance: float,
     *     rows: list<array<string, mixed>>,
     * }
     */
    public function statement(Customer $customer, array $filters, Merchant|User|null $user = null): array
    {
        $merchantId = (string) $customer->merchant_id;
        $customerId = (string) $customer->id;
        $branchIds = $this->resolveBranchIds($filters, $user);

        $events = [
            ...$this->billEvents($merchantId, $customerId, $branchIds),
            ...$this->paymentEvents($merchantId, $customerId),
            ...$this->allocationMemoEvents($merchantId, $customerId),
        ];

        usort($events, fn (array $a, array $b): int => $this->compareEvents($a, $b));

        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $openingBalance = 0.0;
        $running = 0.0;
        $rows = [];

        foreach ($events as $event) {
            // Events after the "to" cutoff are excluded entirely so they never
            // affect the balance shown as of that date.
            if ($to !== null && $event['date'] > $to) {
                continue;
            }

            $running = round($running + $event['debit'] - $event['credit'], 2);

            // Events before "from" still accumulate into the opening balance but
            // are not shown as their own row.
            if ($from !== null && $event['date'] < $from) {
                $openingBalance = $running;

                continue;
            }

            $rows[] = [
                'date' => $event['date'],
                'description' => $event['description'],
                'reference' => $event['reference'],
                'debit' => $event['debit'],
                'credit' => $event['credit'],
                'balance' => $running,
                'bags' => $event['bags'] ?? null,
                'doc_type' => $event['doc_type'],
                'doc_id' => $event['doc_id'],
            ];
        }

        $closingBalance = $rows === [] ? $openingBalance : (float) end($rows)['balance'];

        return [
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'unallocated_advance' => $this->unallocatedAdvanceTotal($merchantId, $customerId),
            'rows' => $rows,
        ];
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

    /**
     * @param  list<string>|null  $branchIds
     * @return list<array<string, mixed>>
     */
    private function billEvents(string $merchantId, string $customerId, ?array $branchIds): array
    {
        return ColdStorageBill::query()
            ->where('merchant_id', $merchantId)
            ->where('customer_id', $customerId)
            ->where('status', 'posted')
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->get()
            ->map(fn (ColdStorageBill $bill): array => [
                'date' => $bill->period_end->toDateString(),
                'description' => 'Storage bill '.$bill->bill_no,
                'reference' => $bill->bill_no,
                'debit' => (float) $bill->total_amount,
                'credit' => 0.0,
                'bags' => (float) $bill->lines->sum('quantity_days') ?: null,
                'doc_type' => 'bill',
                'doc_id' => $bill->id,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paymentEvents(string $merchantId, string $customerId): array
    {
        return Payment::query()
            ->where('merchant_id', $merchantId)
            ->where('party_type', Customer::class)
            ->where('party_id', $customerId)
            ->whereIn('entry_type', ['payment', 'advance'])
            ->get()
            ->map(fn (Payment $payment): array => [
                'date' => $payment->payment_date->toDateString(),
                'description' => $payment->entry_type === 'advance'
                    ? 'Advance received'
                    : 'Payment against '.($payment->reference_no ?? 'bill'),
                'reference' => $payment->reference_no,
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
                'doc_type' => 'payment',
                'doc_id' => $payment->id,
            ])
            ->all();
    }

    /**
     * Zero-value memo rows: they explain which charge an earlier advance credit was
     * applied to, without changing the running balance (the credit already happened
     * when the advance was received).
     *
     * @return list<array<string, mixed>>
     */
    private function allocationMemoEvents(string $merchantId, string $customerId): array
    {
        return ColdStorageBillAllocation::query()
            ->where('merchant_id', $merchantId)
            ->whereNull('reversed_at')
            ->whereHas('bill', fn ($query) => $query->where('customer_id', $customerId))
            ->with('bill')
            ->get()
            ->map(fn (ColdStorageBillAllocation $allocation): array => [
                'date' => $allocation->allocated_on->toDateString(),
                'description' => 'Advance of '.number_format((float) $allocation->amount, 2).' applied to '.$allocation->bill?->bill_no,
                'reference' => $allocation->bill?->bill_no,
                'debit' => 0.0,
                'credit' => 0.0,
                'doc_type' => 'allocation',
                'doc_id' => $allocation->id,
            ])
            ->all();
    }

    private function unallocatedAdvanceTotal(string $merchantId, string $customerId): float
    {
        return round((float) Payment::query()
            ->where('merchant_id', $merchantId)
            ->where('party_type', Customer::class)
            ->where('party_id', $customerId)
            ->where('entry_type', 'advance')
            ->sum('unallocated_amount'), 2);
    }

    private function compareEvents(array $a, array $b): int
    {
        $dateCompare = strcmp($a['date'], $b['date']);

        if ($dateCompare !== 0) {
            return $dateCompare;
        }

        $priority = ['opening_balance' => 0, 'bill' => 1, 'payment' => 2, 'allocation' => 3];

        return ($priority[$a['doc_type']] ?? 9) <=> ($priority[$b['doc_type']] ?? 9);
    }
}

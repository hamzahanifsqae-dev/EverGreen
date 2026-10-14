<x-filament-panels::page>
    @php
        $data = $this->register();
        $rows = $data['rows'];
        $totals = $data['totals'];
        $currency = config('cold-storage.currency');
    @endphp

    <div class="mb-6">
        {{ $this->getFiltersForm() }}
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-slate-900 dark:text-slate-100 dark:ring-slate-700/40">
            <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Bags released</p>
                    <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ number_format($totals['bags'], 2) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Rent charged</p>
                    <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ number_format($totals['charged'], 2) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Collected</p>
                    <p class="text-lg font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($totals['collected'], 2) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Outstanding</p>
                    <p class="text-lg font-semibold text-rose-600 dark:text-rose-400">{{ number_format($totals['due'], 2) }}</p>
                </div>
            </div>
        </div>

        <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <div class="overflow-x-auto">
                <table class="w-full min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Date</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Customer</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Withdrawal</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Bill</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Bags</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Rate</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Charged</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Collected</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Due</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Method</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['customer'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">{{ $row['withdrawal_reference'] ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">{{ $row['bill_reference'] ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-950 dark:text-gray-200">{{ $row['bags'] > 0 ? number_format($row['bags'], 2) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-950 dark:text-gray-200">{{ $row['rate'] !== null ? number_format($row['rate'], 2) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-950 dark:text-gray-200">{{ $row['charged'] !== null ? number_format($row['charged'], 2) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-emerald-600 dark:text-emerald-400">{{ $row['collected'] !== null ? number_format($row['collected'], 2) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-rose-600 dark:text-rose-400">{{ $row['due'] !== null ? number_format($row['due'], 2) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">{{ $row['method'] ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No withdrawals or advances in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>

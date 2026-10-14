<x-filament-panels::page>
    @php
        $statement = $this->statement;
        $currency = config('cold-storage.currency');
    @endphp

    <div class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-slate-900 dark:text-slate-100 dark:ring-slate-700/40">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">From</label>
                    <input type="date" wire:model="from" class="mt-1 block rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-slate-800 dark:text-slate-100" />
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">To</label>
                    <input type="date" wire:model="to" class="mt-1 block rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-slate-800 dark:text-slate-100" />
                </div>
                <button wire:click="applyFilters" type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                    Filter
                </button>
                <button wire:click="clearFilters" type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 dark:bg-slate-800 dark:text-slate-200">
                    Clear
                </button>

                <div class="ml-auto flex gap-6 text-right">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Opening</p>
                        <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ number_format($statement['opening_balance'], 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Closing</p>
                        <p class="text-lg font-semibold {{ $statement['closing_balance'] >= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ number_format($statement['closing_balance'], 2) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Unallocated advance</p>
                        <p class="text-lg font-semibold text-amber-600 dark:text-amber-400">{{ number_format($statement['unallocated_advance'], 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <div class="overflow-x-auto">
                <table class="w-full min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Date</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Description</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Reference</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Debit</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Credit</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($statement['rows'] as $row)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 text-gray-950 dark:text-gray-200">
                                    {{ $row['description'] }}
                                    @if ($row['doc_type'] === 'allocation')
                                        <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">memo</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">
                                    @if ($row['doc_type'] === 'bill')
                                        <a href="{{ $this->billViewUrl($row['doc_id']) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $row['reference'] }}</a>
                                    @else
                                        {{ $row['reference'] ?? '—' }}
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-950 dark:text-gray-200">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-950 dark:text-gray-200">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right font-medium text-gray-950 dark:text-gray-200">{{ number_format($row['balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No ledger activity for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>

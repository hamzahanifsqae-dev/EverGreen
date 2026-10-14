<x-filament-panels::page>
    @php
        $groups = $this->register()['groups'];
    @endphp

    <div class="mb-6">
        {{ $this->getFiltersForm() }}
    </div>

    <div class="space-y-6">
        @forelse ($groups as $group)
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gray-50/60 px-6 py-4 dark:border-white/10 dark:bg-white/[0.03]">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-600 dark:bg-sky-900/40 dark:text-sky-300">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375C2.754 3.75 2.25 4.254 2.25 4.875v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                        </span>
                        <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            {{ $group['customer'] }} — Lot {{ $group['lot_number'] }}
                            <span class="font-normal text-gray-500 dark:text-gray-400">({{ $group['product'] }})</span>
                        </h2>
                    </div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-semibold ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                        <span class="text-gray-500 dark:text-gray-400">Opening</span>
                        <span class="text-gray-900 dark:text-gray-100">{{ number_format($group['opening'], 2) }}</span>
                        <svg class="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                        <span class="text-gray-500 dark:text-gray-400">Closing</span>
                        <span class="text-sky-600 dark:text-sky-300">{{ number_format($group['closing'], 2) }}</span>
                    </div>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="bg-gray-50/60 dark:bg-white/[0.02]">
                                <th class="px-6 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Date</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Inward</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Outward</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Adjustment</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Closing</th>
                                <th class="px-6 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Reference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($group['rows'] as $row)
                                <tr class="transition-colors hover:bg-gray-50/80 dark:hover:bg-white/[0.03]">
                                    <td class="whitespace-nowrap px-6 py-3 text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">
                                        @if ($row['inward'] > 0)
                                            <span class="font-medium text-emerald-600 dark:text-emerald-400">+{{ number_format($row['inward'], 2) }}</span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">
                                        @if ($row['outward'] > 0)
                                            <span class="font-medium text-rose-600 dark:text-rose-400">-{{ number_format($row['outward'], 2) }}</span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">
                                        @if ($row['adjustment'] != 0)
                                            <span class="font-medium text-amber-600 dark:text-amber-400">{{ $row['adjustment'] > 0 ? '+' : '' }}{{ number_format($row['adjustment'], 2) }}</span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right font-semibold tabular-nums text-gray-950 dark:text-gray-100">{{ number_format($row['closing'], 2) }}</td>
                                    <td class="px-6 py-3">
                                        @if ($row['references'])
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($row['references'] as $reference)
                                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $reference }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/60 px-6 py-12 text-center dark:border-white/10 dark:bg-white/[0.02]">
                <p class="text-sm text-gray-500 dark:text-gray-400">No stock movements match these filters.</p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>

<x-filament-panels::page>
    @php
        $rows = $this->ageingRows();
        $oldest = collect($rows)->max('days_in_store') ?? 0;
        $avgDays = count($rows) > 0
            ? (int) round(collect($rows)->avg('days_in_store'))
            : 0;
    @endphp

    <div class="mb-6">
        {{ $this->getFiltersForm() }}
    </div>

    <div class="space-y-6 stats-dashboard">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 stats-card dark:bg-slate-900 dark:text-slate-100 dark:ring-slate-700/40">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">Overview</p>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Days in store</span>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="rounded-2xl bg-gradient-to-br from-orange-50 to-white p-5 shadow-sm ring-1 ring-orange-100 stats-panel stats-panel-orange dark:from-slate-950 dark:to-slate-950 dark:ring-orange-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Lots in stock</p>
                        <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600 dark:bg-orange-900/40 dark:text-orange-200">Active</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format(count($rows)) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Matching current filters</p>
                </div>

                <div class="rounded-2xl bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm ring-1 ring-amber-100 stats-panel stats-panel-amber dark:from-slate-950 dark:to-slate-950 dark:ring-amber-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Oldest lot</p>
                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-600 dark:bg-amber-900/40 dark:text-amber-200">Max</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($oldest) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Days since receipt</p>
                </div>

                <div class="rounded-2xl bg-gradient-to-br from-sky-50 to-white p-5 shadow-sm ring-1 ring-sky-100 stats-panel stats-panel-sky dark:from-slate-950 dark:to-slate-950 dark:ring-sky-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Average age</p>
                        <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-600 dark:bg-sky-900/40 dark:text-sky-200">Mean</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($avgDays) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Days across lots shown</p>
                </div>
            </div>
        </div>

        <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <header class="fi-section-header flex items-center justify-between gap-x-3 px-6 py-4">
                <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    Lots in store (oldest first)
                </h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ count($rows) }} {{ \Illuminate\Support\Str::plural('row', count($rows)) }}
                </span>
            </header>

            <div class="fi-section-content border-t border-gray-200 px-6 py-4 dark:border-white/10">
                @if ($rows === [])
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No stock on hand for these filters.
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Days</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Lot</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Customer</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Product</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Chamber</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Packages</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Weight</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Received</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($rows as $row)
                                    @php
                                        $days = (int) $row['days_in_store'];
                                        $badge = match (true) {
                                            $days >= 90 => 'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200',
                                            $days >= 30 => 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
                                            default => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="whitespace-nowrap px-3 py-2">
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge }}">
                                                {{ number_format($days) }} d
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-950 dark:text-gray-200">{{ $row['lot_number'] }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['customer'] }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['product'] }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['chamber'] }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ number_format($row['packages'], 2) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ number_format($row['weight'], 2) }} {{ $row['weight_unit'] }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['received_on'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>

<x-filament-panels::page>
    @php
        $summary = $this->summary();
        $currency = config('cold-storage.currency', 'PKR');
        $overdueBills = $this->overdueBills();
        $upcomingReservations = $this->upcomingReservations();
    @endphp

    <div class="space-y-6 stats-dashboard">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 stats-card dark:bg-slate-900 dark:text-slate-100 dark:ring-slate-700/40">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">Overview</p>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Needs attention</span>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl bg-gradient-to-br from-rose-50 to-white p-5 shadow-sm ring-1 ring-rose-100 stats-panel stats-panel-rose dark:from-slate-950 dark:to-slate-950 dark:ring-rose-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Open alerts</p>
                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-600 dark:bg-rose-900/40 dark:text-rose-200">All</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($summary['open']) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Items that still need action</p>
                </div>

                <div class="rounded-2xl bg-gradient-to-br from-orange-50 to-white p-5 shadow-sm ring-1 ring-orange-100 stats-panel stats-panel-orange dark:from-slate-950 dark:to-slate-950 dark:ring-orange-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Temperature</p>
                        <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600 dark:bg-orange-900/40 dark:text-orange-200">Unacked</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($summary['temperature']) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Out-of-range readings</p>
                </div>

                <div class="rounded-2xl bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm ring-1 ring-amber-100 stats-panel stats-panel-amber dark:from-slate-950 dark:to-slate-950 dark:ring-amber-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Bills due</p>
                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-600 dark:bg-amber-900/40 dark:text-amber-200">Balance</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($summary['bills']) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Posted bills with outstanding amount</p>
                </div>

                <div class="rounded-2xl bg-gradient-to-br from-sky-50 to-white p-5 shadow-sm ring-1 ring-sky-100 stats-panel stats-panel-sky dark:from-slate-950 dark:to-slate-950 dark:ring-sky-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Reservations</p>
                        <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-600 dark:bg-sky-900/40 dark:text-sky-200">7 days</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($summary['reservations']) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Confirmed bookings starting soon</p>
                </div>
            </div>
        </div>

        <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <header class="fi-section-header flex items-center justify-between gap-x-3 px-6 py-4">
                <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    Temperature exceptions needing review
                </h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Acknowledge after you have checked the chamber
                </span>
            </header>
            <div class="fi-section-content border-t border-gray-200 px-6 py-4 dark:border-white/10">
                {{ $this->table }}
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <header class="fi-section-header flex items-center justify-between gap-x-3 px-6 py-4">
                    <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Outstanding storage bills
                    </h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ count($overdueBills) }} {{ \Illuminate\Support\Str::plural('row', count($overdueBills)) }}
                    </span>
                </header>
                <div class="fi-section-content border-t border-gray-200 px-6 py-4 dark:border-white/10">
                    @if ($overdueBills === [])
                        <p class="text-sm text-gray-500 dark:text-gray-400">No outstanding bills.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Bill</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Customer</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Branch</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Due</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                    @foreach ($overdueBills as $row)
                                        <tr>
                                            <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-950 dark:text-gray-200">{{ $row['bill_no'] }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['customer'] }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['branch'] }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $currency }} {{ number_format($row['due_amount'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>

            <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <header class="fi-section-header flex items-center justify-between gap-x-3 px-6 py-4">
                    <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Confirmed reservations starting soon
                    </h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ count($upcomingReservations) }} {{ \Illuminate\Support\Str::plural('row', count($upcomingReservations)) }}
                    </span>
                </header>
                <div class="fi-section-content border-t border-gray-200 px-6 py-4 dark:border-white/10">
                    @if ($upcomingReservations === [])
                        <p class="text-sm text-gray-500 dark:text-gray-400">No upcoming reservations.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Booking</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Customer</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Chamber</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">From</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                    @foreach ($upcomingReservations as $row)
                                        <tr>
                                            <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-950 dark:text-gray-200">{{ $row['reservation_no'] }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['customer'] }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['chamber'] ?? '—' }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['reserved_from'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>

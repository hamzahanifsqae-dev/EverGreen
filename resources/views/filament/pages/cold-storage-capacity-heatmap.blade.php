<x-filament-panels::page>
    @php
        $rows = $this->heatmapRows();
        $avgPercent = count($rows) > 0
            ? round(collect($rows)->avg('occupancy_percent'), 1)
            : 0;
        $critical = collect($rows)->where('heat', 'critical')->count();
        $high = collect($rows)->where('heat', 'high')->count();
    @endphp

    <div class="mb-6">
        {{ $this->getFiltersForm() }}
    </div>

    <div class="space-y-6 stats-dashboard">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 stats-card dark:bg-slate-900 dark:text-slate-100 dark:ring-slate-700/40">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">Overview</p>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Live capacity</span>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="rounded-2xl bg-gradient-to-br from-sky-50 to-white p-5 shadow-sm ring-1 ring-sky-100 stats-panel stats-panel-sky dark:from-slate-950 dark:to-slate-950 dark:ring-sky-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Chambers</p>
                        <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-600 dark:bg-sky-900/40 dark:text-sky-200">Listed</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format(count($rows)) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Matching current filters</p>
                </div>

                <div class="rounded-2xl bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm ring-1 ring-blue-100 stats-panel stats-panel-blue dark:from-slate-950 dark:to-slate-950 dark:ring-blue-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Average fill</p>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-600 dark:bg-blue-900/40 dark:text-blue-200">Mean</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($avgPercent, 1) }}%</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Across listed chambers</p>
                </div>

                <div class="rounded-2xl bg-gradient-to-br from-rose-50 to-white p-5 shadow-sm ring-1 ring-rose-100 stats-panel stats-panel-rose dark:from-slate-950 dark:to-slate-950 dark:ring-rose-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Pressure</p>
                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-600 dark:bg-rose-900/40 dark:text-rose-200">Watch</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($critical + $high) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $critical }} critical · {{ $high }} high (≥75%)</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 stats-card dark:bg-slate-900 dark:text-slate-100 dark:ring-slate-700/40">
            <div class="flex items-center justify-between">
                <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">Chamber fill</p>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">
                    {{ count($rows) }} {{ \Illuminate\Support\Str::plural('chamber', count($rows)) }}
                </span>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                @forelse ($rows as $row)
                    @php
                        $percent = (float) ($row['occupancy_percent'] ?? 0);
                        $heat = $row['heat'] ?? 'low';
                        $panel = match ($heat) {
                            'critical' => 'from-rose-50 to-white ring-rose-100 dark:ring-rose-900/40',
                            'high' => 'from-orange-50 to-white ring-orange-100 dark:ring-orange-900/40',
                            'medium' => 'from-amber-50 to-white ring-amber-100 dark:ring-amber-900/40',
                            default => 'from-emerald-50 to-white ring-emerald-100 dark:ring-emerald-900/40',
                        };
                        $bar = match ($heat) {
                            'critical' => 'bg-rose-500',
                            'high' => 'bg-orange-500',
                            'medium' => 'bg-amber-400',
                            default => 'bg-emerald-500',
                        };
                        $badge = match ($heat) {
                            'critical' => 'bg-rose-50 text-rose-600 dark:bg-rose-900/40 dark:text-rose-200',
                            'high' => 'bg-orange-50 text-orange-600 dark:bg-orange-900/40 dark:text-orange-200',
                            'medium' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/40 dark:text-amber-200',
                            default => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-200',
                        };
                        $label = match ($heat) {
                            'critical' => 'Critical',
                            'high' => 'High',
                            'medium' => 'Medium',
                            default => 'Low',
                        };
                    @endphp
                    <div class="rounded-2xl bg-gradient-to-br {{ $panel }} p-5 shadow-sm ring-1 dark:from-slate-950 dark:to-slate-950">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $row['chamber'] }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $row['branch'] }} · {{ $row['business'] }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $badge }}">{{ $label }}</span>
                        </div>

                        <p class="mt-4 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($percent, 1) }}%</p>

                        <div class="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-white/80 ring-1 ring-slate-100 dark:bg-slate-800 dark:ring-slate-700/40">
                            <div class="{{ $bar }} h-full rounded-full transition-all" style="width: {{ min(100, max(0, $percent)) }}%"></div>
                        </div>

                        <div class="mt-4 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                            <div class="flex items-center justify-between">
                                <span>Occupied</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format((float) $row['occupied'], 2) }} {{ $row['unit'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Available</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format((float) $row['available'], 2) }} {{ $row['unit'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Capacity</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format((float) $row['capacity'], 2) }} {{ $row['unit'] }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="lg:col-span-2">
                        <p class="text-sm text-slate-500 dark:text-slate-400">No chambers match these filters.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>

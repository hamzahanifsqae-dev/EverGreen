<x-filament-widgets::widget>
    @php
        $stats = $stats ?? [];
        $stock = $stock ?? ['packages' => 0, 'weight' => 0, 'lots' => 0];
        $occupancy = $occupancy ?? ['capacity' => 0, 'occupied' => 0, 'available' => 0, 'unit' => 'kg'];
        $trend = $trend ?? ['labels' => [], 'receipts' => [], 'dispatches' => []];
        $leaders = $leaders ?? ['customers' => []];
        $currency = $currency ?? 'PKR';
        $filterPeriodLabel = $filterPeriodLabel ?? 'All time';
        $insights = $insights ?? ['urls' => [], 'heatmap' => [], 'ageing' => [], 'chamber_heat' => []];
        $insightUrls = $insights['urls'] ?? [];

        $labels = $trend['labels'] ?? [];
        $receiptSeries = $trend['receipts'] ?? [];
        $dispatchSeries = $trend['dispatches'] ?? [];
        $seriesAll = array_merge($receiptSeries, $dispatchSeries);
        $maxValue = max($seriesAll ?: [1]);
        $chartWidth = 560;
        $chartHeight = 260;
        $axisLeft = 36;
        $axisRight = 12;
        $axisTop = 10;
        $axisBottom = 24;
        $plotWidth = $chartWidth - $axisLeft - $axisRight;
        $plotHeight = $chartHeight - $axisTop - $axisBottom;
        $pointCount = max(count($receiptSeries), 1);
        $step = $pointCount > 1 ? $plotWidth / ($pointCount - 1) : 0;
        $makePoints = function (array $series) use ($axisLeft, $axisTop, $plotHeight, $pointCount, $step, $maxValue) {
            $points = [];
            foreach ($series as $index => $value) {
                $x = $axisLeft + ($index * $step);
                $normalized = $maxValue > 0 ? $value / $maxValue : 0;
                $y = $axisTop + ($plotHeight - ($plotHeight * $normalized));
                $points[] = $x.','.$y;
            }

            return implode(' ', $points);
        };
        $receiptPoints = $makePoints($receiptSeries);
        $dispatchPoints = $makePoints($dispatchSeries);
        $periodReceiptsTotal = array_sum($receiptSeries);
        $periodDispatchesTotal = array_sum($dispatchSeries);
        $barMax = max($seriesAll ?: [1]);
        $barHeights = [
            'receipts' => array_map(fn ($value) => $barMax > 0 ? (int) round(($value / $barMax) * 100) : 0, $receiptSeries),
            'dispatches' => array_map(fn ($value) => $barMax > 0 ? (int) round(($value / $barMax) * 100) : 0, $dispatchSeries),
        ];
        $tickCount = 4;
        $ticks = [];
        for ($i = 0; $i <= $tickCount; $i++) {
            $ticks[] = [
                'value' => (int) round($maxValue * (1 - ($i / $tickCount))),
                'y' => $axisTop + ($plotHeight * ($i / $tickCount)),
            ];
        }

        $occupancyPercent = $occupancy['capacity'] > 0
            ? round(($occupancy['occupied'] / $occupancy['capacity']) * 100, 1)
            : 0;
    @endphp

    <div
        class="space-y-6 stats-dashboard"
        x-data="{
            showReceipts: true,
            showDispatches: true,
            toggle(which) {
                if (which === 'receipts') {
                    this.showReceipts = !this.showReceipts;
                    if (!this.showReceipts && !this.showDispatches) this.showDispatches = true;
                }
                if (which === 'dispatches') {
                    this.showDispatches = !this.showDispatches;
                    if (!this.showDispatches && !this.showReceipts) this.showReceipts = true;
                }
            }
        }"
    >
        {{-- Overview --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 stats-card dark:bg-slate-900 dark:text-slate-100 dark:ring-slate-700/40">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">Overview</p>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ $filterPeriodLabel }}</span>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm ring-1 ring-blue-100 stats-panel stats-panel-blue dark:from-slate-950 dark:to-slate-950 dark:ring-blue-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Receiving</p>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-600 dark:bg-blue-900/40 dark:text-blue-200">Inbound</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format((float) ($stats['receipt_packages'] ?? 0), 2) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Packages received (posted)</p>
                    <div class="mt-4 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                        <div class="flex items-center justify-between">
                            <span>Posted receipts</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($stats['receipts_posted'] ?? 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Draft receipts</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($stats['receipts_draft'] ?? 0) }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-gradient-to-br from-emerald-50 to-white p-5 shadow-sm ring-1 ring-emerald-100 stats-panel stats-panel-emerald dark:from-slate-950 dark:to-slate-950 dark:ring-emerald-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Dispatch</p>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-200">Outbound</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format((float) ($stats['dispatch_packages'] ?? 0), 2) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Packages dispatched (posted)</p>
                    <div class="mt-4 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                        <div class="flex items-center justify-between">
                            <span>Posted dispatches</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($stats['dispatches_posted'] ?? 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Draft dispatches</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($stats['dispatches_draft'] ?? 0) }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-gradient-to-br from-orange-50 to-white p-5 shadow-sm ring-1 ring-orange-100 stats-panel stats-panel-orange dark:from-slate-950 dark:to-slate-950 dark:ring-orange-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Stock on Hand</p>
                        <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600 dark:bg-orange-900/40 dark:text-orange-200">Inventory</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($stock['lots'] ?? 0) }}</p>
                    <div class="mt-4 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                        <div class="flex items-center justify-between">
                            <span>Lots in stock</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($stock['lots'] ?? 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Net packages</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($stock['packages'] ?? 0, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Net weight</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($stock['weight'] ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm ring-1 ring-amber-100 stats-panel stats-panel-amber dark:from-slate-950 dark:to-slate-950 dark:ring-amber-900/50">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Billing</p>
                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-600 dark:bg-amber-900/40 dark:text-amber-200">Charges</span>
                    </div>
                    <p class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ $currency }} {{ number_format((float) ($stats['due_amount'] ?? 0), 2) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Outstanding due</p>
                    <div class="mt-4 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                        <div class="flex items-center justify-between">
                            <span>Bills with balance</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($stats['bills_due'] ?? 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Collected</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $currency }} {{ number_format((float) ($stats['paid_amount'] ?? 0), 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Billed amount</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $currency }} {{ number_format((float) ($stats['billed_amount'] ?? 0), 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Top depositors + chamber health --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 stats-card dark:bg-slate-900 dark:text-slate-100 dark:ring-slate-700/40">
            <div class="flex items-center justify-between">
                <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">Facility Snapshot</p>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Live</span>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-2xl bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm ring-1 ring-blue-100 dark:from-slate-950 dark:to-slate-950 dark:ring-blue-900/40 lg:col-span-1">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Top Depositors</p>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-600 dark:bg-blue-900/40 dark:text-blue-200">Receipts</span>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse (($leaders['customers'] ?? []) as $index => $row)
                            <div class="rounded-xl bg-white p-3 ring-1 ring-slate-100 dark:bg-slate-800/80 dark:ring-slate-700/40">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-900/40 dark:text-blue-200">{{ $index + 1 }}</span>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $row['name'] }}</p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ number_format($row['count']) }} posted receipts</p>
                                        </div>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ number_format($row['count']) }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500 dark:text-slate-400">No depositor receipt data available.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl bg-gradient-to-br from-sky-50 to-white p-5 shadow-sm ring-1 ring-sky-100 stats-panel stats-panel-sky dark:from-slate-950 dark:to-slate-950 dark:ring-sky-900/40 lg:col-span-1">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Chamber Occupancy</p>
                        <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-600 dark:bg-sky-900/40 dark:text-sky-200">Capacity</span>
                    </div>
                    <p class="mt-4 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($occupancyPercent, 1) }}%</p>
                    <div class="mt-4 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                        <div class="flex items-center justify-between">
                            <span>Active chambers</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($stats['chambers'] ?? 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Occupied</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($occupancy['occupied'] ?? 0, 2) }} {{ $occupancy['unit'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Available</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($occupancy['available'] ?? 0, 2) }} {{ $occupancy['unit'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Total capacity</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($occupancy['capacity'] ?? 0, 2) }} {{ $occupancy['unit'] }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-gradient-to-br from-rose-50 to-white p-5 shadow-sm ring-1 ring-rose-100 stats-panel stats-panel-rose dark:from-slate-950 dark:to-slate-950 dark:ring-rose-900/40 lg:col-span-1">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Alerts & Master Data</p>
                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-600 dark:bg-rose-900/40 dark:text-rose-200">Monitor</span>
                    </div>
                    @if (($stats['action_alerts_open'] ?? null) !== null)
                        <p class="mt-4 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($stats['action_alerts_open']) }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Open action alerts</p>
                        @if ($insightUrls['alerts'] ?? null)
                            <a href="{{ $insightUrls['alerts'] }}" class="mt-2 inline-block text-xs font-semibold text-rose-600 hover:underline dark:text-rose-300">Review alerts</a>
                        @endif
                    @else
                        <p class="mt-4 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($stats['temp_exceptions'] ?? 0) }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Temperature exceptions (unacknowledged)</p>
                    @endif
                    <div class="mt-4 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                        @if ($stats['customers'] !== null)
                            <div class="flex items-center justify-between">
                                <span>Customers</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($stats['customers'] ?? 0) }}</span>
                            </div>
                        @endif
                        @if ($stats['products'] !== null)
                            <div class="flex items-center justify-between">
                                <span>Products</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($stats['products'] ?? 0) }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span>Active chambers</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ number_format($stats['chambers'] ?? 0) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if (! empty($insights['ageing']) || ! empty($insights['chamber_heat']))
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 stats-card dark:bg-slate-900 dark:text-slate-100 dark:ring-slate-700/40">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">Storage insights</p>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Live</span>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    @if (! empty($insights['ageing']))
                        <div class="rounded-2xl bg-gradient-to-br from-orange-50 to-white p-5 shadow-sm ring-1 ring-orange-100 dark:from-slate-950 dark:to-slate-950 dark:ring-orange-900/40">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Oldest lots in store</p>
                                @if ($insightUrls['ageing'] ?? null)
                                    <a href="{{ $insightUrls['ageing'] }}" class="rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600 hover:underline dark:bg-orange-900/40 dark:text-orange-200">View all</a>
                                @else
                                    <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600 dark:bg-orange-900/40 dark:text-orange-200">Ageing</span>
                                @endif
                            </div>
                            <div class="mt-4 space-y-3">
                                @foreach ($insights['ageing'] as $index => $row)
                                    <div class="rounded-xl bg-white p-3 ring-1 ring-slate-100 dark:bg-slate-800/80 dark:ring-slate-700/40">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex items-start gap-3">
                                                <span class="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-orange-100 text-xs font-bold text-orange-700 dark:bg-orange-900/40 dark:text-orange-200">{{ $index + 1 }}</span>
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $row['lot_number'] }}</p>
                                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $row['customer'] }}</p>
                                                </div>
                                            </div>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ number_format($row['days_in_store']) }} d</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (! empty($insights['chamber_heat']))
                        <div class="rounded-2xl bg-gradient-to-br from-sky-50 to-white p-5 shadow-sm ring-1 ring-sky-100 dark:from-slate-950 dark:to-slate-950 dark:ring-sky-900/40">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Chamber fill</p>
                                @if ($insightUrls['heatmap'] ?? null)
                                    <a href="{{ $insightUrls['heatmap'] }}" class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-600 hover:underline dark:bg-sky-900/40 dark:text-sky-200">Heatmap</a>
                                @else
                                    <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-600 dark:bg-sky-900/40 dark:text-sky-200">Capacity</span>
                                @endif
                            </div>
                            <div class="mt-4 space-y-3">
                                @foreach ($insights['chamber_heat'] as $row)
                                    <div class="rounded-xl bg-white p-3 ring-1 ring-slate-100 dark:bg-slate-800/80 dark:ring-slate-700/40">
                                        <div class="flex items-center justify-between gap-3 text-sm">
                                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $row['name'] }}</span>
                                            <span class="font-semibold text-slate-900 dark:text-slate-100">{{ number_format($row['percent'], 1) }}%</span>
                                        </div>
                                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                                            <div class="h-full rounded-full bg-sky-500" style="width: {{ min(100, max(0, $row['percent'])) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Operations Pulse --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 stats-card dark:bg-slate-900 dark:text-slate-100 dark:ring-slate-700/40">
            <div class="flex items-center justify-between">
                <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">Operations Pulse</p>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Last 6 months</span>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-4">
                <div class="lg:col-span-3">
                    <div class="rounded-2xl bg-gradient-to-br from-blue-50 via-white to-emerald-50 p-6 ring-1 ring-gray-950/5 stats-panel dark:bg-slate-900 dark:ring-slate-700/40">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Receipts vs Dispatches</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Monthly trend</p>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <button type="button" class="flex items-center gap-2" @click="toggle('receipts')" :class="showReceipts ? 'opacity-100' : 'opacity-40'">
                                    <span class="h-2 w-2 rounded-full bg-blue-500"></span>Receipts
                                </button>
                                <button type="button" class="flex items-center gap-2" @click="toggle('dispatches')" :class="showDispatches ? 'opacity-100' : 'opacity-40'">
                                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>Dispatches
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 overflow-hidden rounded-xl bg-white/80 p-4 shadow-sm dark:bg-slate-900/80">
                            <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="h-64 w-full">
                                @foreach ($ticks as $tick)
                                    <line x1="{{ $axisLeft }}" x2="{{ $chartWidth - $axisRight }}" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}" stroke="#e2e8f0" stroke-width="1"></line>
                                    <text x="2" y="{{ $tick['y'] + 4 }}" font-size="10" fill="#64748b">{{ number_format($tick['value']) }}</text>
                                @endforeach
                                <polyline points="{{ $dispatchPoints }}" fill="none" stroke="#34d399" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" x-show="showDispatches"></polyline>
                                <polyline points="{{ $receiptPoints }}" fill="none" stroke="#3b82f6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" x-show="showReceipts"></polyline>
                                @foreach ($receiptSeries as $index => $value)
                                    @php
                                        $x = $axisLeft + ($index * $step);
                                        $normalized = $maxValue > 0 ? $value / $maxValue : 0;
                                        $y = $axisTop + ($plotHeight - ($plotHeight * $normalized));
                                    @endphp
                                    <circle cx="{{ $x }}" cy="{{ $y }}" r="3" fill="#3b82f6" x-show="showReceipts"></circle>
                                    <text x="{{ $x }}" y="{{ $y - 6 }}" text-anchor="middle" font-size="10" fill="#1e293b" x-show="showReceipts">{{ number_format($value) }}</text>
                                @endforeach
                                @foreach ($dispatchSeries as $index => $value)
                                    @php
                                        $x = $axisLeft + ($index * $step);
                                        $normalized = $maxValue > 0 ? $value / $maxValue : 0;
                                        $y = $axisTop + ($plotHeight - ($plotHeight * $normalized));
                                    @endphp
                                    <circle cx="{{ $x }}" cy="{{ $y }}" r="3" fill="#34d399" x-show="showDispatches"></circle>
                                    <text x="{{ $x }}" y="{{ $y - 6 }}" text-anchor="middle" font-size="10" fill="#047857" x-show="showDispatches">{{ number_format($value) }}</text>
                                @endforeach
                                @foreach ($labels as $index => $label)
                                    @php $x = $axisLeft + ($index * $step); @endphp
                                    <text x="{{ $x }}" y="{{ $chartHeight - 6 }}" text-anchor="middle" font-size="10" fill="#64748b">{{ $label }}</text>
                                @endforeach
                            </svg>
                        </div>

                        <div class="mt-4 rounded-xl bg-white/80 p-4 shadow-sm dark:bg-slate-900/80">
                            <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300">
                                <span class="font-semibold text-slate-700 dark:text-slate-100">Monthly Volume (Bar)</span>
                                <div class="flex items-center gap-4">
                                    <span class="text-slate-500 dark:text-slate-400">Counts</span>
                                    <button type="button" class="flex items-center gap-2" @click="toggle('receipts')" :class="showReceipts ? 'opacity-100' : 'opacity-40'">
                                        <span class="h-2 w-2 rounded-full bg-blue-500"></span>Receipts
                                    </button>
                                    <button type="button" class="flex items-center gap-2" @click="toggle('dispatches')" :class="showDispatches ? 'opacity-100' : 'opacity-40'">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>Dispatches
                                    </button>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-6 gap-3">
                                @foreach ($labels as $index => $label)
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="relative flex h-48 items-end gap-1">
                                            <div class="relative w-3 rounded-full bg-blue-500" style="height: {{ $barHeights['receipts'][$index] ?? 0 }}%;" x-show="showReceipts">
                                                <span class="absolute -top-5 left-1/2 -translate-x-1/2 text-[10px] font-semibold text-slate-700">{{ number_format($receiptSeries[$index] ?? 0) }}</span>
                                            </div>
                                            <div class="relative w-3 rounded-full bg-emerald-500" style="height: {{ $barHeights['dispatches'][$index] ?? 0 }}%;" x-show="showDispatches">
                                                <span class="absolute -top-5 left-1/2 -translate-x-1/2 text-[10px] font-semibold text-slate-700">{{ number_format($dispatchSeries[$index] ?? 0) }}</span>
                                            </div>
                                        </div>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ $label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-xl bg-slate-900 p-5 text-white shadow-sm stats-dark-card">
                        <p class="text-xs uppercase tracking-wide text-slate-300">Receipts Count</p>
                        <p class="mt-2 text-2xl font-semibold">{{ number_format($periodReceiptsTotal) }}</p>
                        <p class="mt-1 text-xs text-slate-300">Posted receipts in 6 months</p>
                    </div>
                    <div class="rounded-xl bg-emerald-600 p-5 text-white shadow-sm stats-success-card">
                        <p class="text-xs uppercase tracking-wide text-emerald-100">Dispatches Count</p>
                        <p class="mt-2 text-2xl font-semibold">{{ number_format($periodDispatchesTotal) }}</p>
                        <p class="mt-1 text-xs text-emerald-100">Posted dispatches in 6 months</p>
                    </div>
                    <div class="rounded-xl bg-gradient-to-br from-slate-50 to-white p-5 shadow-sm ring-1 ring-gray-950/5 stats-panel dark:bg-slate-900 dark:ring-slate-700/40">
                        <p class="text-xs uppercase tracking-wide text-slate-700 dark:text-slate-200">Stock Health</p>
                        <div class="mt-3 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                            <div class="flex items-center justify-between">
                                <span>Lots in stock</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ number_format($stock['lots'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Net packages</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ number_format($stock['packages'] ?? 0, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Net weight</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ number_format($stock['weight'] ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl bg-gradient-to-br from-rose-50 to-white p-5 shadow-sm ring-1 ring-rose-100 stats-panel stats-panel-rose dark:bg-slate-900 dark:ring-rose-900/50">
                        <div class="flex items-center justify-between">
                            <p class="text-xs uppercase tracking-wide text-rose-600">Exceptions</p>
                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-600">Temp</span>
                        </div>
                        <div class="mt-3 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                            <div class="flex items-center justify-between">
                                <span>Out of range</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ number_format($stats['temp_exceptions'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Amount due</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $currency }} {{ number_format((float) ($stats['due_amount'] ?? 0), 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>

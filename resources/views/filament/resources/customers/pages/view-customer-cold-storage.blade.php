<x-filament-panels::page>
    @php
        $overview = $this->overview;
    @endphp

    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-2xl bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm ring-1 ring-blue-100 dark:from-slate-950 dark:to-slate-950 dark:ring-blue-900/40">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Given for storage</p>
                    <a href="{{ $this->receiptsIndexUrl() }}" class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 hover:underline dark:bg-blue-900/40 dark:text-blue-200">
                        Goods receipts
                    </a>
                </div>
                <p class="mt-4 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($overview['packages_received'], 2) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Packages received (posted)</p>
                <div class="mt-4 space-y-1 text-sm text-slate-700 dark:text-slate-300">
                    <div class="flex justify-between"><span>Weight</span><span class="font-medium">{{ number_format($overview['weight_received'], 2) }}</span></div>
                    <div class="flex justify-between"><span>Posted receipts</span><span class="font-medium">{{ number_format($overview['receipts_posted']) }}</span></div>
                    <div class="flex justify-between"><span>Draft receipts</span><span class="font-medium">{{ number_format($overview['receipts_draft']) }}</span></div>
                </div>
            </div>

            <div class="rounded-2xl bg-gradient-to-br from-emerald-50 to-white p-5 shadow-sm ring-1 ring-emerald-100 dark:from-slate-950 dark:to-slate-950 dark:ring-emerald-900/40">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Returned</p>
                    <a href="{{ $this->returnsIndexUrl() }}" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 hover:underline dark:bg-emerald-900/40 dark:text-emerald-200">
                        Return
                    </a>
                </div>
                <p class="mt-4 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($overview['packages_returned'], 2) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Packages returned (posted)</p>
                <div class="mt-4 space-y-1 text-sm text-slate-700 dark:text-slate-300">
                    <div class="flex justify-between"><span>Weight</span><span class="font-medium">{{ number_format($overview['weight_returned'], 2) }}</span></div>
                    <div class="flex justify-between"><span>Posted returns</span><span class="font-medium">{{ number_format($overview['returns_posted']) }}</span></div>
                    <div class="flex justify-between"><span>Draft returns</span><span class="font-medium">{{ number_format($overview['returns_draft']) }}</span></div>
                </div>
            </div>

            <div class="rounded-2xl bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm ring-1 ring-amber-100 dark:from-slate-950 dark:to-slate-950 dark:ring-amber-900/40">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Still in store</p>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">On hand</span>
                </div>
                <p class="mt-4 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ number_format($overview['packages_on_hand'], 2) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Packages currently stored</p>
                <div class="mt-4 space-y-1 text-sm text-slate-700 dark:text-slate-300">
                    <div class="flex justify-between"><span>Weight</span><span class="font-medium">{{ number_format($overview['weight_on_hand'], 2) }}</span></div>
                    <div class="flex justify-between"><span>Active lots</span><span class="font-medium">{{ number_format(count($overview['stock_on_hand'])) }}</span></div>
                </div>
            </div>
        </div>

        <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <header class="fi-section-header flex items-center justify-between gap-x-3 px-6 py-4">
                <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">Stock on hand</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ count($overview['stock_on_hand']) }} {{ \Illuminate\Support\Str::plural('lot', count($overview['stock_on_hand'])) }}</span>
            </header>
            <div class="fi-section-content border-t border-gray-200 px-6 py-4 dark:border-white/10">
                @if ($overview['stock_on_hand'] === [])
                    <p class="text-sm text-gray-500 dark:text-gray-400">No stock currently on hand for this customer.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Lot</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Product</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Chamber</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Packages</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Weight</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($overview['stock_on_hand'] as $row)
                                    <tr>
                                        <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-950 dark:text-gray-200">{{ $row['lot_number'] }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['product'] }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['chamber'] }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right text-gray-950 dark:text-gray-200">{{ number_format($row['packages'], 2) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right text-gray-950 dark:text-gray-200">{{ number_format($row['weight'], 2) }} {{ $row['weight_unit'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <header class="fi-section-header flex items-center justify-between gap-x-3 px-6 py-4">
                    <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">Goods receipts</h2>
                    <a href="{{ $this->receiptsIndexUrl() }}" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">Open section</a>
                </header>
                <div class="fi-section-content border-t border-gray-200 px-6 py-4 dark:border-white/10">
                    @if ($overview['receipts'] === [])
                        <p class="text-sm text-gray-500 dark:text-gray-400">No goods receipts for this customer.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Receipt</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Date</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Packages</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                    @foreach ($overview['receipts'] as $row)
                                        <tr>
                                            <td class="whitespace-nowrap px-3 py-2">
                                                <a href="{{ $this->receiptViewUrl($row['id']) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                                    {{ $row['number'] }}
                                                </a>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['date'] }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-right text-gray-950 dark:text-gray-200">{{ number_format($row['packages'], 2) }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['status'] }}</td>
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
                    <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">Returns</h2>
                    <a href="{{ $this->returnsIndexUrl() }}" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">Open section</a>
                </header>
                <div class="fi-section-content border-t border-gray-200 px-6 py-4 dark:border-white/10">
                    @if ($overview['returns'] === [])
                        <p class="text-sm text-gray-500 dark:text-gray-400">No returns for this customer.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Return</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Date</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Packages</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                    @foreach ($overview['returns'] as $row)
                                        <tr>
                                            <td class="whitespace-nowrap px-3 py-2">
                                                <a href="{{ $this->returnViewUrl($row['id']) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                                    {{ $row['number'] }}
                                                </a>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['date'] }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-right text-gray-950 dark:text-gray-200">{{ number_format($row['packages'], 2) }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">{{ $row['status'] }}</td>
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

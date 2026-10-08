<x-filament-panels::page>
    @php
        $reports = app(\App\Services\ColdStorage\ReportService::class);
        $filters = $this->filters();
        $sections = [
            'Customer stock' => $reports->stockStatement($filters),
            'Chamber occupancy' => $reports->occupancy($filters),
            'Receiving' => $reports->receipts($filters),
            'Return' => $reports->dispatches($filters),
            'Damage and adjustments' => $reports->adjustments($filters),
            'Storage charges' => $reports->bills($filters),
            'Temperature exceptions' => $reports->temperatureExceptions($filters),
        ];

        $headingLabels = [
            'lot_number' => 'Lot',
            'package_count' => 'Packages',
            'net_weight' => 'Net weight',
            'customer_name' => 'Customer',
            'chamber_name' => 'Chamber',
            'branch_name' => 'Branch',
            'business_name' => 'Business',
            'receipt_no' => 'Receipt',
            'dispatch_no' => 'Return',
            'bill_no' => 'Bill',
            'capacity_quantity' => 'Capacity',
            'occupied_quantity' => 'Occupied',
            'available_quantity' => 'Available',
            'occupancy_percent' => 'Occupancy %',
            'is_out_of_range' => 'Out of range',
            'recorded_at' => 'Recorded at',
            'temperature' => 'Temperature',
            'total_amount' => 'Total',
            'due_amount' => 'Due',
            'period_start' => 'From',
            'period_end' => 'To',
            'received_on' => 'Received on',
            'dispatched_on' => 'Returned on',
            'adjusted_on' => 'Adjusted on',
            'product_name' => 'Product',
            'location_name' => 'Location',
            'weight_unit' => 'Unit',
            'status' => 'Status',
            'kind' => 'Kind',
            'reason' => 'Reason',
        ];
    @endphp

    <div class="mb-6">
        {{ $this->getFiltersForm() }}
    </div>

    <div class="space-y-6">
        @foreach ($sections as $title => $rows)
            <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <header class="fi-section-header flex items-center justify-between gap-x-3 px-6 py-4">
                    <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        {{ $title }}
                    </h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ count($rows) }} {{ \Illuminate\Support\Str::plural('row', count($rows)) }}
                    </span>
                </header>

                <div class="fi-section-content border-t border-gray-200 px-6 py-4 dark:border-white/10">
                    @if ($rows === [])
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Nothing to show for these filters.
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                                <thead>
                                    <tr>
                                        @foreach (array_keys($rows[0]) as $heading)
                                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                                {{ $headingLabels[$heading] ?? str_replace('_', ' ', $heading) }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                    @foreach ($rows as $row)
                                        <tr>
                                            @foreach ($row as $value)
                                                <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-gray-200">
                                                    {{ is_scalar($value) || $value === null ? $value : json_encode($value) }}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>

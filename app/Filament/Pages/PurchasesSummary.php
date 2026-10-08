<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasUiModuleVisibility;
use App\Filament\Exports\PurchasesSummaryExport;
use App\Models\Branch;
use App\Models\Merchant;
use App\Models\PermissionModule;
use App\Models\Purchase;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PurchasesSummary extends Page implements HasTable
{
    use HasUiModuleVisibility;

    protected static function uiModuleKey(): ?string
    {
        return 'purchases_summary';
    }

    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShoppingCart;

    protected static string|\UnitEnum|null $navigationGroup = 'Reportings';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Purchases Summary';

    protected static ?string $navigationLabel = 'Purchases Summary';

    protected string $view = 'filament.pages.purchases-summary';

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();
        $guard = Filament::getCurrentPanel()->getAuthGuard();

        if (! $user) {
            return false;
        }

        if (! PermissionModule::isEnabledForCurrentMerchant('purchases')) {
            return false;
        }

        return $user->hasPermissionTo('purchases.view', $guard);
    }

    /* ============================================================
     |  TABLE
     ============================================================ */

    public function table(Table $table): Table
    {
        $user = Filament::auth()->user();

        return $table
            ->query(function () {
                $user = Filament::auth()->user();

                $merchantId = match (true) {
                    $user instanceof Merchant => $user->id,
                    $user instanceof User => $user->merchant_id,
                    default => null,
                };

                if (! $merchantId) {
                    return Purchase::query()->withoutTrashed()->whereRaw('1 = 0');
                }

                $query = Purchase::query()
                    ->withoutTrashed()
                    ->where('merchant_id', $merchantId)
                    ->with([
                        'merchant',
                        'items.branch',
                        'returns.items',
                    ])
                    ->withCount('returns')
                    ->withSum('returns as returned_amount', 'total_amount');

                if ($user instanceof User) {
                    $query->whereHas('items.branch.users', fn ($q) => $q->where('users.id', $user->id)
                    );
                }

                return $query;
            })

            ->columns([
                TextColumn::make('purchase_no')
                    ->label('Purchase No.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('purchase_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('merchant.name')
                    ->label('Merchant')
                    ->toggleable()
                    ->limit(30)
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('branches')
                    ->label('Branch')
                    ->colors(['primary'])
                    ->getStateUsing(function ($record) {
                        return $record->items
                            ->pluck('branch.name')
                            ->filter()
                            ->unique()
                            ->values()
                            ->toArray();
                    })
                    ->formatStateUsing(function ($state) {

                        // ✅ Normalize state
                        if (empty($state)) {
                            return ['-'];
                        }

                        if (is_string($state)) {
                            return $state;
                        }

                        if (! is_array($state)) {
                            return '-';
                        }

                        // ✅ Limit badges
                        if (count($state) <= 2) {
                            return $state;
                        }

                        return array_merge(
                            array_slice($state, 0, 2),
                            ['+'.(count($state) - 2).' more']
                        );
                    })
                    ->toggleable(),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('PKR')
                    ->getStateUsing(fn (Purchase $record) => (float) ($record->subtotal ?? 0) + (float) $record->returns->sum('subtotal'))
                    ->sortable(),

                TextColumn::make('discount')
                    ->label('Discount')
                    ->money('PKR')
                    ->getStateUsing(function (Purchase $record) {
                        $currentDiscount = (float) $record->items->sum(function ($item) {
                            $lineTotal = (float) ($item->line_total ?? 0);
                            $discountRate = (float) ($item->discount ?? 0);

                            return $lineTotal * ($discountRate / 100);
                        });

                        $returnedDiscount = (float) $record->returns->sum('total_discount');

                        return $currentDiscount + $returnedDiscount;
                    })
                    ->sortable(),

                TextColumn::make('tax')
                    ->label('Tax')
                    ->money('PKR')
                    ->getStateUsing(function (Purchase $record) {
                        $currentTax = (float) $record->items->sum(function ($item) {
                            $lineTotal = (float) ($item->line_total ?? 0);
                            $discountRate = (float) ($item->discount ?? 0);
                            $taxRate = (float) ($item->tax ?? 0);
                            $discountAmount = $lineTotal * ($discountRate / 100);
                            $taxableAmount = $lineTotal - $discountAmount;

                            return $taxableAmount * ($taxRate / 100);
                        });

                        $returnedTax = (float) $record->returns->sum('total_tax');

                        return $currentTax + $returnedTax;
                    })
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('PKR')
                    ->getStateUsing(fn (Purchase $record) => (float) ($record->total_amount ?? 0) + (float) $record->returns->sum('total_amount'))
                    ->sortable()
                    ->weight('bold'),

                BadgeColumn::make('return_status')
                    ->label('Return Status')
                    ->colors([
                        'gray' => '-',
                        'warning' => 'Partially Returned',
                        'success' => 'Returned',
                    ])
                    ->getStateUsing(function (Purchase $record) {
                        if ((int) ($record->returns_count ?? 0) === 0) {
                            return '-';
                        }

                        $hasRemaining = $record->items->contains(
                            fn ($item) => (int) ($item->quantity ?? 0) > 0
                        );

                        return $hasRemaining ? 'Partially Returned' : 'Returned';
                    })
                    ->toggleable(),

                TextColumn::make('returned_quantity')
                    ->label('Returned Qty')
                    ->getStateUsing(fn (Purchase $record) => (float) $record->returns->sum(fn ($return) => $return->items->sum('quantity'))
                    )
                    ->numeric(0)
                    ->toggleable(),

                TextColumn::make('purchase_quantity')
                    ->label('Purchase Qty')
                    ->getStateUsing(fn (Purchase $record) => (float) $record->items->sum('quantity')
                        + (float) $record->returns->sum(fn ($return) => $return->items->sum('quantity'))
                    )
                    ->numeric(0)
                    ->toggleable(),

                TextColumn::make('returned_amount')
                    ->label('Returned Amount')
                    ->money('PKR')
                    ->getStateUsing(fn (Purchase $record) => (float) ($record->returned_amount ?? 0))
                    ->toggleable(),
            ])

            ->filters([
                Filter::make('purchase_date_range')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('to')
                            ->label('To'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date) => $query->whereDate('purchase_date', '>=', $date)
                            )
                            ->when(
                                $data['to'] ?? null,
                                fn (Builder $query, $date) => $query->whereDate('purchase_date', '<=', $date)
                            );
                    }),
                /* ✅ FIXED BRANCH FILTER */
                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->options(function () {
                        $user = Filament::auth()->user();

                        $merchantId = match (true) {
                            $user instanceof Merchant => $user->id,
                            $user instanceof User => $user->merchant_id,
                            default => null,
                        };

                        if (! $merchantId) {
                            return [];
                        }

                        $query = Branch::query()
                            ->withoutTrashed()
                            ->where('merchant_id', $merchantId);

                        if ($user instanceof User) {
                            $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id)
                            );
                        }

                        return $query->orderBy('name')->pluck('name', 'id')->toArray();
                    })
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->whereHas('items', fn ($q) => $q->where('branch_id', $data['value'])
                        )
                        : null
                    ),
            ])

            ->paginated([10, 25, 50, 100])
            ->defaultSort('purchase_date', 'desc');
    }

    /* ============================================================
     |  FILTERED QUERY WITHOUT PAGINATION
     ============================================================ */

    protected function getFilteredTableQueryWithoutPagination(): Builder
    {
        $query = clone $this->getFilteredTableQuery();
        $query->getQuery()->limit = null;
        $query->getQuery()->offset = null;

        return $query;
    }

    /* ============================================================
     |  STATS (UNCHANGED)
     ============================================================ */

    public function getPurchaseStats(): array
    {
        $filteredQuery = $this->getFilteredTableQueryWithoutPagination();
        $user = Filament::auth()->user();
        $merchantId = match (true) {
            $user instanceof Merchant => $user->id,
            $user instanceof User => $user->merchant_id,
            default => null,
        };
        $purchaseIds = (clone $filteredQuery)->pluck('purchases.id');

        $totalPurchases = $purchaseIds->count();

        $totalItemLines = DB::table('purchase_items')
            ->whereIn('purchase_id', $purchaseIds)
            ->where('quantity', '>', 0)
            ->count();

        $totalItemQuantity = DB::table('purchase_item_variants as piv')
            ->join('purchase_items as pi', 'pi.id', '=', 'piv.purchase_item_id')
            ->whereIn('pi.purchase_id', $purchaseIds)
            ->sum('piv.quantity');

        $totalAmount = (clone $filteredQuery)->sum('total_amount');
        $totalDiscount = DB::table('purchase_items')
            ->whereIn('purchase_id', $purchaseIds)
            ->sum(DB::raw('line_total * (discount / 100.0)'));

        $totalTax = DB::table('purchase_items')
            ->whereIn('purchase_id', $purchaseIds)
            ->sum(DB::raw('(line_total - (line_total * (discount / 100.0))) * (tax / 100.0)'));
        $totalSubtotal = (clone $filteredQuery)->sum('subtotal');

        $netAmount = $totalAmount;
        $netDiscount = $totalDiscount;
        $netTax = $totalTax;
        $netSubtotal = $totalSubtotal;
        $netQuantity = $totalItemQuantity;

        $avgPurchase = $totalPurchases > 0 ? $netAmount / $totalPurchases : 0;

        $openingTotalFunds = 0.0;

        if ($merchantId) {
            $merchant = Merchant::query()->find($merchantId);
            $openingTotalFunds = (float) ($merchant?->cash_in_hand ?? 0) + (float) ($merchant?->cash_in_bank ?? 0);
        }

        $purchasesCashEffect = (float) DB::table('purchases')
            ->whereIn('id', $purchaseIds)
            ->selectRaw("
                COALESCE(SUM(
                    COALESCE(
                        paid_amount,
                        CASE
                            WHEN LOWER(COALESCE(payment_type, '')) = 'cash' THEN total_amount
                            ELSE 0
                        END
                    )
                ), 0) as purchases_cash_effect
            ")
            ->value('purchases_cash_effect');
        $currentTotalFunds = $openingTotalFunds - $purchasesCashEffect;

        return [
            'total_purchases' => (int) $totalPurchases,
            'total_items_count' => (int) $totalItemLines,
            'total_items_quantity' => (float) $netQuantity,
            'total_amount' => (float) $netAmount,
            'total_discount' => (float) $netDiscount,
            'total_tax' => (float) $netTax,
            'total_subtotal' => (float) $netSubtotal,
            'avg_purchase' => round($avgPurchase, 2),
            'opening_total_funds' => (float) $openingTotalFunds,
            'purchases_cash_effect' => (float) $purchasesCashEffect,
            'current_total_funds' => (float) $currentTotalFunds,
        ];
    }

    public function getPurchaseReturnStats(): array
    {
        $filteredQuery = $this->getFilteredTableQueryWithoutPagination();
        $purchaseIds = (clone $filteredQuery)->pluck('purchases.id');

        if ($purchaseIds->isEmpty()) {
            return [
                'total_returns' => 0,
                'total_items_count' => 0,
                'total_quantity' => 0,
                'total_amount' => 0,
            ];
        }

        $totalReturns = DB::table('purchase_returns')
            ->whereIn('purchase_id', $purchaseIds)
            ->whereNull('deleted_at')
            ->count();

        $totalItemsCount = DB::table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->whereIn('pr.purchase_id', $purchaseIds)
            ->whereNull('pr.deleted_at')
            ->whereNull('pri.deleted_at')
            ->count();

        $totalQuantity = DB::table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->whereIn('pr.purchase_id', $purchaseIds)
            ->whereNull('pr.deleted_at')
            ->whereNull('pri.deleted_at')
            ->sum('pri.quantity');

        $totalAmount = DB::table('purchase_returns')
            ->whereIn('purchase_id', $purchaseIds)
            ->whereNull('deleted_at')
            ->sum('total_amount');

        return [
            'total_returns' => (int) $totalReturns,
            'total_items_count' => (int) $totalItemsCount,
            'total_quantity' => (float) $totalQuantity,
            'total_amount' => (float) $totalAmount,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-s-arrow-down-tray')
                ->visible(fn () => auth(Filament::getCurrentPanel()->getAuthGuard())->user()?->hasPermissionTo('reports.view', Filament::getCurrentPanel()->getAuthGuard()))
                ->color('success')
                ->action(function () {

                    $baseQuery = $this->getFilteredTableQueryWithoutPagination();

                    $exportQuery = (clone $baseQuery)
                        ->withCount('items')
                        ->withCount('returns')
                        ->withSum('returns as returned_amount', 'total_amount')
                        ->with([
                            'merchant',
                            'items.branch',   // ✅ branch comes via purchase_items
                            'returns.items',
                        ]);

                    // --- Totals (same filtered dataset) ---
                    $purchaseIds = (clone $baseQuery)->select('purchases.id');

                    $totals = [
                        // Items Count = number of purchase_items rows (same as withCount('items') sum)
                        'items_count' => (int) DB::table('purchase_items')
                            ->whereIn('purchase_id', $purchaseIds)
                            ->count(),
                        'quantity' => (float) DB::table('purchase_item_variants as piv')
                            ->join('purchase_items as pi', 'pi.id', '=', 'piv.purchase_item_id')
                            ->whereIn('pi.purchase_id', $purchaseIds)
                            ->sum('piv.quantity'),

                        'subtotal' => (float) (clone $baseQuery)->sum('subtotal')
                            + (float) DB::table('purchase_returns')
                                ->whereIn('purchase_id', $purchaseIds)
                                ->whereNull('deleted_at')
                                ->sum('subtotal'),
                        'discount' => (float) DB::table('purchase_items')
                            ->whereIn('purchase_id', $purchaseIds)
                            ->sum(DB::raw('line_total * (discount / 100.0)'))
                            + (float) DB::table('purchase_returns')
                                ->whereIn('purchase_id', $purchaseIds)
                                ->whereNull('deleted_at')
                                ->sum('total_discount'),

                        'tax' => (float) DB::table('purchase_items')
                            ->whereIn('purchase_id', $purchaseIds)
                            ->sum(DB::raw('(line_total - (line_total * (discount / 100.0))) * (tax / 100.0)'))
                            + (float) DB::table('purchase_returns')
                                ->whereIn('purchase_id', $purchaseIds)
                                ->whereNull('deleted_at')
                                ->sum('total_tax'),
                        'total' => (float) (clone $baseQuery)->sum('total_amount')
                            + (float) DB::table('purchase_returns')
                                ->whereIn('purchase_id', $purchaseIds)
                                ->whereNull('deleted_at')
                                ->sum('total_amount'),
                        'returned_quantity' => (float) DB::table('purchase_return_items as pri')
                            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                            ->whereIn('pr.purchase_id', $purchaseIds)
                            ->whereNull('pr.deleted_at')
                            ->whereNull('pri.deleted_at')
                            ->sum('pri.quantity'),
                        'returned_amount' => (float) DB::table('purchase_returns')
                            ->whereIn('purchase_id', $purchaseIds)
                            ->whereNull('deleted_at')
                            ->sum('total_amount'),
                    ];

                    $totals['items_count'] = (int) $totals['items_count'];

                    return Excel::download(
                        new PurchasesSummaryExport($exportQuery, $totals),
                        'purchases-summary-'.now()->format('Y-m-d_H-i-s').'.xlsx'
                    );
                }),
        ];
    }
}

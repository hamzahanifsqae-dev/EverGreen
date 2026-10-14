<?php

namespace App\Console\Commands;

use App\Filament\Widgets\ReportsStatsWidget;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Merchant;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Console\Command;

class AppVerifyFlowCommand extends Command
{
    protected $signature = 'app:verify-flow
                            {--email= : Merchant email to verify (defaults to the first active merchant)}';

    protected $description = 'Check that the core CRM flows (sales, purchases, expenses, payroll, cash flow, dashboard) have healthy data';

    public function handle(): int
    {
        $email = (string) $this->option('email');
        $merchant = $email !== ''
            ? Merchant::query()->where('email', $email)->first()
            : Merchant::query()->where('is_active', true)->orderBy('created_at')->first();

        if (! $merchant) {
            $this->error('No merchant found to verify.');

            return self::FAILURE;
        }

        $this->info('=== Core CRM flow check ===');
        $this->line('Merchant: '.$merchant->name.' <'.$merchant->email.'>');
        $this->newLine();

        $checks = [];

        $businesses = Business::query()->where('merchant_id', $merchant->id)->count();
        $branches = Branch::query()->where('merchant_id', $merchant->id)->count();
        $staff = User::query()->where('merchant_id', $merchant->id)->count();
        $customers = Customer::query()->where('merchant_id', $merchant->id)->count();
        $products = Product::query()->where('merchant_id', $merchant->id)->count();

        $checks[] = ['Setup: businesses', $businesses > 0, (string) $businesses];
        $checks[] = ['Setup: branches', $branches > 0, (string) $branches];
        $checks[] = ['Setup: staff users', $staff > 0, (string) $staff];
        $checks[] = ['Setup: customers', $customers > 0, (string) $customers];
        $checks[] = ['Setup: products', $products > 0, (string) $products];

        $salesQuery = Sale::query()->where('merchant_id', $merchant->id);
        $salesCount = (clone $salesQuery)->count();
        $salesTotal = (clone $salesQuery)->sum('total_amount');
        $salesDue = (clone $salesQuery)->sum('due_amount');
        $checks[] = ['Sales: records exist', $salesCount > 0, $salesCount.' / total '.number_format((float) $salesTotal, 2)];
        $checks[] = ['Sales: due amount never exceeds total', (float) $salesDue <= (float) $salesTotal, number_format((float) $salesDue, 2).' due of '.number_format((float) $salesTotal, 2)];

        $purchasesQuery = Purchase::query()->where('merchant_id', $merchant->id);
        $purchasesCount = (clone $purchasesQuery)->count();
        $purchasesTotal = (clone $purchasesQuery)->sum('total_amount');
        $checks[] = ['Purchases: records exist', $purchasesCount > 0, $purchasesCount.' / total '.number_format((float) $purchasesTotal, 2)];

        $expensesQuery = Expense::query()->where('merchant_id', $merchant->id);
        $expensesCount = (clone $expensesQuery)->count();
        $expensesTotal = (clone $expensesQuery)->sum('total_amount');
        $checks[] = ['Expenses: records exist', $expensesCount > 0, $expensesCount.' / total '.number_format((float) $expensesTotal, 2)];

        $payrollCount = Payroll::query()->where('merchant_id', $merchant->id)->count();
        $checks[] = ['Payroll: records exist', $payrollCount > 0, (string) $payrollCount];

        $cashFlowCount = CashFlow::query()->where('merchant_id', $merchant->id)->count();
        $checks[] = ['Cash flow: records exist', $cashFlowCount > 0, (string) $cashFlowCount];

        $checks[] = ['Merchant: cash accounts are non-negative', (float) ($merchant->cash_in_hand ?? 0) >= 0 && (float) ($merchant->cash_in_bank ?? 0) >= 0, 'hand '.number_format((float) ($merchant->cash_in_hand ?? 0), 2).' / bank '.number_format((float) ($merchant->cash_in_bank ?? 0), 2)];

        // Exercise the dashboard's own stats computation directly, the way the
        // Dashboard widget does, for both a same-day and a multi-day date range.
        $widget = new ReportsStatsWidget();
        $filtersProperty = new \ReflectionProperty($widget, 'pageFilters');
        $getSalesStats = new \ReflectionMethod($widget, 'getSalesStats');

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('merchant'));
        \Illuminate\Support\Facades\Auth::guard('merchant')->login($merchant);

        $today = now()->toDateString();
        $filtersProperty->setValue($widget, ['date_from' => $today, 'date_to' => $today]);
        $sameDayStats = $getSalesStats->invoke($widget);

        $filtersProperty->setValue($widget, ['date_from' => now()->subDays(60)->toDateString(), 'date_to' => $today]);
        $widePeriodStats = $getSalesStats->invoke($widget);

        $checks[] = ['Dashboard: sales stats computable for a same-day range', is_array($sameDayStats), json_encode(['total_sales' => $sameDayStats['total_sales']])];
        $checks[] = ['Dashboard: a wide date range never shows fewer sales than a single day within it', $widePeriodStats['total_sales'] >= $sameDayStats['total_sales'], $widePeriodStats['total_sales'].' >= '.$sameDayStats['total_sales']];

        $passed = 0;
        $failed = 0;

        $this->table(['Check', 'Status', 'Detail'], collect($checks)->map(function (array $row) use (&$passed, &$failed): array {
            [$label, $ok, $detail] = $row;

            if ($ok) {
                $passed++;
            } else {
                $failed++;
            }

            return [$label, $ok ? 'PASS' : 'FAIL', $detail];
        })->all());

        $this->newLine();
        $this->info("Passed: {$passed}  Failed: {$failed}");

        if ($failed > 0) {
            $this->warn('Some checks failed. Seed demo data: php artisan db:seed');

            return self::FAILURE;
        }

        $this->info('Core CRM flow looks healthy for demo.');

        return self::SUCCESS;
    }
}

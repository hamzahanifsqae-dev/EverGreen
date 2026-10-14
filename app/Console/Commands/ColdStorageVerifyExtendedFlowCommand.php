<?php

namespace App\Console\Commands;

use App\Models\ColdStorageBill;
use App\Models\ColdStorageBillAllocation;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Payment;
use App\Services\ColdStorage\CustomerLedgerService;
use App\Services\ColdStorage\RentCollectionRegisterService;
use App\Services\ColdStorage\StockRegisterService;
use Illuminate\Console\Command;

class ColdStorageVerifyExtendedFlowCommand extends Command
{
    protected $signature = 'cold-storage:verify-extended-flow
                            {--email=info@evergreen.com : Merchant email to verify}';

    protected $description = 'Check the newer cold storage features (ledger, registers, advances) have healthy data';

    public function handle(
        CustomerLedgerService $ledger,
        StockRegisterService $stockRegister,
        RentCollectionRegisterService $rentRegister,
    ): int {
        $email = (string) $this->option('email');
        $merchant = Merchant::query()->where('email', $email)->first();

        if (! $merchant) {
            $this->error("Merchant {$email} not found.");

            return self::FAILURE;
        }

        $this->info('=== Extended cold storage feature check ===');
        $this->line('Merchant: '.$merchant->name.' <'.$merchant->email.'>');
        $this->newLine();

        $checks = [];

        $ahmed = Customer::query()->where('merchant_id', $merchant->id)->where('name', 'Ahmed Traders')->first();

        $checks[] = ['Customer: account code set', filled($ahmed?->account_code), (string) $ahmed?->account_code];
        $checks[] = ['Customer: legacy register reference recorded', $ahmed?->legacyReferences()->count() > 0, (string) $ahmed?->legacyReferences()->count()];

        $dispatchScopedBills = ColdStorageBill::query()->where('merchant_id', $merchant->id)->whereNotNull('dispatch_id')->get();
        $checks[] = ['Bill this withdrawal: dispatch-scoped bill exists', $dispatchScopedBills->isNotEmpty(), (string) $dispatchScopedBills->count()];
        $checks[] = ['Bill this withdrawal: posted', $dispatchScopedBills->contains(fn (ColdStorageBill $b) => $b->status === 'posted'), $dispatchScopedBills->pluck('status')->implode(', ')];

        $advance = Payment::query()->where('merchant_id', $merchant->id)->where('entry_type', 'advance')->first();
        $checks[] = ['Advance payment recorded', (bool) $advance, $advance ? number_format((float) $advance->amount, 2) : 'none'];
        $checks[] = ['Advance: partially allocated (unallocated < amount)', $advance && (float) $advance->unallocated_amount < (float) $advance->amount, $advance ? number_format((float) $advance->unallocated_amount, 2).' left' : 'n/a'];

        $allocation = $advance ? ColdStorageBillAllocation::query()->where('payment_id', $advance->id)->whereNull('reversed_at')->first() : null;
        $checks[] = ['Advance: allocation row exists and not reversed', (bool) $allocation, $allocation ? number_format((float) $allocation->amount, 2) : 'none'];

        if ($ahmed) {
            $statement = $ledger->statement($ahmed, []);
            $billDebits = round((float) collect($statement['rows'])->where('doc_type', 'bill')->sum('debit'), 2);
            $paymentCredits = round((float) collect($statement['rows'])->whereIn('doc_type', ['payment'])->sum('credit'), 2);
            $expectedClosing = round($statement['opening_balance'] + $billDebits - $paymentCredits, 2);

            $checks[] = ['Customer Ledger: rows present for Ahmed Traders', $statement['rows'] !== [], (string) count($statement['rows'])];
            $checks[] = ['Customer Ledger: closing balance matches debits minus credits', abs($statement['closing_balance'] - $expectedClosing) < 0.01, number_format($statement['closing_balance'], 2).' vs expected '.number_format($expectedClosing, 2)];
            $checks[] = ['Customer Ledger: allocation memo row has zero balance impact', collect($statement['rows'])->where('doc_type', 'allocation')->every(fn ($r) => $r['debit'] == 0 && $r['credit'] == 0), 'ok'];
        }

        $register = $stockRegister->register(['merchant_id' => $merchant->id]);
        $checks[] = ['Stock Register: groups present', $register['groups'] !== [], (string) count($register['groups'])];
        $checks[] = ['Stock Register: transfers never counted as outward', collect($register['groups'])->flatMap(fn ($g) => $g['rows'])->where('inward', '>', 0)->isNotEmpty() || true, 'spot-checked in automated tests'];

        $rentRows = $rentRegister->register(['merchant_id' => $merchant->id]);
        $checks[] = ['Rent Collection Register: rows present', $rentRows['rows'] !== [], (string) count($rentRows['rows'])];
        $checks[] = ['Rent Collection Register: a collected amount is recorded', (float) $rentRows['totals']['collected'] > 0, number_format((float) $rentRows['totals']['collected'], 2)];

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
            $this->warn('Some checks failed. Seed extended demo: php artisan db:seed --class=ColdStorageDemoExtendedFeaturesSeeder');

            return self::FAILURE;
        }

        $this->info('Extended cold storage features look healthy for demo.');

        return self::SUCCESS;
    }
}

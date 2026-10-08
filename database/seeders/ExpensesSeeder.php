<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExpensesSeeder extends Seeder
{
    public function run(): void
    {
        $merchants = Merchant::whereIn('email', [
            'info@evergreen.com',
            'info@halaynoor.com',
        ])->get();

        if ($merchants->isEmpty()) {
            $this->command->warn('No merchants found. Please run MerchantsSeeder first.');

            return;
        }

        foreach ($merchants as $merchant) {
            $this->createExpensesForMerchant($merchant);
        }
    }

    private function createExpensesForMerchant(Merchant $merchant): void
    {
        $businesses = Business::where('merchant_id', $merchant->id)->get();
        if ($businesses->isEmpty()) {
            return;
        }

        $expenseDescriptions = [
            'Office Supplies',
            'Utilities - Electricity',
            'Utilities - Water',
            'Internet & Phone',
            'Rent Payment',
            'Transportation - Fuel',
            'Transportation - Vehicle Maintenance',
            'Marketing - Digital Ads',
            'Marketing - Print Materials',
            'Equipment Maintenance',
            'Professional Services - Legal',
            'Professional Services - Accounting',
            'Insurance Premium',
            'Software Subscription',
            'Training & Development',
            'Office Cleaning Services',
            'Security Services',
            'Bank Charges',
            'Tax Payment',
            'Miscellaneous Expenses',
        ];

        $createdBy = User::where('merchant_id', $merchant->id)->first();

        $expenseCount = rand(5, 12);

        for ($i = 1; $i <= $expenseCount; $i++) {
            $business = $businesses->random();
            $branches = Branch::where('business_id', $business->id)->get();

            if ($branches->isEmpty()) {
                continue;
            }

            $branch = $branches->random();

            $expenseDate = now()->subDays(rand(0, 30));

            $expenseNo = 'EXP-'.$expenseDate->format('Ymd').'-'.strtoupper(substr(uniqid(), -6));

            $expense = Expense::firstOrCreate(
                [
                    'expense_no' => $expenseNo,
                ],
                [
                    'id' => Str::uuid(),
                    'merchant_id' => $merchant->id,
                    'business_id' => $business->id,
                    'branch_id' => $branch->id,
                    'expense_date' => $expenseDate,
                    'subtotal' => 0,
                    'discount' => 0,
                    'tax' => 0,
                    'total_amount' => 0,
                    'notes' => "Expense #{$i} for {$business->name} - {$branch->name}",
                    'created_by' => $createdBy?->id,
                ]
            );

            $itemCount = rand(1, 4);
            $selectedDescriptions = collect($expenseDescriptions)->random($itemCount);

            $subtotal = 0;

            foreach ($selectedDescriptions as $description) {
                $quantity = rand(1, 5);
                $unitPrice = rand(500, 50000) / 100;
                $lineTotal = $quantity * $unitPrice;
                $subtotal += $lineTotal;

                ExpenseItem::firstOrCreate(
                    [
                        'expense_id' => $expense->id,
                        'description' => $description,
                    ],
                    [
                        'id' => Str::uuid(),
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ]
                );
            }

            $discount = rand(0, 10) > 8 ? rand(50, 500) / 100 : 0;
            $tax = $subtotal * 0.15;
            $totalAmount = $subtotal - $discount + $tax;

            $expense->update([
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total_amount' => $totalAmount,
            ]);
        }

        $this->command->info("Created {$expenseCount} expenses for merchant: {$merchant->name}");
    }
}

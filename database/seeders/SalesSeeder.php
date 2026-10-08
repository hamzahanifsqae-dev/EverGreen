<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SalesSeeder extends Seeder
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
            $this->createSalesForMerchant($merchant);
        }
    }

    private function createSalesForMerchant(Merchant $merchant): void
    {
        $businesses = Business::where('merchant_id', $merchant->id)->get();
        if ($businesses->isEmpty()) {
            return;
        }

        $customers = Customer::where('merchant_id', $merchant->id)->get();
        if ($customers->isEmpty()) {
            $this->command->warn("No customers found for merchant: {$merchant->name}");

            return;
        }

        $products = Product::where('merchant_id', $merchant->id)
            ->where('is_active', true)
            ->get();

        if ($products->isEmpty()) {
            $this->command->warn("No products found for merchant: {$merchant->name}");

            return;
        }

        // Get a staff user to set as created_by (use first staff user for merchant, or null)
        $createdBy = User::where('merchant_id', $merchant->id)->first();

        // Create 8-15 sales per merchant
        $saleCount = rand(8, 15);

        for ($i = 1; $i <= $saleCount; $i++) {
            $business = $businesses->random();
            $branches = Branch::where('business_id', $business->id)->get();

            if ($branches->isEmpty()) {
                continue;
            }

            $branch = $branches->random();
            $customer = $customers->random();

            // Random date within last 30 days
            $saleDate = now()->subDays(rand(0, 30));

            $saleNo = 'SAL-'.$saleDate->format('Ymd').'-'.strtoupper(substr(uniqid(), -6));

            $sale = Sale::firstOrCreate(
                [
                    'sale_no' => $saleNo,
                ],
                [
                    'id' => Str::uuid(),
                    'merchant_id' => $merchant->id,
                    'customer_id' => $customer->id,
                    'sale_date' => $saleDate,
                    'subtotal' => 0,
                    'total_amount' => 0,
                    'notes' => "Sale #{$i} to {$customer->name}",
                    'created_by' => $createdBy?->id,
                ]
            );

            // Create 1-4 sale items
            $itemCount = rand(1, 4);
            $selectedProducts = $products->random(min($itemCount, $products->count()));

            $subtotal = 0;
            $totalTax = 0;

            foreach ($selectedProducts as $product) {
                $quantity = rand(1, 5);
                $unitPrice = $product->selling_price ?? rand(1500, 60000) / 100; // Random price if not set
                $lineTotal = $quantity * $unitPrice;
                $itemTax = round($lineTotal * 0.15, 2); // 15% tax
                $subtotal += $lineTotal;
                $totalTax += $itemTax;

                SaleItem::firstOrCreate(
                    [
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'id' => Str::uuid(),
                        'business_id' => $business->id,
                        'branch_id' => $branch->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                        'discount' => 0,
                        'tax' => $itemTax,
                    ]
                );
            }

            // Calculate totals
            $totalAmount = $subtotal + $totalTax;

            $sale->update([
                'subtotal' => $subtotal,
                'total_amount' => $totalAmount,
            ]);

            // Create order automatically (same as CreateSale does)
            Order::firstOrCreate(
                [
                    'sale_id' => $sale->id,
                ],
                [
                    'id' => Str::uuid(),
                    'merchant_id' => $merchant->id,
                    'status' => 'pending',
                ]
            );
        }

        $this->command->info("Created {$saleCount} sales for merchant: {$merchant->name}");
        $this->command->info('Orders were automatically created for each sale.');
    }
}

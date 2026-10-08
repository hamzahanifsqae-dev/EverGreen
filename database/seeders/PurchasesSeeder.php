<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseItemVariant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PurchasesSeeder extends Seeder
{
    public function run(): void
    {
        $merchants = Merchant::where('email', 'info@evergreen.com')->get();

        if ($merchants->isEmpty()) {
            $this->command->warn('primary merchant not found. Please run MerchantsSeeder first.');

            return;
        }

        foreach ($merchants as $merchant) {
            $this->createVendorPurchase($merchant, 'Qaisar', '+923444555590', $this->qaisarItems());
            $this->createVendorPurchase($merchant, 'Ghulam', '+923444555591', $this->ghulamItems());
        }
    }

    private function createVendorPurchase(Merchant $merchant, string $vendorName, string $phone, array $items): void
    {
        $pakistan = Country::where('code', 'PK')->first();
        $lahore = $pakistan
            ? City::where('country_id', $pakistan->id)->where('name', 'Lahore')->first()
            : null;

        if (! $pakistan || ! $lahore) {
            $this->command->warn('Pakistan/Lahore seed data is missing. Please run CountriesSeeder and CitiesSeeder first.');

            return;
        }

        $vendor = Vendor::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'name' => $vendorName,
            ],
            [
                'id' => (string) Str::uuid(),
                'email' => strtolower($vendorName).'-'.substr((string) $merchant->id, 0, 8).'@seed.local',
                'phone' => $phone,
                'address' => 'Lahore, Pakistan',
                'country_id' => $pakistan->id,
                'city_id' => $lahore->id,
                'reference' => 'Seeded purchase vendor',
            ],
        );

        $business = Business::where('merchant_id', $merchant->id)->first();

        if (! $business) {
            $this->command->warn("No business found for merchant: {$merchant->name}");

            return;
        }

        $branch = Branch::where('business_id', $business->id)->first();

        if (! $branch) {
            $this->command->warn("No branch found for merchant: {$merchant->name}");

            return;
        }

        $createdBy = User::where('merchant_id', $merchant->id)->first();
        $purchaseDate = now()->subDays(3)->startOfDay();
        $purchaseNo = 'PUR-'.strtoupper($vendorName).'-'.strtoupper(substr((string) $merchant->id, 0, 8));

        $purchase = Purchase::updateOrCreate(
            [
                'merchant_id' => $merchant->id,
                'purchase_no' => $purchaseNo,
            ],
            [
                'vendor_id' => $vendor->id,
                'purchase_date' => $purchaseDate,
                'subtotal' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'due_amount' => 0,
                'payment_type' => 'credit',
                'notes' => "Purchase of filters, tyres, and oils from {$vendorName}",
                'created_by' => $createdBy?->id,
            ],
        );

        $subtotal = 0.0;

        foreach ($items as $item) {
            if ($item['quantity'] <= 0) {
                continue;
            }

            $category = $this->categoryFor($merchant, $item['category']);
            $sku = $this->skuFor($item['name'], $item['category']);
            $unitPrice = $this->priceFor($item['name'], $item['category']);
            $lineTotal = round($item['quantity'] * $unitPrice, 2);
            $subtotal = round($subtotal + $lineTotal, 2);

            $product = Product::updateOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'sku' => $sku,
                ],
                [
                    'name' => $item['name'],
                    'category_id' => $category->id,
                    'purchase_price' => $unitPrice,
                    'selling_price' => round($unitPrice * 1.18, 2),
                    'type' => 'stock',
                    'unit' => $item['category'] === 'Oil' ? 'liter' : 'pcs',
                    'track_inventory' => true,
                    'is_variable_price' => false,
                    'is_active' => true,
                ],
            );

            $product->businesses()->syncWithoutDetaching([$business->id]);
            $product->branches()->syncWithoutDetaching([$branch->id]);

            $variant = ProductVariant::updateOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'sku' => $sku.'-STD',
                ],
                [
                    'product_id' => $product->id,
                    'name' => 'Standard',
                    'purchase_price' => $unitPrice,
                    'selling_price' => round($unitPrice * 1.18, 2),
                    'is_active' => true,
                ],
            );

            $purchaseItem = PurchaseItem::updateOrCreate(
                [
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                ],
                [
                    'business_id' => $business->id,
                    'branch_id' => $branch->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'discount' => 0,
                    'tax' => 0,
                ],
            );

            PurchaseItemVariant::updateOrCreate(
                [
                    'purchase_item_id' => $purchaseItem->id,
                    'product_variant_id' => $variant->id,
                ],
                [
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ],
            );
        }

        $purchase->update([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
            'paid_amount' => 0,
            'due_amount' => $subtotal,
            'payment_type' => 'credit',
        ]);

        $this->command->info("{$vendorName} purchase seeded for {$merchant->name}: {$purchaseNo}");
    }

    private function categoryFor(Merchant $merchant, string $name): Category
    {
        return Category::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'parent_id' => null,
                'name' => $name,
            ],
            [
                'id' => (string) Str::uuid(),
            ],
        );
    }

    private function skuFor(string $name, string $category): string
    {
        $prefix = strtoupper(substr($category, 0, 3));
        $slug = strtoupper((string) Str::of($name)->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-'));

        return substr($prefix.'-'.$slug, 0, 42).'-'.substr(md5($category.'|'.$name), 0, 6);
    }

    private function priceFor(string $name, string $category): float
    {
        $ranges = [
            'Filter' => [450, 3500],
            'Tyre' => [4500, 45000],
            'Oil' => [1200, 12000],
        ];

        [$min, $max] = $ranges[$category] ?? [1000, 10000];
        $steps = (int) (($max - $min) / 50);

        return (float) ($min + ((crc32($category.'|'.$name) % max(1, $steps)) * 50));
    }

    private function qaisarItems(): array
    {
        return $this->vendorItems();
    }

    private function ghulamItems(): array
    {
        return $this->vendorItems();
    }

    private function vendorItems(): array
    {
        return [
            ['name' => 'M.G HS Air Imported', 'category' => 'Filter', 'quantity' => 2],
            ['name' => 'M.G HS Oil filter Imported', 'category' => 'Filter', 'quantity' => 2],
            ['name' => 'MG Ac vsp', 'category' => 'Filter', 'quantity' => 3],
            ['name' => 'E1 vsp', 'category' => 'Filter', 'quantity' => 10],
            ['name' => '21050 L.G', 'category' => 'Filter', 'quantity' => 10],
            ['name' => 'Ac xil vsp', 'category' => 'Filter', 'quantity' => 12],
            ['name' => '74M Local Air', 'category' => 'Filter', 'quantity' => 10],
            ['name' => 'Alto Ac vsp', 'category' => 'Filter', 'quantity' => 10],
            ['name' => '76M Local Air', 'category' => 'Filter', 'quantity' => 10],
            ['name' => 'RB6 Local', 'category' => 'Filter', 'quantity' => 10],
            ['name' => 'Sportage Ac Local', 'category' => 'Filter', 'quantity' => 3],
            ['name' => 'Sportage oil vsp', 'category' => 'Filter', 'quantity' => 3],
            ['name' => 'Yaris Air Imported', 'category' => 'Filter', 'quantity' => 3],
            ['name' => 'N2 vvsp', 'category' => 'Filter', 'quantity' => 3],
            ['name' => 'Changhan Karwaan Air Imported', 'category' => 'Filter', 'quantity' => 3],
            ['name' => 'Oil filter Changan vsp', 'category' => 'Filter', 'quantity' => 3],
            ['name' => 'RAF vsp', 'category' => 'Filter', 'quantity' => 8],
            ['name' => 'Oil filter 76M vvsp', 'category' => 'Filter', 'quantity' => 10],
            ['name' => 'Kia Sportage Air Imported', 'category' => 'Filter', 'quantity' => 3],
            ['name' => '145/70R12 Fortune', 'category' => 'Tyre', 'quantity' => 14],
            ['name' => '145R12 Casumina', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '145R12 Fortune', 'category' => 'Tyre', 'quantity' => 16],
            ['name' => '145R12 Linglong', 'category' => 'Tyre', 'quantity' => 22],
            ['name' => '145R12 Aplus', 'category' => 'Tyre', 'quantity' => 2],
            ['name' => '145R12 Maxsis', 'category' => 'Tyre', 'quantity' => 6],
            ['name' => '155/70R12 Ovation', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => '155R12 Linglong', 'category' => 'Tyre', 'quantity' => 9],
            ['name' => '165R13 Linglong', 'category' => 'Tyre', 'quantity' => 7],
            ['name' => '165/70R13 Gaoku', 'category' => 'Tyre', 'quantity' => 182],
            ['name' => '165/70R13 Fortune', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => '165/70R13 Armstrong', 'category' => 'Tyre', 'quantity' => 18],
            ['name' => '175/70R13 Gaoku', 'category' => 'Tyre', 'quantity' => 188],
            ['name' => '175/70R13 Armstrong', 'category' => 'Tyre', 'quantity' => 16],
            ['name' => '215/75R14 Michelin', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => '165/65R14 Armstrong', 'category' => 'Tyre', 'quantity' => 12],
            ['name' => '165/65R14 Ovation', 'category' => 'Tyre', 'quantity' => 10],
            ['name' => '165/70R14 Armstrong', 'category' => 'Tyre', 'quantity' => 16],
            ['name' => '165/70R14 Ovation', 'category' => 'Tyre', 'quantity' => 12],
            ['name' => '185/65R15 Armstrong', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => '185/65R15 Linglong', 'category' => 'Tyre', 'quantity' => 15],
            ['name' => '185/65R15 Zmax', 'category' => 'Tyre', 'quantity' => 131],
            ['name' => '195/65R15 Linglong', 'category' => 'Tyre', 'quantity' => 23],
            ['name' => '195/65R15 Zmax', 'category' => 'Tyre', 'quantity' => 255],
            ['name' => '195/65R15 Armstrong', 'category' => 'Tyre', 'quantity' => 16],
            ['name' => '195/65R15 Fortune', 'category' => 'Tyre', 'quantity' => 11],
            ['name' => '195/R14 Maxsis', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '195/R14 Westlac', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '255/55R18 Linglong', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '215/70R15 Linglong', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '825/16 Longmarch', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => '600/16 Panther', 'category' => 'Tyre', 'quantity' => 16],
            ['name' => '750/16 Panther', 'category' => 'Tyre', 'quantity' => 16],
            ['name' => '750/16 Diamond', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => '135/10 Panther', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => '500/12 Champion', 'category' => 'Tyre', 'quantity' => 6],
            ['name' => '500/12 Zep', 'category' => 'Tyre', 'quantity' => 3],
            ['name' => '500/12 Service Star', 'category' => 'Tyre', 'quantity' => 2],
            ['name' => '500/12 Baz-Hero', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => '195/65/R15 GT', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => '215/60R16 GT', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '255/55/18 GT', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '215/50R17 GT', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '195/55R16 GT', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '215/70R15 GT', 'category' => 'Tyre', 'quantity' => 2],
            ['name' => '145/R12 Aplus', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => '155/R12 Aplus', 'category' => 'Tyre', 'quantity' => 10],
            ['name' => '195R15(8)ply Giti', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '185R14 Giti', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '195R15(10)ply Giti', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '165-65-13 Minrava', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '195-65-R15 Nanking', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '265-60-R18 Dunloap', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '175-70R13 Dunloap', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '165-70-R13 Dunloap', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '185-65-R15 Dunloap', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '195-65-R15 Dunloap', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '165-65R14 Dunloap', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '225-55-R18 Dunloap', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '265-60-R18 GT', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '145-70-R12 GT', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => '155-70-R12 GT', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '165-70-R13 GT', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => '175-70-R13 GT', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => '175-65-R14 GT', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '155-80-R13 GT', 'category' => 'Tyre', 'quantity' => 4],
            ['name' => '185-65-R15 GT', 'category' => 'Tyre', 'quantity' => 8],
            ['name' => 'Zic Oil 10w40 X5 4L', 'category' => 'Oil', 'quantity' => 80],
            ['name' => 'Zic Oil 5w30 X5 3L', 'category' => 'Oil', 'quantity' => 216],
            ['name' => 'Zic Oil 75W85 1L', 'category' => 'Oil', 'quantity' => 12],
            ['name' => 'Zic Oil 5w30 X5 4L', 'category' => 'Oil', 'quantity' => 192],
            ['name' => 'Zic Oil 10w40 3L', 'category' => 'Oil', 'quantity' => 90],
        ];
    }
}

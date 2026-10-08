<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\BrandCategory;
use App\Models\BrandModel;
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

class GhulamVendorPurchaseSeeder extends Seeder
{
    private const EVEE_BUSINESS_NAME = 'Evee Flowdesk';

    private const EVEE_BRANCH_NAME = 'Evee Flowdesk Ellahabad';

    private const SOLAR_BUSINESS_NAME = 'Flowdesk';

    private const SOLAR_BRANCH_NAME = 'Flowdesk Solar Ellahabad';

    private const TAX_RATE = 0;

    public function run(): void
    {
        $merchants = Merchant::whereIn('email', [
            'info@evergreen.com',
            // 'info@halaynoor.com',
        ])->get();

        if ($merchants->isEmpty()) {
            $this->command->warn('No merchants found. Please run MerchantsSeeder first.');

            return;
        }

        foreach ($merchants as $merchant) {
            $this->createGhulamPurchase($merchant);
        }
    }

    private function createGhulamPurchase(Merchant $merchant): void
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
                'name' => 'Ghulam Nabi',
            ],
            [
                'id' => (string) Str::uuid(),
                'email' => 'ghulam-nabi-'.substr((string) $merchant->id, 0, 8).'@seed.local',
                'phone' => '+923444555590',
                'address' => 'Lahore, Pakistan',
                'country_id' => $pakistan->id,
                'city_id' => $lahore->id,
                'reference' => 'Seeded purchase vendor',
            ],
        );

        $createdBy = User::where('merchant_id', $merchant->id)->first();
        $purchaseDate = now()->subDays(rand(1, 15));
        $purchaseNo = 'PUR-'.$purchaseDate->format('Ymd').'-'.strtoupper(Str::random(6));

        $purchase = Purchase::firstOrCreate(
            [
                'purchase_no' => $purchaseNo,
            ],
            [
                'id' => (string) Str::uuid(),
                'merchant_id' => $merchant->id,
                'vendor_id' => $vendor->id,
                'purchase_date' => $purchaseDate,
                'subtotal' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'due_amount' => 0,
                'payment_type' => 'cash',
                'notes' => 'Bulk purchase from Ghulam Nabi - Inverters, Batteries, Solar Plates & EVEE Products',
                'created_by' => $createdBy?->id,
            ],
        );

        $subtotal = 0.0;
        $totalTax = 0.0;
        $locations = [];

        foreach ($this->items() as $item) {
            if ($item['quantity'] <= 0) {
                continue;
            }

            $locationKey = $this->locationKeyFor($item['category']);

            if (! $locationKey) {
                $this->command->warn("No business/branch mapping found for {$item['category']}. Skipping item.");

                continue;
            }

            if (! array_key_exists($locationKey, $locations)) {
                $locations[$locationKey] = $this->locationFor($merchant, $item['category']);
            }

            if (! $locations[$locationKey]) {
                continue;
            }

            [$business, $branch] = $locations[$locationKey];

            $productName = $this->productNameFor($item);
            $category = $this->categoryFor($merchant, $item['category']);
            $productMeta = $this->productMetaFor($merchant, $category, $item);
            $sku = $this->skuFor($productName, $item['category']);
            $unitPrice = $this->priceFor($productName, $item['category']);
            $lineTotal = round($item['quantity'] * $unitPrice, 2);
            $lineTax = round($lineTotal * (self::TAX_RATE / 100), 2);
            $subtotal = round($subtotal + $lineTotal, 2);
            $totalTax = round($totalTax + $lineTax, 2);

            $product = Product::updateOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'sku' => $sku,
                ],
                [
                    'name' => $productName,
                    'category_id' => $category->id,
                    'sub_category_id' => $productMeta['sub_category']->id,
                    'brand_id' => $productMeta['brand']->id,
                    'brand_model_id' => $productMeta['brand_model']->id,
                    'purchase_price' => $unitPrice,
                    'selling_price' => round($unitPrice * 1.18, 2),
                    'type' => 'stock',
                    'unit' => $this->unitFor($item['category']),
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
                    'tax' => self::TAX_RATE,
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

        $totalAmount = round($subtotal + $totalTax, 2);

        $purchase->update([
            'subtotal' => $subtotal,
            'total_amount' => $totalAmount,
            'paid_amount' => $totalAmount,
            'due_amount' => 0,
            'payment_type' => 'cash',
        ]);

        $this->command->info("Specific bulk purchase created successfully for merchant: {$merchant->name}");
        $this->command->info('Purchase No: '.$purchaseNo.' | Total Items: '.count($this->items()).' lines');
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

    private function subCategoryFor(Merchant $merchant, Category $category, string $name): Category
    {
        return Category::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'parent_id' => $category->id,
                'name' => $name,
            ],
            [
                'id' => (string) Str::uuid(),
            ],
        );
    }

    private function brandFor(Merchant $merchant, Category $category, string $name): Brand
    {
        $brand = Brand::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'name' => $this->normaliseBrandName($name),
            ],
            [
                'id' => (string) Str::uuid(),
            ],
        );

        BrandCategory::firstOrCreate([
            'merchant_id' => $merchant->id,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
        ]);

        return $brand;
    }

    private function brandModelFor(Merchant $merchant, Brand $brand, string $name): BrandModel
    {
        return BrandModel::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'name' => $name,
            ],
            [
                'id' => (string) Str::uuid(),
                'brand_id' => $brand->id,
            ],
        );
    }

    private function productMetaFor(Merchant $merchant, Category $category, array $item): array
    {
        $subCategoryName = $this->subCategoryNameFor($item);
        $subCategory = $this->subCategoryFor($merchant, $category, $subCategoryName);
        $brand = $this->brandFor($merchant, $subCategory, $item['brand']);
        $brandModel = $this->brandModelFor($merchant, $brand, $this->brandModelNameFor($item, $brand->name));

        return [
            'sub_category' => $subCategory,
            'brand' => $brand,
            'brand_model' => $brandModel,
        ];
    }

    private function subCategoryNameFor(array $item): string
    {
        $model = Str::lower($item['model'] ?? '');
        $capacity = Str::lower($item['capacity'] ?? '');

        return match ($item['category']) {
            'Inverter' => match (true) {
                str_contains($model, 'hybrid') => 'Hybrid Solar Inverters',
                str_contains($model, 'ongrid'), str_contains($model, 'on grid') => 'On-Grid Solar Inverters',
                str_contains($model, 'off grid') => 'Off-Grid Solar Inverters',
                str_contains($model, 'wifi') => 'Inverter Monitoring Devices',
                str_contains($model, 'power bank') => 'Solar Power Backup Units',
                default => 'Solar Inverters',
            },
            'VFD' => 'Solar Pump VFDs',
            'Battery' => match (true) {
                str_contains($model, 'claim') => 'Battery Warranty Claim Units',
                str_contains($model, 'hybrid') => 'Hybrid Lithium Batteries',
                str_contains($capacity, 'cycle') => 'Deep Cycle Lithium Batteries',
                default => 'Lithium Solar Batteries',
            },
            'Solar Plates' => match (true) {
                str_contains($capacity, '715') => 'High Wattage N-Type Solar Panels',
                str_contains($capacity, '650') || str_contains($capacity, '625') || str_contains($capacity, '620') => 'A Grade Mono PERC Solar Panels',
                default => 'Solar Panels',
            },
            'Wire' => match (true) {
                str_contains($model, '6mm') => '6mm DC Solar Cable',
                default => '4mm DC Solar Cable',
            },
            'EVEE' => match (true) {
                str_contains($model, '3w') => 'Three Wheel Electric Scooters',
                str_contains($model, 's1') => 'Electric Scooters',
                default => 'Electric Bikes',
            },
            default => $item['category'],
        };
    }

    private function brandModelNameFor(array $item, string $brandName): string
    {
        $parts = collect([
            $brandName,
            $this->normaliseModelName($item['model'] ?? ''),
            $this->normaliseCapacity($item['capacity'] ?? ''),
        ])->filter();

        return $parts->implode(' ');
    }

    private function normaliseBrandName(string $brand): string
    {
        return match (Str::lower(trim($brand))) {
            'ja' => 'JA Solar',
            'tw' => 'Tongwei',
            'invit' => 'INVT',
            'dongeal' => 'Dongle',
            default => Str::title(trim($brand)),
        };
    }

    private function normaliseModelName(string $model): ?string
    {
        $model = trim($model);

        if ($model === '') {
            return null;
        }

        return match (Str::lower($model)) {
            'off grid' => 'Off Grid',
            'ongrid' => 'On Grid',
            'inverter hybrid' => 'Hybrid Inverter Compatible',
            'for claim' => 'Claim Replacement',
            'gen z' => 'Gen Z',
            'nisa 3w' => 'Nisa 3W',
            default => Str::title($model),
        };
    }

    private function normaliseCapacity(string $capacity): ?string
    {
        $capacity = trim($capacity);

        if ($capacity === '') {
            return null;
        }

        return preg_replace('/kw/i', 'kW', $capacity);
    }

    private function locationKeyFor(string $category): ?string
    {
        return match ($category) {
            'EVEE' => 'evee',
            'Inverter', 'Battery', 'Solar Plates', 'VFD', 'Wire' => 'solar',
            default => null,
        };
    }

    private function locationFor(Merchant $merchant, string $category): ?array
    {
        [$businessName, $branchName] = $this->locationNamesFor($category);

        $business = Business::where('merchant_id', $merchant->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($businessName))])
            ->first();

        if (! $business) {
            $this->command->warn("{$businessName} business not found for merchant: {$merchant->name}");

            return null;
        }

        $branch = Branch::where('merchant_id', $merchant->id)
            ->where('business_id', $business->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($branchName))])
            ->first();

        if (! $branch) {
            $branch = Branch::where('merchant_id', $merchant->id)
                ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($branchName))])
                ->first();

            if ($branch) {
                $branch->update(['business_id' => $business->id]);
            }
        }

        if (! $branch) {
            $this->command->warn("{$branchName} branch not found for merchant: {$merchant->name}");

            return null;
        }

        return [$business, $branch];
    }

    private function locationNamesFor(string $category): array
    {
        return $category === 'EVEE'
            ? [self::EVEE_BUSINESS_NAME, self::EVEE_BRANCH_NAME]
            : [self::SOLAR_BUSINESS_NAME, self::SOLAR_BRANCH_NAME];
    }

    private function skuFor(string $name, string $category): string
    {
        $prefix = strtoupper(substr($category, 0, 3));
        $slug = strtoupper((string) Str::of($name)->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-'));

        return substr($prefix.'-'.$slug, 0, 42).'-'.substr(md5($category.'|'.$name), 0, 6);
    }

    private function productNameFor(array $item): string
    {
        return trim(collect([
            $item['brand'],
            $item['model'] ?: null,
            $item['capacity'] ?: null,
        ])->filter()->implode(' '));
    }

    private function unitFor(string $category): string
    {
        return 'pcs';
    }

    private function priceFor(string $name, string $category): float
    {
        $ranges = [
            'Inverter' => [25000, 450000],
            'VFD' => [15000, 180000],
            'Battery' => [30000, 450000],
            'Solar Plates' => [8000, 45000],
            'Wire' => [150, 1200],
            'EVEE' => [80000, 350000],
        ];

        [$min, $max] = $ranges[$category] ?? [1000, 10000];
        $steps = (int) (($max - $min) / 50);

        return (float) ($min + ((crc32($category.'|'.$name) % max(1, $steps)) * 50));
    }

    private function items(): array
    {
        return [
            ['category' => 'Inverter', 'brand' => 'CROWN', 'model' => 'off grid', 'capacity' => '10kw', 'quantity' => 3],
            ['category' => 'Inverter', 'brand' => 'CROWN', 'model' => 'off grid', 'capacity' => '8kw', 'quantity' => 2],
            ['category' => 'Inverter', 'brand' => 'Solar Power', 'model' => 'off grid', 'capacity' => '8kw', 'quantity' => 3],
            ['category' => 'Inverter', 'brand' => 'Long Life', 'model' => 'Hybrid', 'capacity' => '4000', 'quantity' => 4],
            ['category' => 'Inverter', 'brand' => 'Solis', 'model' => 'Hybrid', 'capacity' => '8kw pro', 'quantity' => 5],
            ['category' => 'Inverter', 'brand' => 'Solis', 'model' => 'Hybrid', 'capacity' => '8kw plus', 'quantity' => 1],
            ['category' => 'Inverter', 'brand' => 'Solis', 'model' => 'Hybrid', 'capacity' => '6kw pro', 'quantity' => 1],
            ['category' => 'Inverter', 'brand' => 'Solis', 'model' => 'Hybrid', 'capacity' => '6kw plus', 'quantity' => 1],
            ['category' => 'Inverter', 'brand' => 'Solis', 'model' => 'Ongrid', 'capacity' => '10kw three phase', 'quantity' => 3],
            ['category' => 'Inverter', 'brand' => 'Solis', 'model' => 'ongrid', 'capacity' => '110kw 3 phase', 'quantity' => 1],
            ['category' => 'Inverter', 'brand' => 'Huawei', 'model' => 'Ongrid', 'capacity' => '25kw 3 phase', 'quantity' => 1],
            ['category' => 'Inverter', 'brand' => 'Huawei', 'model' => 'Ongrid', 'capacity' => '12kw 3 phase', 'quantity' => 1],
            ['category' => 'Inverter', 'brand' => 'Auxsol', 'model' => 'Ongrid', 'capacity' => '10kw 3 phase', 'quantity' => 1],
            ['category' => 'Inverter', 'brand' => 'Maxpower', 'model' => 'pro', 'capacity' => '7kw', 'quantity' => 1],
            ['category' => 'Inverter', 'brand' => 'Long Life', 'model' => '', 'capacity' => '7kw 1 phase', 'quantity' => 1],
            ['category' => 'Inverter', 'brand' => 'Solis', 'model' => 'Hybrid', 'capacity' => '12kw 3 phase', 'quantity' => 1],
            ['category' => 'Inverter', 'brand' => 'Auxsol', 'model' => 'Hybrid', 'capacity' => '15kw', 'quantity' => 1],
            ['category' => 'Inverter', 'brand' => 'Dongeal', 'model' => 'Huawei Wifi', 'capacity' => '', 'quantity' => 1],
            ['category' => 'Inverter', 'brand' => 'Auxsol', 'model' => 'Power bank', 'capacity' => '16.5kw', 'quantity' => 1],
            ['category' => 'VFD', 'brand' => 'INVIT', 'model' => '', 'capacity' => '5.5kw', 'quantity' => 1],
            ['category' => 'VFD', 'brand' => 'INVIT', 'model' => '', 'capacity' => '22/30', 'quantity' => 1],
            ['category' => 'VFD', 'brand' => 'INVIT', 'model' => '', 'capacity' => '18/22', 'quantity' => 2],
            ['category' => 'VFD', 'brand' => 'INVIT', 'model' => '', 'capacity' => '37/45', 'quantity' => 1],
            ['category' => 'Battery', 'brand' => 'Pylontech', 'model' => 'Fidus', 'capacity' => '5kw', 'quantity' => 6],
            ['category' => 'Battery', 'brand' => 'Ritar', 'model' => '', 'capacity' => '5kw', 'quantity' => 1],
            ['category' => 'Battery', 'brand' => 'Narada', 'model' => 'for claim', 'capacity' => '', 'quantity' => 1],
            ['category' => 'Battery', 'brand' => 'Apex', 'model' => '', 'capacity' => '6000 cycle', 'quantity' => 0],
            ['category' => 'Battery', 'brand' => 'Grovolt', 'model' => 'inverter Hybrid', 'capacity' => '8.2kw', 'quantity' => 1],
            ['category' => 'Battery', 'brand' => 'Apex', 'model' => '', 'capacity' => '8000 cycle', 'quantity' => 4],
            ['category' => 'Solar Plates', 'brand' => 'TCL', 'model' => '', 'capacity' => '620w', 'quantity' => 512],
            ['category' => 'Solar Plates', 'brand' => 'JA', 'model' => '', 'capacity' => '715w', 'quantity' => 3],
            ['category' => 'Solar Plates', 'brand' => 'Canadian', 'model' => '', 'capacity' => '625w', 'quantity' => 119],
            ['category' => 'Solar Plates', 'brand' => 'Huasun', 'model' => '', 'capacity' => '650w', 'quantity' => 1],
            ['category' => 'Solar Plates', 'brand' => 'Huasun', 'model' => '', 'capacity' => '610w', 'quantity' => 2],
            ['category' => 'Solar Plates', 'brand' => 'Tw', 'model' => '', 'capacity' => '615W', 'quantity' => 2],
            ['category' => 'Solar Plates', 'brand' => 'Astronergy', 'model' => '', 'capacity' => '625w', 'quantity' => 12],
            ['category' => 'Solar Plates', 'brand' => 'Sunpro', 'model' => '', 'capacity' => '620w', 'quantity' => 15],
            ['category' => 'Solar Plates', 'brand' => 'TCL', 'model' => '', 'capacity' => '715w', 'quantity' => 0],
            ['category' => 'Wire', 'brand' => 'Black', 'model' => '4mm', 'capacity' => '1000 m', 'quantity' => 1000],
            ['category' => 'Wire', 'brand' => 'Red', 'model' => '4mm', 'capacity' => '22 m', 'quantity' => 22],
            ['category' => 'Wire', 'brand' => 'Black', 'model' => '6mm', 'capacity' => '1959', 'quantity' => 1854],
            ['category' => 'Wire', 'brand' => 'Red', 'model' => '6mm', 'capacity' => '3068', 'quantity' => 2971],
            ['category' => 'EVEE', 'brand' => 'Evee', 'model' => 'Nisa 3w', 'capacity' => 'Graphane', 'quantity' => 1],
            ['category' => 'EVEE', 'brand' => 'Evee', 'model' => 'S1 Air', 'capacity' => 'Graphane', 'quantity' => 2],
            ['category' => 'EVEE', 'brand' => 'Evee', 'model' => 'Nisa', 'capacity' => 'Graphane', 'quantity' => 1],
            ['category' => 'EVEE', 'brand' => 'Evee', 'model' => 'Mito', 'capacity' => 'Graphane', 'quantity' => 1],
            ['category' => 'EVEE', 'brand' => 'Evee', 'model' => 'Gen z', 'capacity' => 'Graphane', 'quantity' => 3],
        ];
    }
}

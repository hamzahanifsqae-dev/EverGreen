<?php

namespace Database\Seeders\ZGN\PremiumLubricantsOils;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PremiumLubricantsOilsProductsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $lubricantsOilsCategory = Category::where('merchant_id', $merchant->id)
            ->where('name', 'Premium Lubricants & Oils')
            ->whereNull('parent_id')
            ->first();

        if (! $lubricantsOilsCategory) {
            return;
        }

        $brands = Brand::where('merchant_id', $merchant->id)
            ->whereIn('name', ['Castrol', 'Mobil', 'Shell', 'Total'])
            ->get();

        if ($brands->isEmpty()) {
            return;
        }

        $subCategories = [
            'Engine Oil' => [
                ['name' => '5W-30 Synthetic Engine Oil', 'description' => 'Premium synthetic engine oil 5W-30 grade'],
                ['name' => '10W-40 Conventional Oil', 'description' => 'Standard conventional engine oil'],
                ['name' => '0W-20 Full Synthetic', 'description' => 'Full synthetic engine oil for modern engines'],
                ['name' => '15W-40 Diesel Engine Oil', 'description' => 'Heavy-duty diesel engine oil'],
            ],
            'Transmission Oil' => [
                ['name' => 'ATF Automatic Transmission Fluid', 'description' => 'Automatic transmission fluid'],
                ['name' => 'CVT Transmission Fluid', 'description' => 'Continuously variable transmission fluid'],
            ],
            'Gear Oil' => [
                ['name' => '75W-90 Gear Oil', 'description' => 'Premium gear oil for manual transmissions'],
                ['name' => '80W-90 Gear Oil', 'description' => 'Heavy-duty gear oil'],
            ],
            'Brake Fluid' => [
                ['name' => 'DOT 3 Brake Fluid', 'description' => 'Standard brake fluid DOT 3'],
                ['name' => 'DOT 4 Brake Fluid', 'description' => 'Premium brake fluid DOT 4'],
            ],
            'Coolant' => [
                ['name' => 'Ethylene Glycol Coolant', 'description' => 'Standard engine coolant'],
                ['name' => 'Propylene Glycol Coolant', 'description' => 'Eco-friendly engine coolant'],
            ],
            'Grease' => [
                ['name' => 'Multi-Purpose Grease', 'description' => 'All-purpose lubricating grease'],
                ['name' => 'High-Temperature Grease', 'description' => 'Grease for high-temperature applications'],
            ],
            'Additives' => [
                ['name' => 'Engine Oil Additive', 'description' => 'Performance engine oil additive'],
                ['name' => 'Fuel System Cleaner', 'description' => 'Fuel system cleaning additive'],
            ],
        ];

        $merchantSlug = 'flowdesk';

        foreach ($subCategories as $subCategoryName => $products) {
            $subCategory = Category::where('merchant_id', $merchant->id)
                ->where('name', $subCategoryName)
                ->where('parent_id', $lubricantsOilsCategory->id)
                ->first();

            if (! $subCategory) {
                continue;
            }

            foreach ($products as $productData) {
                $brand = $brands->random();
                $brandModels = BrandModel::where('merchant_id', $merchant->id)
                    ->where('brand_id', $brand->id)
                    ->get();

                if ($brandModels->isEmpty()) {
                    continue;
                }

                $brandModel = $brandModels->random();
                $sku = strtoupper($merchantSlug).'-LO-'.strtoupper(substr($subCategoryName, 0, 3)).'-'.strtoupper(substr(uniqid(), -6));

                Product::firstOrCreate(
                    [
                        'merchant_id' => $merchant->id,
                        'sku' => $sku,
                    ],
                    [
                        'id' => Str::uuid(),
                        'category_id' => $lubricantsOilsCategory->id,
                        'sub_category_id' => $subCategory->id,
                        'brand_id' => $brand->id,
                        'brand_model_id' => $brandModel->id,
                        'name' => $productData['name'],
                        'description' => $productData['description'],
                        'purchase_price' => rand(500, 5000) / 100,
                        'selling_price' => rand(800, 8000) / 100,
                        'type' => 'stock',
                        'unit' => 'liter',
                        'track_inventory' => true,
                        'is_variable_price' => false,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}

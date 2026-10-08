<?php

namespace Database\Seeders\ZGN\TyresAlloyWheels;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TyresAlloyWheelsProductsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $tyresWheelsCategory = Category::where('merchant_id', $merchant->id)
            ->where('name', 'Tyres & Alloy Wheels')
            ->whereNull('parent_id')
            ->first();

        if (! $tyresWheelsCategory) {
            return;
        }

        $brands = Brand::where('merchant_id', $merchant->id)
            ->whereIn('name', ['Michelin', 'Bridgestone', 'Continental', 'Goodyear'])
            ->get();

        if ($brands->isEmpty()) {
            return;
        }

        $subCategories = [
            'Car Tyres' => [
                ['name' => '195/65R15 Car Tire', 'description' => 'Standard passenger car tire'],
                ['name' => '205/55R16 Car Tire', 'description' => 'Premium car tire for sedans'],
                ['name' => '225/50R17 Performance Tire', 'description' => 'High-performance tire'],
                ['name' => '185/70R14 Economy Tire', 'description' => 'Economy car tire'],
            ],
            'Motorcycle Tyres' => [
                ['name' => '100/90-18 Motorcycle Tire', 'description' => 'Standard motorcycle tire'],
                ['name' => '120/70-17 Sport Tire', 'description' => 'Sport motorcycle tire'],
                ['name' => '110/80-18 Commuter Tire', 'description' => 'Commuter bike tire'],
            ],
            'Alloy Wheels' => [
                ['name' => '15" Alloy Wheel Set', 'description' => 'Premium 15-inch alloy wheels'],
                ['name' => '16" Alloy Wheel', 'description' => 'Stylish 16-inch alloy wheel'],
                ['name' => '17" Sport Alloy Wheel', 'description' => 'Sporty 17-inch alloy wheel'],
                ['name' => '18" Luxury Alloy Wheel', 'description' => 'Luxury 18-inch alloy wheel'],
            ],
            'Steel Wheels' => [
                ['name' => '14" Steel Wheel', 'description' => 'Durable 14-inch steel wheel'],
                ['name' => '15" Steel Wheel', 'description' => 'Standard 15-inch steel wheel'],
            ],
            'Wheel Accessories' => [
                ['name' => 'Wheel Center Cap', 'description' => 'Decorative center cap for wheels'],
                ['name' => 'Wheel Lug Nuts', 'description' => 'High-quality lug nuts'],
                ['name' => 'Wheel Spacers', 'description' => 'Wheel spacers for fitment'],
            ],
            'Tire Accessories' => [
                ['name' => 'Tire Pressure Gauge', 'description' => 'Digital tire pressure gauge'],
                ['name' => 'Tire Repair Kit', 'description' => 'Complete tire repair kit'],
                ['name' => 'Tire Valve Cap', 'description' => 'Premium tire valve caps'],
            ],
        ];

        $merchantSlug = 'flowdesk';

        foreach ($subCategories as $subCategoryName => $products) {
            $subCategory = Category::where('merchant_id', $merchant->id)
                ->where('name', $subCategoryName)
                ->where('parent_id', $tyresWheelsCategory->id)
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
                $sku = strtoupper($merchantSlug).'-TW-'.strtoupper(substr($subCategoryName, 0, 3)).'-'.strtoupper(substr(uniqid(), -6));

                Product::firstOrCreate(
                    [
                        'merchant_id' => $merchant->id,
                        'sku' => $sku,
                    ],
                    [
                        'id' => Str::uuid(),
                        'category_id' => $tyresWheelsCategory->id,
                        'sub_category_id' => $subCategory->id,
                        'brand_id' => $brand->id,
                        'brand_model_id' => $brandModel->id,
                        'name' => $productData['name'],
                        'description' => $productData['description'],
                        'purchase_price' => rand(5000, 50000) / 100,
                        'selling_price' => rand(8000, 80000) / 100,
                        'type' => 'stock',
                        'unit' => 'pcs',
                        'track_inventory' => true,
                        'is_variable_price' => false,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}

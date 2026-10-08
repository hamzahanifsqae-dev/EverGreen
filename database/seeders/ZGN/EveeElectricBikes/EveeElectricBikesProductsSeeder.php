<?php

namespace Database\Seeders\ZGN\EveeElectricBikes;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EveeElectricBikesProductsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $electricBikesCategory = Category::where('merchant_id', $merchant->id)
            ->where('name', 'Electric Bikes')
            ->whereNull('parent_id')
            ->first();

        if (! $electricBikesCategory) {
            return;
        }

        $brand = Brand::where('merchant_id', $merchant->id)
            ->where('name', 'Evee')
            ->first();

        if (! $brand) {
            return;
        }

        $brandModels = BrandModel::where('merchant_id', $merchant->id)
            ->where('brand_id', $brand->id)
            ->get();

        if ($brandModels->isEmpty()) {
            return;
        }

        $subCategories = [
            'Complete Bikes' => [
                ['name' => 'Evee City Pro Electric Bike', 'description' => 'Premium city electric bike with 500W motor'],
                ['name' => 'Evee Mountain Elite E-Bike', 'description' => 'High-performance mountain electric bike'],
                ['name' => 'Evee Commuter Plus', 'description' => 'Comfortable commuter electric bike'],
                ['name' => 'Evee Sport E-Bike', 'description' => 'Sporty electric bike for active riders'],
            ],
            'Batteries' => [
                ['name' => '48V 10Ah Lithium Battery', 'description' => 'High-capacity lithium battery for e-bikes'],
                ['name' => '48V 15Ah Lithium Battery', 'description' => 'Extended range lithium battery'],
                ['name' => '36V 12Ah Battery Pack', 'description' => 'Standard voltage battery pack'],
            ],
            'Chargers' => [
                ['name' => '48V Fast Charger', 'description' => 'Rapid charging solution for 48V batteries'],
                ['name' => '36V Standard Charger', 'description' => 'Standard charger for 36V batteries'],
                ['name' => 'Universal E-Bike Charger', 'description' => 'Compatible with multiple voltage systems'],
            ],
            'Tires & Tubes' => [
                ['name' => '26" E-Bike Tire', 'description' => 'Durable tire designed for electric bikes'],
                ['name' => '27.5" Mountain Tire', 'description' => 'Mountain bike tire for e-bikes'],
                ['name' => 'Inner Tube 26"', 'description' => 'Standard inner tube for 26" wheels'],
            ],
            'Brakes' => [
                ['name' => 'Hydraulic Disc Brake Set', 'description' => 'High-performance hydraulic disc brakes'],
                ['name' => 'Mechanical Disc Brake', 'description' => 'Reliable mechanical disc brake system'],
            ],
            'Frames' => [
                ['name' => 'Aluminum E-Bike Frame', 'description' => 'Lightweight aluminum frame for electric bikes'],
                ['name' => 'Steel E-Bike Frame', 'description' => 'Durable steel frame construction'],
            ],
            'Motors' => [
                ['name' => '500W Hub Motor', 'description' => 'Powerful 500W rear hub motor'],
                ['name' => '750W Mid-Drive Motor', 'description' => 'High-torque mid-drive motor'],
            ],
            'Controllers' => [
                ['name' => '48V E-Bike Controller', 'description' => 'Advanced controller for 48V systems'],
                ['name' => '36V Controller Unit', 'description' => 'Standard controller for 36V systems'],
            ],
            'Accessories' => [
                ['name' => 'E-Bike Helmet', 'description' => 'Safety helmet for electric bike riders'],
                ['name' => 'Bike Lock', 'description' => 'Heavy-duty lock for e-bike security'],
                ['name' => 'Bike Basket', 'description' => 'Front basket for carrying items'],
            ],
        ];

        $merchantSlug = 'flowdesk';

        foreach ($subCategories as $subCategoryName => $products) {
            $subCategory = Category::where('merchant_id', $merchant->id)
                ->where('name', $subCategoryName)
                ->where('parent_id', $electricBikesCategory->id)
                ->first();

            if (! $subCategory) {
                continue;
            }

            foreach ($products as $productData) {
                $brandModel = $brandModels->random();
                $sku = strtoupper($merchantSlug).'-EBK-'.strtoupper(substr($subCategoryName, 0, 3)).'-'.strtoupper(substr(uniqid(), -6));

                Product::firstOrCreate(
                    [
                        'merchant_id' => $merchant->id,
                        'sku' => $sku,
                    ],
                    [
                        'id' => Str::uuid(),
                        'category_id' => $electricBikesCategory->id,
                        'sub_category_id' => $subCategory->id,
                        'brand_id' => $brand->id,
                        'brand_model_id' => $brandModel->id,
                        'name' => $productData['name'],
                        'description' => $productData['description'],
                        'purchase_price' => rand(10000, 200000) / 100,
                        'selling_price' => rand(15000, 300000) / 100,
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

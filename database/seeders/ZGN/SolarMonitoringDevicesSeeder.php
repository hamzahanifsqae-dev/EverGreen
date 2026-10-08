<?php

namespace Database\Seeders\ZGN;

use App\Models\Brand;
use App\Models\BrandCategory;
use App\Models\BrandModel;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\ProductVariantValue;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SolarMonitoringDevicesSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $subCat = Category::where('merchant_id', $merchant->id)->where('name', 'Monitoring Devices')->first();
        if (! $subCat) {
            throw new Exception('Monitoring Devices category not found');
        }

        $brand = Brand::where(['merchant_id' => $merchant->id, 'name' => 'Growatt'])->first();
        if (! $brand) {
            throw new Exception('Growatt brand not found');
        }

        BrandCategory::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'brand_id' => $brand->id,
                'category_id' => $subCat->id,
            ],
            [
                'id' => Str::uuid(),
            ]
        );

        $brandModel = BrandModel::where([
            'merchant_id' => $merchant->id,
            'brand_id' => $brand->id,
        ])->first();

        $merchantSlug = collect(explode(' ', $merchant->name))
            ->map(fn ($word) => Str::lower(Str::substr($word, 0, 1)))
            ->implode('');

        $sku = "{$merchantSlug}-monitoring-device";

        $product = Product::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'sku' => $sku,
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Monitoring Device',
                'description' => 'Energy meters and net meters for solar monitoring',
                'category_id' => $subCat->parent_id,
                'sub_category_id' => $subCat->id,
                'brand_id' => $brand?->id,
                'brand_model_id' => $brandModel?->id,
                'type' => 'stock',
                'unit' => 'pcs',
                'track_inventory' => true,
                'is_active' => true,
            ]
        );

        $options = [
            'device_type' => ['Energy Meter', 'Net Meter'],
            'phase' => ['Single Phase', 'Three Phase'],
        ];

        foreach ($options as $name => $values) {
            $opt = ProductOption::firstOrCreate(
                ['product_id' => $product->id, 'name' => $name],
                ['id' => Str::uuid(), 'display_name' => ucwords(str_replace('_', ' ', $name))]
            );

            foreach ($values as $val) {
                ProductOptionValue::firstOrCreate(
                    ['product_option_id' => $opt->id, 'value' => $val],
                    ['id' => Str::uuid()]
                );
            }
        }

        $variants = [
            'EM-1PH' => ['name' => 'Energy Meter (Single Phase)', 'values' => ['device_type' => 'Energy Meter', 'phase' => 'Single Phase']],
            'EM-3PH' => ['name' => 'Energy Meter (Three Phase)', 'values' => ['device_type' => 'Energy Meter', 'phase' => 'Three Phase']],
            'NM-1PH' => ['name' => 'Net Meter (Single Phase)', 'values' => ['device_type' => 'Net Meter', 'phase' => 'Single Phase']],
            'NM-3PH' => ['name' => 'Net Meter (Three Phase)', 'values' => ['device_type' => 'Net Meter', 'phase' => 'Three Phase']],
        ];

        foreach ($variants as $sku => $data) {
            $variant = ProductVariant::firstOrCreate(
                ['merchant_id' => $merchant->id, 'sku' => $sku],
                ['id' => Str::uuid(), 'product_id' => $product->id, 'name' => $data['name'], 'is_active' => true]
            );

            foreach ($data['values'] as $optName => $valName) {
                $opt = ProductOption::where('product_id', $product->id)->where('name', $optName)->first();
                if (! $opt) {
                    continue;
                }

                $val = ProductOptionValue::where('product_option_id', $opt->id)->where('value', $valName)->first();
                if (! $val) {
                    continue;
                }

                ProductVariantValue::firstOrCreate(
                    ['product_variant_id' => $variant->id, 'product_option_id' => $opt->id],
                    ['id' => Str::uuid(), 'product_option_value_id' => $val->id]
                );
            }
        }
    }
}

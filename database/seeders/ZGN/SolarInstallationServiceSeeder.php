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

class SolarInstallationServiceSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $subCat = Category::where('merchant_id', $merchant->id)->where('name', 'Installation')->first();
        if (! $subCat) {
            throw new Exception('Installation category not found');
        }

        $brand = Brand::where(['merchant_id' => $merchant->id, 'name' => 'Flowdesk Services'])->first();
        if (! $brand) {
            throw new Exception('Flowdesk Services brand not found');
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

        $sku = "{$merchantSlug}-solar-installation-service";

        $product = Product::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'sku' => $sku,
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Solar Installation Service',
                'description' => 'Complete solar system installation service',
                'category_id' => $subCat->parent_id,
                'sub_category_id' => $subCat->id,
                'brand_id' => $brand?->id,
                'brand_model_id' => $brandModel?->id,
                'type' => 'service',
                'unit' => 'job',
                'track_inventory' => false,
                'is_variable_price' => true,
                'is_active' => true,
            ]
        );

        $options = [
            'system_type' => ['Residential', 'Commercial'],
            'capacity_range' => ['Up to 5kW', '5–20kW', '20kW+'],
        ];

        foreach ($options as $name => $vals) {
            $opt = ProductOption::firstOrCreate(
                ['product_id' => $product->id, 'name' => $name],
                ['id' => Str::uuid(), 'display_name' => ucwords(str_replace('_', ' ', $name))]
            );
            foreach ($vals as $v) {
                ProductOptionValue::firstOrCreate(
                    ['product_option_id' => $opt->id, 'value' => $v],
                    ['id' => Str::uuid()]
                );
            }
        }

        $variants = [
            'INST-RES-5KW' => ['name' => 'Residential Installation (≤5kW)', 'values' => ['system_type' => 'Residential', 'capacity_range' => 'Up to 5kW']],
            'INST-COM-20KW' => ['name' => 'Commercial Installation (5–20kW)', 'values' => ['system_type' => 'Commercial', 'capacity_range' => '5–20kW']],
            'INST-COM-50KW' => ['name' => 'Commercial Installation (20kW+)', 'values' => ['system_type' => 'Commercial', 'capacity_range' => '20kW+']],
        ];

        foreach ($variants as $sku => $data) {
            $variant = ProductVariant::firstOrCreate(
                ['merchant_id' => $merchant->id, 'sku' => $sku],
                ['id' => Str::uuid(), 'product_id' => $product->id, 'name' => $data['name'], 'is_active' => true]
            );

            foreach ($data['values'] as $optName => $valName) {
                $opt = ProductOption::where('product_id', $product->id)->where('name', $optName)->first();
                $val = ProductOptionValue::where('product_option_id', $opt->id)->where('value', $valName)->first();

                ProductVariantValue::firstOrCreate(
                    ['product_variant_id' => $variant->id, 'product_option_id' => $opt->id],
                    ['id' => Str::uuid(), 'product_option_value_id' => $val->id]
                );
            }
        }
    }
}

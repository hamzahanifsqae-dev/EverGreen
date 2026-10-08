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

class SolarAMCServiceSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $subCat = Category::where('merchant_id', $merchant->id)->where('name', 'AMC')->first();
        if (! $subCat) {
            throw new Exception('AMC category not found');
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

        $sku = "{$merchantSlug}-solar-amc-service";

        $product = Product::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'sku' => $sku,
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Solar AMC Service',
                'description' => 'Annual maintenance contract for solar systems',
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
            'duration' => ['1 Year', '2 Years', '3 Years'],
            'system_type' => ['Residential', 'Commercial'],
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
            'AMC-RES-1Y' => ['name' => 'Residential AMC – 1 Year', 'values' => ['duration' => '1 Year', 'system_type' => 'Residential']],
            'AMC-RES-2Y' => ['name' => 'Residential AMC – 2 Years', 'values' => ['duration' => '2 Years', 'system_type' => 'Residential']],
            'AMC-COM-1Y' => ['name' => 'Commercial AMC – 1 Year', 'values' => ['duration' => '1 Year', 'system_type' => 'Commercial']],
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

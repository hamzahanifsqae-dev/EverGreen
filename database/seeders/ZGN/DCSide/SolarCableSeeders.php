<?php

namespace Database\Seeders\ZGN\DCSide;

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

class SolarCableSeeders extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $category = Category::where('merchant_id', $merchant->id)->where('name', 'Cable Management')->first();
        if (! $category) {
            throw new Exception('Cable Management category not found');
        }

        $brand = Brand::where(['merchant_id' => $merchant->id, 'name' => 'Generic'])->first();
        if (! $brand) {
            throw new Exception('Generic brand not found');
        }

        BrandCategory::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'brand_id' => $brand->id,
                'category_id' => $category->id,
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

        $sku = "{$merchantSlug}-solar-cable";

        $product = Product::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'sku' => $sku,
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Solar Cable',
                'type' => 'measured_stock',
                'unit' => 'sqm',
                'track_inventory' => true,
                'category_id' => $category->parent_id,
                'sub_category_id' => $category->id,
                'brand_id' => $brand?->id,
                'brand_model_id' => $brandModel?->id,
            ]
        );

        $options = [
            'size' => ['4mm', '6mm', '10mm'],
            'cable_type' => ['DC', 'AC'],
        ];

        foreach ($options as $name => $values) {
            $opt = ProductOption::firstOrCreate(
                ['product_id' => $product->id, 'name' => $name],
                ['id' => Str::uuid(), 'display_name' => ucfirst($name)]
            );

            foreach ($values as $val) {
                ProductOptionValue::firstOrCreate(
                    ['product_option_id' => $opt->id, 'value' => $val],
                    ['id' => Str::uuid()]
                );
            }
        }

        $variants = [
            'DC-4MM' => ['size' => '4mm', 'cable_type' => 'DC'],
            'DC-6MM' => ['size' => '6mm', 'cable_type' => 'DC'],
            'DC-10MM' => ['size' => '10mm', 'cable_type' => 'DC'],
            'AC-6MM' => ['size' => '6mm', 'cable_type' => 'AC'],
        ];

        foreach ($variants as $sku => $map) {
            $variant = ProductVariant::firstOrCreate(
                ['merchant_id' => $merchant->id, 'sku' => $sku],
                ['id' => Str::uuid(), 'product_id' => $product->id, 'name' => "Solar Cable $sku"]
            );

            foreach ($map as $opt => $val) {
                $o = ProductOption::where('product_id', $product->id)->where('name', $opt)->first();
                $v = ProductOptionValue::where('product_option_id', $o->id)->where('value', $val)->first();

                ProductVariantValue::firstOrCreate(
                    ['product_variant_id' => $variant->id, 'product_option_id' => $o->id],
                    ['id' => Str::uuid(), 'product_option_value_id' => $v->id]
                );
            }
        }
    }
}

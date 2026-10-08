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

class SolarProtectionSeeders extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $category = Category::where('merchant_id', $merchant->id)->where('name', 'Circuit Protection')->first();
        if (! $category) {
            throw new Exception('Circuit Protection category not found');
        }

        $brand = Brand::where(['merchant_id' => $merchant->id, 'name' => 'Schneider Electric'])->first();
        if (! $brand) {
            throw new Exception('Schneider Electric brand not found');
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

        $sku = "{$merchantSlug}-protection-device";

        $product = Product::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'sku' => $sku,
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Protection Device',
                'type' => 'stock',
                'unit' => 'pcs',
                'track_inventory' => true,
                'category_id' => $category->parent_id,
                'sub_category_id' => $category->id,
                'brand_id' => $brand?->id,
                'brand_model_id' => $brandModel?->id,
            ]
        );

        $opts = [
            'device_type' => ['MCB', 'SPD'],
            'side' => ['AC', 'DC'],
        ];

        foreach ($opts as $name => $vals) {
            $opt = ProductOption::firstOrCreate(
                ['product_id' => $product->id, 'name' => $name],
                ['id' => Str::uuid(), 'display_name' => ucfirst($name)]
            );
            foreach ($vals as $v) {
                ProductOptionValue::firstOrCreate(
                    ['product_option_id' => $opt->id, 'value' => $v],
                    ['id' => Str::uuid()]
                );
            }
        }

        $variants = [
            'DC-MCB' => ['device_type' => 'MCB', 'side' => 'DC'],
            'AC-MCB' => ['device_type' => 'MCB', 'side' => 'AC'],
            'DC-SPD' => ['device_type' => 'SPD', 'side' => 'DC'],
            'AC-SPD' => ['device_type' => 'SPD', 'side' => 'AC'],
        ];

        foreach ($variants as $sku => $map) {
            $variant = ProductVariant::firstOrCreate(
                ['merchant_id' => $merchant->id, 'sku' => $sku],
                ['id' => Str::uuid(), 'product_id' => $product->id, 'name' => $sku]
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

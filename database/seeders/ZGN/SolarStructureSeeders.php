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

class SolarStructureSeeders extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $category = Category::where('merchant_id', $merchant->id)->where('name', 'Mounting Structures')->first();
        if (! $category) {
            throw new Exception('Mounting Structures category not found');
        }

        $brand = Brand::where(['merchant_id' => $merchant->id, 'name' => 'Flowdesk Fabrication'])->first();
        if (! $brand) {
            throw new Exception('Flowdesk Fabrication brand not found');
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

        $sku = "{$merchantSlug}-mounting-structure";

        $product = Product::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'sku' => $sku,
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Mounting Structure',
                'type' => 'custom',
                'unit' => 'set',
                'track_inventory' => false,
                'category_id' => $category->parent_id,
                'sub_category_id' => $category->id,
                'brand_id' => $brand?->id,
                'brand_model_id' => $brandModel?->id,
            ]
        );

        $opts = [
            'level' => ['L1', 'L2', 'L3'],
            'material' => ['GI', 'Aluminum'],
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

        foreach (['L1', 'L2', 'L3'] as $level) {
            foreach (['GI', 'Aluminum'] as $mat) {
                $sku = "STRUCT-$level-$mat";

                $variant = ProductVariant::firstOrCreate(
                    ['merchant_id' => $merchant->id, 'sku' => $sku],
                    ['id' => Str::uuid(), 'product_id' => $product->id, 'name' => "Structure $level $mat"]
                );

                foreach (['level' => $level, 'material' => $mat] as $opt => $val) {
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
}

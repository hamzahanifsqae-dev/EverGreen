<?php

namespace Database\Seeders\ZGN\SolarInverters;

use App\Models\Brand;
use App\Models\BrandCategory;
use App\Models\BrandModel;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZGNInverterProductsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $category = Category::where('merchant_id', $merchant->id)->where('name', 'Inverters')->first();
        if (! $category) {
            throw new Exception('Inverters category not found');
        }

        $brand = Brand::where(['merchant_id' => $merchant->id, 'name' => 'Inverex'])->first();
        if (! $brand) {
            throw new Exception('Inverex brand not found');
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

        $sku = "{$merchantSlug}-solar-inverter";

        Product::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'sku' => $sku,
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Solar Inverter',
                'description' => 'Solar power inverter',
                'category_id' => $category?->parent_id,
                'sub_category_id' => $category?->id,
                'brand_id' => $brand?->id,
                'brand_model_id' => $brandModel?->id,
                'type' => 'stock',
                'unit' => 'pcs',
                'track_inventory' => true,
            ]
        );
    }
}

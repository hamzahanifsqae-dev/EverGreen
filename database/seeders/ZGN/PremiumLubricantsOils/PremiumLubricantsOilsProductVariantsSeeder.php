<?php

namespace Database\Seeders\ZGN\PremiumLubricantsOils;

use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PremiumLubricantsOilsProductVariantsSeeder extends Seeder
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

        $products = Product::where('merchant_id', $merchant->id)
            ->where('category_id', $lubricantsOilsCategory->id)
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $merchantSlug = 'flowdesk';

        foreach ($products as $product) {
            $variantSku = strtoupper($merchantSlug).'-LO-VAR-'.strtoupper(substr(uniqid(), -8));

            ProductVariant::firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'sku' => $variantSku,
                ],
                [
                    'id' => Str::uuid(),
                    'product_id' => $product->id,
                    'name' => $product->name.' - Standard',
                    'purchase_price' => $product->purchase_price,
                    'selling_price' => $product->selling_price,
                    'is_active' => true,
                ]
            );
        }
    }
}

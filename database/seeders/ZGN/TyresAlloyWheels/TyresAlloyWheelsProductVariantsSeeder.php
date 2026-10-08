<?php

namespace Database\Seeders\ZGN\TyresAlloyWheels;

use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TyresAlloyWheelsProductVariantsSeeder extends Seeder
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

        $products = Product::where('merchant_id', $merchant->id)
            ->where('category_id', $tyresWheelsCategory->id)
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $merchantSlug = 'flowdesk';

        foreach ($products as $product) {
            $variantSku = strtoupper($merchantSlug).'-TW-VAR-'.strtoupper(substr(uniqid(), -8));

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

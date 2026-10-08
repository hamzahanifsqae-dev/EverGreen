<?php

namespace Database\Seeders\ZGN\EveeElectricBikes;

use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EveeElectricBikesProductVariantsSeeder extends Seeder
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

        $products = Product::where('merchant_id', $merchant->id)
            ->where('category_id', $electricBikesCategory->id)
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $merchantSlug = 'flowdesk';

        foreach ($products as $product) {
            $variantSku = strtoupper($merchantSlug).'-EBK-VAR-'.strtoupper(substr(uniqid(), -8));

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

<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Lightweight cold-storage demo catalog for local testing.
 * Run: php artisan db:seed --class=ColdStorageDemoProductsSeeder
 */
class ColdStorageDemoProductsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::query()->where('email', 'info@evergreen.com')->first();

        if (! $merchant) {
            $this->command?->error('Merchant info@evergreen.com not found.');

            return;
        }

        $root = Category::query()->firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'parent_id' => null,
                'name' => 'Cold Storage Goods',
            ],
            [
                'id' => (string) Str::uuid(),
            ]
        );

        $categories = [
            'Produce' => [
                ['name' => 'Potatoes', 'sku' => 'CS-POTATO', 'unit' => 'bags'],
                ['name' => 'Onions', 'sku' => 'CS-ONION', 'unit' => 'bags'],
                ['name' => 'Apples', 'sku' => 'CS-APPLE', 'unit' => 'crates'],
                ['name' => 'Oranges', 'sku' => 'CS-ORANGE', 'unit' => 'crates'],
            ],
            'Frozen foods' => [
                ['name' => 'Frozen Chicken', 'sku' => 'CS-CHICKEN', 'unit' => 'boxes'],
                ['name' => 'Frozen Peas', 'sku' => 'CS-PEAS', 'unit' => 'bags'],
                ['name' => 'Ice Cream', 'sku' => 'CS-ICECREAM', 'unit' => 'cartons'],
            ],
            'Dairy' => [
                ['name' => 'Milk Cartons', 'sku' => 'CS-MILK', 'unit' => 'crates'],
                ['name' => 'Butter Blocks', 'sku' => 'CS-BUTTER', 'unit' => 'boxes'],
                ['name' => 'Cheese Blocks', 'sku' => 'CS-CHEESE', 'unit' => 'boxes'],
            ],
        ];

        $created = 0;

        foreach ($categories as $categoryName => $products) {
            $category = Category::query()->firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'parent_id' => $root->id,
                    'name' => $categoryName,
                ],
                [
                    'id' => (string) Str::uuid(),
                ]
            );

            foreach ($products as $product) {
                $record = Product::query()->firstOrCreate(
                    [
                        'merchant_id' => $merchant->id,
                        'sku' => $product['sku'],
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'name' => $product['name'],
                        'description' => 'Demo cold storage product',
                        'category_id' => $root->id,
                        'sub_category_id' => $category->id,
                        'type' => 'stock',
                        'unit' => 'pcs',
                        'track_inventory' => false,
                        'is_active' => true,
                        'purchase_price' => 0,
                        'selling_price' => 0,
                    ]
                );

                if ($record->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        $this->command?->info("Cold storage demo products ready ({$created} created).");
    }
}

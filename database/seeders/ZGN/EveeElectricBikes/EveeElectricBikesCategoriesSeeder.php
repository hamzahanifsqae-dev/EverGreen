<?php

namespace Database\Seeders\ZGN\EveeElectricBikes;

use App\Models\Category;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EveeElectricBikesCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        $create = function (string $name, ?string $parentId = null) use ($merchant) {
            return Category::firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'parent_id' => $parentId,
                    'name' => $name,
                ],
                [
                    'id' => Str::uuid(),
                ]
            );
        };

        $electricBikes = $create('Electric Bikes');

        $create('Complete Bikes', $electricBikes->id);
        $create('Batteries', $electricBikes->id);
        $create('Chargers', $electricBikes->id);
        $create('Tires & Tubes', $electricBikes->id);
        $create('Brakes', $electricBikes->id);
        $create('Frames', $electricBikes->id);
        $create('Motors', $electricBikes->id);
        $create('Controllers', $electricBikes->id);
        $create('Accessories', $electricBikes->id);
    }
}

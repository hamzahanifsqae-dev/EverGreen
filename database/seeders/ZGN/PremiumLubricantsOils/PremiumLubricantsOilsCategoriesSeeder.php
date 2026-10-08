<?php

namespace Database\Seeders\ZGN\PremiumLubricantsOils;

use App\Models\Category;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PremiumLubricantsOilsCategoriesSeeder extends Seeder
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

        $lubricantsOils = $create('Premium Lubricants & Oils');

        $create('Engine Oil', $lubricantsOils->id);
        $create('Transmission Oil', $lubricantsOils->id);
        $create('Gear Oil', $lubricantsOils->id);
        $create('Brake Fluid', $lubricantsOils->id);
        $create('Coolant', $lubricantsOils->id);
        $create('Grease', $lubricantsOils->id);
        $create('Additives', $lubricantsOils->id);
    }
}

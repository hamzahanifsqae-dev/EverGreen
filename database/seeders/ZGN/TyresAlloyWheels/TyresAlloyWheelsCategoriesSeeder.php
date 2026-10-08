<?php

namespace Database\Seeders\ZGN\TyresAlloyWheels;

use App\Models\Category;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TyresAlloyWheelsCategoriesSeeder extends Seeder
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

        $tyresWheels = $create('Tyres & Alloy Wheels');

        $create('Car Tyres', $tyresWheels->id);
        $create('Motorcycle Tyres', $tyresWheels->id);
        $create('Alloy Wheels', $tyresWheels->id);
        $create('Steel Wheels', $tyresWheels->id);
        $create('Wheel Accessories', $tyresWheels->id);
        $create('Tire Accessories', $tyresWheels->id);
    }
}

<?php

namespace Database\Seeders\ZGN;

use App\Models\Brand;
use App\Models\BrandCategory;
use App\Models\Category;
use App\Models\Merchant;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZGNBrandsSeeder extends Seeder
{
    /**
     * @throws Exception
     */
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        /**
         * Resolve category by name (merchant scoped)
         */
        $category = function (string $name) use ($merchant): Category {
            $cat = Category::where('merchant_id', $merchant->id)
                ->where('name', $name)
                ->first();

            if (! $cat) {
                throw new Exception("Category '{$name}' not found for merchant {$merchant->id}");
            }

            return $cat;
        };

        /**
         * Create brand globally + attach to category via pivot
         */
        $attach = function (string $brandName, string $categoryName) use ($merchant, $category) {
            $cat = $category($categoryName);

            // 1️⃣ Brand (GLOBAL per merchant)
            $brand = Brand::firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'name' => $brandName,
                ],
                [
                    'id' => Str::uuid(),
                ]
            );

            // 2️⃣ Brand ↔ Category (PIVOT)
            BrandCategory::firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'brand_id' => $brand->id,
                    'category_id' => $cat->id,
                ],
                [
                    'id' => Str::uuid(),
                ]
            );
        };

        /* ================= SOLAR PANELS ================= */

        foreach ([
            'Longi',
            'JA Solar',
            'Jinko Solar',
            'Canadian Solar',
            'Trina Solar',
        ] as $brand) {
            $attach($brand, 'Solar Panels');
        }

        /* ================= INVERTERS ================= */

        foreach ([
            'Huawei',
            'Growatt',
            'Inverex',
            'GoodWe',
            'Solis',
        ] as $brand) {
            $attach($brand, 'Inverters');
        }

        /* ================= BATTERIES ================= */

        foreach ([
            'Phoenix',
            'Exide',
            'AGS',
            'Narada',
            'Pylontech',
        ] as $brand) {
            $attach($brand, 'Batteries');
        }

        /* ================= MONITORING DEVICES ================= */

        foreach ([
            'Huawei',
            'Growatt',
            'Generic',
        ] as $brand) {
            $attach($brand, 'Monitoring Devices');
        }

        /* ================= MOUNTING STRUCTURES ================= */

        foreach ([
            'Flowdesk Fabrication',
            'Local Fabricator',
        ] as $brand) {
            $attach($brand, 'Mounting Structures');
        }

        /* ================= ELECTRICAL & SAFETY ================= */

        foreach ([
            'Schneider Electric',
            'ABB',
        ] as $brand) {
            $attach($brand, 'Circuit Protection');
        }

        /* ================= BATTERY ACCESSORIES ================= */

        foreach ([
            'Generic',
            'Flowdesk Accessories',
        ] as $brand) {
            $attach($brand, 'Battery Accessories');
        }

        /* ================= EARTHING ================= */

        foreach ([
            'Generic',
        ] as $brand) {
            $attach($brand, 'Earthing');
            $attach($brand, 'Cable Management');
        }

        /* ================= COMMUNICATION MODULES ================= */

        foreach ([
            'Huawei',
            'Growatt',
            'Generic',
        ] as $brand) {
            $attach($brand, 'Communication Modules');
        }

        /* ================= SERVICES ================= */

        foreach ([
            'Flowdesk Services',
        ] as $brand) {
            $attach($brand, 'Installation');
            $attach($brand, 'Net Metering Processing');
            $attach($brand, 'AMC');
        }
    }
}

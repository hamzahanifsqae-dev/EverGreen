<?php

namespace Database\Seeders\ZGN;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZGNBrandModelsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        /**
         * Helper: get brand by name
         */
        $brand = function (string $name) use ($merchant) {
            return Brand::where('merchant_id', $merchant->id)
                ->where('name', $name)
                ->first();
        };

        /**
         * Helper: create model
         */
        $create = function (string $brandName, array $models) use ($merchant, $brand) {
            $b = $brand($brandName);
            if (! $b) {
                return;
            }

            foreach ($models as $model) {
                BrandModel::firstOrCreate(
                    [
                        'merchant_id' => $merchant->id,
                        'brand_id' => $b->id,
                        'name' => $model,
                    ],
                    [
                        'id' => Str::uuid(),
                    ]
                );
            }
        };

        /* ================= SOLAR PANELS ================= */

        $create('Longi', [
            'LONGI-LR5-72HPH-540M',
            'LONGI-LR5-72HPH-550M',
            'LONGI-LR5-72HPH-560M',
        ]);

        $create('JA Solar', [
            'JA-JAM72S30-545-MR',
            'JA-JAM72S30-550-MR',
        ]);

        $create('Jinko Solar', [
            'JINKO-JKM550M-72HL4-V',
            'JINKO-JKM555M-72HL4-V',
        ]);

        $create('Canadian Solar', [
            'CANADIAN-CS6W-545MS',
            'CANADIAN-CS6W-550MS',
        ]);

        $create('Trina Solar', [
            'TRINA-TSM-DE18M-545',
            'TRINA-TSM-DE18M-550',
        ]);

        /* ================= INVERTERS ================= */

        $create('Huawei', [
            'HUAWEI-SUN2000-5K',
            'HUAWEI-SUN2000-10K',
            'HUAWEI-SUN2000-20K',
        ]);

        $create('Growatt', [
            'GROWATT-MIN-5000TL-X',
            'GROWATT-MOD-10KTL3-X',
            'GROWATT-MID-20KTL3-X',
        ]);

        $create('Inverex', [
            'INVEREX-AXPERT-VM-III-5K',
            'INVEREX-NITROX-10KW',
            'INVEREX-NITROX-15KW',
        ]);

        $create('GoodWe', [
            'GOODWE-GW5000-ES',
            'GOODWE-GW10K-ET',
        ]);

        $create('Solis', [
            'SOLIS-S5-GR1P5K',
            'SOLIS-S5-GC20K',
        ]);

        /* ================= BATTERIES ================= */

        $create('Phoenix', [
            'PHOENIX-TX-1800',
            'PHOENIX-TX-2500',
        ]);

        $create('Exide', [
            'EXIDE-TR-2000',
            'EXIDE-TR-2500',
        ]);

        $create('AGS', [
            'AGS-SP-1800',
            'AGS-SP-2000',
        ]);

        $create('Narada', [
            'NARADA-REXC-1000',
            'NARADA-REXC-2000',
        ]);

        $create('Pylontech', [
            'PYLONTECH-US2000C',
            'PYLONTECH-US3000C',
        ]);

        /* ================= BATTERY ACCESSORIES ================= */

        $create('Generic', [
            'GENERIC-BATTERY-ACCESSORY',
        ]);

        $create('Flowdesk Accessories', [
            'FD-BATTERY-ACCESSORY',
        ]);

        /* ================= EARTHING ================= */

        $create('Generic', [
            'GENERIC-EARTHING',
        ]);

        /* ================= MONITORING DEVICES ================= */

        $create('Huawei', [
            'HUAWEI-MONITORING',
        ]);

        $create('Growatt', [
            'GROWATT-MONITORING',
        ]);

        $create('Generic', [
            'GENERIC-MONITORING',
        ]);

        /* ================= COMMUNICATION MODULES ================= */

        $create('Huawei', [
            'HUAWEI-COMMUNICATION',
        ]);

        $create('Growatt', [
            'GROWATT-COMMUNICATION',
        ]);

        $create('Generic', [
            'GENERIC-COMMUNICATION',
        ]);

        /* ================= STRUCTURES ================= */

        $create('Flowdesk Fabrication', [
            'FD-L1-ROOF-MOUNT',
            'FD-L2-ELEVATED',
            'FD-L3-HIGH-ELEVATION',
        ]);

        $create('Local Fabricator', [
            'LOCAL-GI-FRAME',
            'LOCAL-AL-FRAME',
        ]);

        /* ================= SERVICES ================= */

        $create('Flowdesk Services', [
            'FD-INSTALLATION-SERVICE',
            'FD-NET-METERING-SERVICE',
            'FD-AMC-SERVICE',
        ]);
    }
}

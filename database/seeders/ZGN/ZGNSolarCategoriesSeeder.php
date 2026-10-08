<?php

namespace Database\Seeders\ZGN;

use App\Models\Category;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZGNSolarCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::where('email', 'info@evergreen.com')->first();
        if (! $merchant) {
            return;
        }

        // Helper
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

        /* ================= ROOT ================= */

        $electronics = $create('Electronics');
        $electrical = $create('Electrical Infrastructure');
        $storage = $create('Energy Storage');
        $mechanical = $create('Mechanical & Structural');
        $safety = $create('Safety & Protection');
        $monitoring = $create('Monitoring & Smart Systems');
        $tools = $create('Installation Tools & Equipment');
        $consumables = $create('Consumables & Accessories');
        $services = $create('Services');
        $systems = $create('Solar Systems');

        /* ================= ELECTRONICS ================= */

        /* ================= ELECTRONICS ================= */

        $create('Solar Panels', $electronics->id);
        $create('Inverters', $electronics->id);
        $create('Power Electronics', $electronics->id);

        /* ================= ELECTRICAL ================= */

        $create('DC Side Components', $electrical->id);
        $create('AC Side Components', $electrical->id);

        /* ================= ENERGY STORAGE ================= */

        $create('Batteries', $storage->id);
        $create('Battery Accessories', $storage->id);

        /* ================= MECHANICAL ================= */

        $create('Mounting Structures', $mechanical->id);
        $create('Structural Components', $mechanical->id);

        /* ================= SAFETY ================= */

        $create('Circuit Protection', $safety->id);
        $create('Surge Protection', $safety->id);
        $create('Earthing', $safety->id);

        /* ================= MONITORING ================= */

        $create('Monitoring Devices', $monitoring->id);
        $create('Communication Modules', $monitoring->id);

        /* ================= TOOLS ================= */

        $create('Electrical Tools', $tools->id);
        $create('Mechanical Tools', $tools->id);
        $create('Safety Gear', $tools->id);

        /* ================= CONSUMABLES ================= */

        $create('Cable Management', $consumables->id);
        $create('Insulation & Tapes', $consumables->id);
        $create('Labels & Accessories', $consumables->id);

        /* ================= SERVICES ================= */

        $create('Installation', $services->id);
        $create('Net Metering Processing', $services->id);
        $create('AMC', $services->id);

        /* ================= SYSTEMS ================= */

        $create('Residential Systems', $systems->id);
        $create('Commercial Systems', $systems->id);
    }
}

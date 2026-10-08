<?php

namespace Database\Seeders;

use App\Models\CashFlow;
use App\Models\City;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class CashFlowsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::query()
            ->where('email', 'info@evergreen.com')
            ->first() ?? Merchant::query()->first();

        if (! $merchant) {
            $this->command?->warn('Cash flows skipped: no merchant found.');

            return;
        }

        $countryId = Country::query()->where('code', 'PK')->value('id') ?? Country::query()->value('id');
        $cityId = City::query()->where('name', 'Karachi')->value('id') ?? City::query()->value('id');

        $cashFlows = [
            // Account Receivable / Loan
            ['name' => 'Yousif rice Mill', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 6500000],
            ['name' => 'Lifter', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 1786450],
            ['name' => 'Ayan Rice Mill', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 280000],
            ['name' => 'Jameel Shos', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 468000],
            ['name' => 'Ajmal Lahore Home', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 750000],
            ['name' => 'EVEE Mobile', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 245000],
            ['name' => 'Ch Naveed Shamkot', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 215500],
            ['name' => 'Sajjad Ramzan', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 135000],
            ['name' => 'Farhan Rice Mill', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 3137900],
            ['name' => 'Sh Nadeem Lhr', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 700000],
            ['name' => 'Azhar Cold Store', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 900000],
            ['name' => 'S Amjad Baqapur', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 65000],
            ['name' => 'Ittefaq Rice Mill', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 100000],
            ['name' => 'Ch Azhar Hujra', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 487600],
            ['name' => 'Hamza Cold Store', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 2600000],
            ['name' => 'Hamza Cold Store New', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 1340000],
            ['name' => 'Karman Wala Flour', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 1260000],
            ['name' => 'Ahmad Jhelania', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 214500],
            ['name' => 'Bata Tennery Kasur', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 400000],
            ['name' => 'Hakim Cold Store', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 200000],
            ['name' => 'Aslam Pakpatan', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 400000],
            ['name' => 'Ali M Pethan', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 204000],
            ['name' => 'Mian Islam Pepsi', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 321000],
            ['name' => 'Bata Tennery Home', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 180000],
            ['name' => 'Sajjad Bhatti', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 388500],
            ['name' => 'Haji Tahir Rajowal', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 1964500],
            ['name' => 'Zulfar itfaq', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 314000],
            ['name' => 'Electro Motion', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 298300],
            ['name' => 'Noor Rice Mill', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 250000],
            ['name' => 'Aqeel Kasur', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 1561100],
            ['name' => 'Rao Babar', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 718000],
            ['name' => 'Hasan mani Lhr', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 350000],
            ['name' => 'Ittehad Rice Mill', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 11000],
            ['name' => 'M Amjad Renala', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 200000],
            ['name' => 'Noor Saray Mughal', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 100000],
            ['name' => 'Dua Rice Mil', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 400000],
            ['name' => 'Ijaz cold chunian', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 1350000],
            ['name' => 'Almadina Rice Mil', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 113900],
            ['name' => 'Atta Ur Rehman Muredky', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 308600],
            ['name' => 'Ali Inverter EVee', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 172700],
            ['name' => 'Asim Rajowal', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 150000],
            ['name' => 'Haji Ashraf Rajowal', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 12000000],
            ['name' => 'Umair Kasur zic', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 790000],
            ['name' => 'Sh Saeed Pakarab', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 400000],
            ['name' => 'Ch Naqash Riaz', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 54500],
            ['name' => 'Rana Ourangzaib chunian', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 350000],
            ['name' => 'U Vas University', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 2100000],
            ['name' => 'Ch Sajjad Jambar', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 3165650],
            ['name' => 'Haji Rayyat Ali', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 230400],
            ['name' => 'Prime Energy', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 12448350],
            ['name' => 'Arshad Jutt', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 8000],
            ['name' => 'Moeen Dealar', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 10000],
            ['name' => 'Sabir Parts Wala', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 35000],
            ['name' => 'Ijaz Okara', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 17850],
            ['name' => 'Zafar Brix', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 158300],
            ['name' => 'Chacha Nazir Kory sial', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 46000],
            ['name' => 'Ghulam Qadir', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 167200],
            ['name' => 'Umer Farooq', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 9200],
            ['name' => 'Sohail Rajowal', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 52300],
            ['name' => 'Zubair Police', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 62700],
            ['name' => 'Rao Hameed', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 78500],
            ['name' => 'Mehmood Lkri wala', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 217000],
            ['name' => 'Maqsood Khan', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 57350],
            ['name' => 'Ch Anas Bulding', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 19750],
            ['name' => 'Ahmad Kambo', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 130800],
            ['name' => 'Umair Rajowal', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 125000],
            ['name' => 'Ahsan Cabel', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 253300],
            ['name' => 'Rao Jalal', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 150000],
            ['name' => 'Salamet Chunian', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 51500],
            ['name' => 'Tariq Zoom pump', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 30600],
            ['name' => 'Riaz Joia khudian', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 378200],
            ['name' => 'Raza Bugti', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 20000],
            ['name' => 'waqas arzani pur', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 500000],
            ['name' => 'ShehrYar Lhr', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 2433500],
            ['name' => 'Commetti', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 900000],
            ['name' => 'Shareef meo', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 118000],
            ['name' => 'Malik Rerafat', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 1400000],
            ['name' => 'Allah Tawakal', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 1708000],
            ['name' => 'Yaseen Solar', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 2000],
            ['name' => 'Bota', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 20000],
            ['name' => 'Naseem shah', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 273300],
            ['name' => 'Qaisar Saodi', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 165000],
            ['name' => 'Nawaz Dhop Seri', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 192000],
            ['name' => 'Ajmeer Lahor', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 29576200],
            ['name' => 'Haji Shafiq Jaj', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 162850],
            ['name' => 'Amjad kasur', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 3300000],
            ['name' => 'Majeed kasur', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 735500],
            ['name' => 'Amaar dvr', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 200000],
            ['name' => 'Anas Dhing shah', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 102000],
            ['name' => 'Rana yaseen', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 372000],
            ['name' => 'Tariq gujrat', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 100000],
            ['name' => 'Abdullah', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 100000],
            ['name' => 'Mia Refaqat Colony', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 4680000],
            ['name' => 'farhan Solis', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 72000],
            ['name' => 'Dr rizwan sp lhr', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 220000],
            ['name' => 'rana Bashir elh', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 210000],
            ['name' => 'Mudasar Solar Nobal food', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 128700],
            ['name' => 'Ashiq Lahor', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 85000],
            ['name' => 'Sajjad Ul Hasan Lhr', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 264000],
            ['name' => 'Faisal Kory sial', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 70000],
            ['name' => 'Ramzan elec lhr', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 105000],
            ['name' => 'Asif cheshti sham Deen', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 175000],
            ['name' => 'm Asif Attari', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 34000],
            ['name' => 'Hakim Ali bigiana', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 115150],
            ['name' => 'Shehzad Kamboo', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 160000],
            ['name' => 'Mohsin rice mill', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 181200],
            ['name' => 'Sardar Zubair Talwndi', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 112500],
            ['name' => 'Shamas AD Hotal', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 89000],
            ['name' => 'Asif Bashir', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 122600],
            ['name' => 'Akbar Cold Store', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 454700],
            ['name' => 'Khadim Solar', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 39000],
            ['name' => 'Sh Saeed Rice Mill', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 50000],
            ['name' => 'Mehfooz Tyre lhr', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 63000],
            ['name' => 'Javed Shamshad', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 25000],
            ['name' => 'Mudsar Arzanipur', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 17700],
            ['name' => 'Nusrat cold store', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 15000],
            ['name' => 'Ghulam Nabi Sindho', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 62800],
            ['name' => 'S Waseem Gnja', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 81600],
            ['name' => 'Dr aman talwndi', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 8000],
            ['name' => 'Sardar Yahya', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 12500],
            ['name' => 'Rana Iqbal', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 12000],
            ['name' => 'Bashart', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 20000],
            ['name' => 'Ashraf SFood', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 10000],
            ['name' => 'Manzoor AHB', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 8500],
            ['name' => 'Sarwar News', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 8000],
            ['name' => 'Taj Dogar', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 9000],
            ['name' => 'Javed Qila', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 6000],
            ['name' => 'Anas Gulshan', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 10000],
            ['name' => 'Ray Aslam', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 14500],
            ['name' => 'Hafiz Yasir SHO', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 15000],
            ['name' => 'Dr Usman', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 69500],
            ['name' => 'Ray Kashif', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 20000],
            ['name' => 'M Sarwar', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 13000],
            ['name' => 'Razaq Pump', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 10000],
            ['name' => 'Nisar Atoz', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 61500],
            ['name' => 'Akhtar Tyres', 'party_type' => 'Customer', 'flow_type' => 'Loan', 'amount' => 160500],
            ['name' => 'javed Tyres Okara', 'party_type' => 'Vendor', 'flow_type' => 'Loan', 'amount' => 631200],
            ['name' => 'irfan tyres okara', 'party_type' => 'Vendor', 'flow_type' => 'Loan', 'amount' => 2770600],

            // Account Payable / Advance
            ['name' => 'Haji Iqbal Rice Mill', 'party_type' => 'Customer', 'flow_type' => 'Advanced', 'amount' => 5800000],
            ['name' => 'Fazal Abbas Rajowal', 'party_type' => 'Customer', 'flow_type' => 'Advanced', 'amount' => 1400000],
            ['name' => 'P TCL', 'party_type' => 'Customer', 'flow_type' => 'Advanced', 'amount' => 175800],
            ['name' => 'Adeel Lahor', 'party_type' => 'Customer', 'flow_type' => 'Advanced', 'amount' => 100000],
            ['name' => 'Farakh sahib Kasur', 'party_type' => 'Customer', 'flow_type' => 'Advanced', 'amount' => 1564800],
            ['name' => 'Allah Tawakal Home', 'party_type' => 'Customer', 'flow_type' => 'Advanced', 'amount' => 3523400],
            ['name' => 'Sabir Cold Store khudian', 'party_type' => 'Customer', 'flow_type' => 'Advanced', 'amount' => 1937800],
            ['name' => 'M Saleem Thekedar', 'party_type' => 'Customer', 'flow_type' => 'Advanced', 'amount' => 35000],
            ['name' => 'Haji Azam Rajowal', 'party_type' => 'Customer', 'flow_type' => 'Advanced', 'amount' => 10056000],
            ['name' => 'AU Solar', 'party_type' => 'Vendor', 'flow_type' => 'Advanced', 'amount' => 6560680],
            ['name' => 'AE Get Power', 'party_type' => 'Vendor', 'flow_type' => 'Advanced', 'amount' => 24272750],
            ['name' => 'Madina Solar', 'party_type' => 'Vendor', 'flow_type' => 'Advanced', 'amount' => 1206000],
            ['name' => 'Apex Bettery', 'party_type' => 'Vendor', 'flow_type' => 'Advanced', 'amount' => 2760000],
        ];

        foreach ($cashFlows as $data) {
            $partyType = $data['party_type'] === 'Vendor' ? Vendor::class : Customer::class;
            $flowType = $this->normalizeFlowType($data['flow_type']);
            $party = $this->firstOrCreateParty(
                $partyType,
                trim($data['name']),
                $merchant->id,
                $countryId,
                $cityId,
            );

            CashFlow::query()->updateOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'party_type' => $partyType,
                    'party_id' => $party->id,
                    'settlement_for_id' => null,
                    'flow_type' => $flowType,
                ],
                [
                    'direction' => CashFlow::primaryDirectionForFlowType($flowType),
                    'amount' => $data['amount'],
                    'flow_date' => now()->toDateString(),
                    'method' => 'Cash',
                    'reference_no' => null,
                    'notes' => 'Seeded '.CashFlow::flowTypeLabel($flowType),
                ],
            );
        }
    }

    private function normalizeFlowType(string $flowType): string
    {
        return strtolower($flowType) === 'loan' ? 'loan' : 'advance';
    }

    private function firstOrCreateParty(string $partyType, string $name, string $merchantId, ?string $countryId, ?string $cityId): Customer|Vendor
    {
        $model = $partyType === Vendor::class ? Vendor::class : Customer::class;

        return $model::query()->firstOrCreate(
            [
                'merchant_id' => $merchantId,
                'name' => $name,
            ],
            [
                'phone' => null,
                'email' => null,
                'country_id' => $countryId,
                'city_id' => $cityId,
                'postal_code' => '54000',
                'address' => null,
                'reference' => 'Seeded cash flow',
            ],
        );
    }
}

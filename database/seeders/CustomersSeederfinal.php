<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomersSeederfinal extends Seeder
{
    private array $customerNames = [
        'Yousif rice Mill',
        'Lifter',
        'Ayan Rice Mill',
        'Jameel Shos',
        'Ajmal Lahore Home',
        'EVEE Mobile',
        'Ch Naveed Shamkot',
        'Sajjad Ramzan',
        'Farhan Rice Mill',
        'Sh Nadeem Lhr',
        'Azhar Cold Store',
        'S Amjad Baqapur',
        'Ittefaq Rice Mill',
        'Ch Azhar Hujra',
        'Hamza Cold Store',
        'Hamza Cold Store 2',
        'Karman Wala Flour',
        'Ahmad Jhelania',
        'Bata Tennery Kasur',
        'Hakim Cold Store',
        'Aslam Pakpatan',
        'Ali M Pethan',
        'Mian Islam Pepsi',
        'Bata Tennery Home',
        'Sajjad Bhatti',
        'Haji Tahir Rajowal',
        'Zulfar itfaq',
        'Electro Motion',
        'Noor Rice Mill',
        'Aqeel Kasur',
        'Rao Babar',
        'Hasan mani Lhr',
        'Ittehad Rice Mill',
        'M Amjad Renala',
        'Noor Saray Mughal',
        'Dua Rice Mil',
        'Ijaz cold chunian',
        'Almadina Rice Mil',
        'Atta Ur Rehman Muredky',
        'Ali Inverter EVee',
        'Asim Rajowal',
        'Haji Ashraf Rajowal',
        'Umair Kasur zic',
        'Sh Saeed Pakarab',
        'Ch Naqash Riaz',
        'Rana Ourangzaib chunian',
        'U Vas University',
        'Ch Sajjad Jambar',
        'Haji Rayyat Ali',
        'Prime Energy',
        'Arshad Jutt',
        'Moeen Dealar',
        'Sabir Parts Wala',
        'Ijaz Okara',
        'Zafar Brix',
        'Chacha Nazir Kory sial',
        'Ghulam Qadir',
        'Umer Farooq',
        'Sohail Rajowal',
        'Zubair Police',
        'Rao Hameed',
        'Mehmood Lkri wala',
        'Maqsood Khan',
        'Ch Anas Bulding',
        'Ahmad Kambo',
        'Umair Rajowal',
        'Ahsan Cabel',
        'Rao Jalal',
        'Salamet Chunian',
        'Tariq Zoom pump',
        'Riaz Joia khudian',
        'Raza Bugti',
        'waqas arzani pur',
        'ShehrYar Lhr',
        'Commetti',
        'Shareef meo',
        'Malik Rerafat',
        'Allah Tawakal',
        'Yaseen Solar',
        'Bota',
        'Naseem shah',
        'Qaisar Saodi',
        'Nawaz Dhop Seri',
        'Ajmeer Lahor',
        'Haji Shafiq Jaj',
        'Amjad kasur',
        'Majeed kasur',
        'Amaar dvr',
        'Anas Dhing shah',
        'Rana yaseen',
        'Tariq gujrat',
        'Abdullah',
        'Mia Refaqat Colony',
        'farhan Solis',
        'Dr rizwan sp lhr',
        'rana Bashir elh',
        'Mudasar Solar Nobal food',
        'Ashiq Lahor',
        'Sajjad Ul Hasan Lhr',
        'Faisal Kory sial',
        'Ramzan elec lhr',
        'Asif cheshti sham Deen',
        'm Asif Attari',
        'Hakim Ali bigiana',
        'Shehzad Kamboo',
        'Mohsin rice mill',
        'Sardar Zubair Talwndi',
        'Shamas AD Hotal',
        'Asif Bashir',
        'Akbar Cold Store',
        'Khadim Solar',
        'Sh Saeed Rice Mill',
        'Mehfooz Tyre lhr',
        'Haji Iqbal Rice Mill',
        'AU Solar',
        'AE Get Power',
        'Fazal Abbas Rajowal',
        'P TCL',
        'Madina Solar',
        'Apex Bettery',
        'Adeel Lahor',
        'Farakh sahib Kasur',
        'Allah Tawakal Home',
        'Sabir Cold Store khudian',
        'M Saleem Thekedar',
        'Haji Azam Rajowal',
        'Javed Shamshad',
        'Mudsar Arzanipur',
        'Nusrat cold store',
        'Ghulam Nabi Sindho',
        'S Waseem Gnja',
        'Dr aman talwndi',
        'Sardar Yahya',
        'Rana Iqbal',
        'Bashart',
        'Ashraf SFood',
        'Manzoor AHB',
        'Sarwar News',
        'Taj Dogar',
        'Javed Qila',
        'Anas Gulshan',
        'Ray Aslam',
        'Hafiz Yasir SHO',
        'Dr Usman',
        'Ray Kashif',
        'M Sarwar',
        'Razaq Pump',
        'Nisar Atoz',
        'Akhtar Tyres',
    ];

    public function run(): void
    {
        $pakistan = Country::where('code', 'PK')->first();
        $lahore = City::where('name', 'Lahore')->first();

        if (! $pakistan || ! $lahore) {
            return;
        }

        $merchants = Merchant::whereIn('email', [
            'info@evergreen.com',
            'info@halaynoor.com',
        ])->get();

        foreach ($merchants as $merchant) {
            $this->createCustomers(
                $merchant->id,
                $pakistan->id,
                $lahore->id
            );
        }
    }

    private function createCustomers(
        string $merchantId,
        string $countryId,
        string $cityId
    ): void {
        foreach ($this->customerNames as $index => $name) {
            $i = $index + 1;

            Customer::firstOrCreate(
                [
                    'email' => "demo{$i}-{$merchantId}@customer.com",
                ],
                [
                    'id' => Str::uuid(),
                    'merchant_id' => $merchantId,
                    'name' => $name,
                    'phone' => '+920900786010',
                    'country_id' => $countryId,
                    'city_id' => $cityId,
                    'postal_code' => '54000',
                    'address' => 'Lahore, Pakistan',
                    'reference' => 'Walk-in',
                ]
            );
        }
    }
}

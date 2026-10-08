<?php

namespace Database\Seeders;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\AttachmentMetaType;
use App\Enums\AttachmentType;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\Business;
use App\Models\CashFlow;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Merchant;
use App\Models\MerchantSetting;
use App\Models\Order;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseItemVariant;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseReturnItemVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemVariant;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SaleReturnItemVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\DemoAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    // ─── Merchant to seed for ─────────────────────────────────────
    private string $merchantEmail = 'info@evergreen.com';

    private ?string $staffEmailNamespace = null;

    public function forMerchant(string $email): void
    {
        $this->merchantEmail = $email;
        $this->staffEmailNamespace = null;

        if (DemoAccount::isTemporaryDemoEmail($email)
            && preg_match('/^demo-([a-f0-9]{32})@/i', $email, $matches) === 1) {
            $this->staffEmailNamespace = substr($matches[1], 0, 8);
        }

        $this->run();
    }

    public function run(): void
    {
        $this->command->info('🚀 Starting DemoSeeder...');

        $merchant = Merchant::where('email', $this->merchantEmail)->first();

        if (! $merchant) {
            $this->command->error("Merchant [{$this->merchantEmail}] not found. Run MerchantsSeeder first.");

            return;
        }

        $this->command->info("✅ Merchant found: {$merchant->name}");

        // Run in order
        $business = $this->seedBusiness($merchant);
        $branches = $this->seedBranches($merchant, $business);
        $roles = $this->seedRoles();
        $staff = $this->seedStaff($merchant, $branches, $roles);
        $categories = $this->seedCategories($merchant);
        [$products, $variants] = $this->seedProducts($merchant, $branches, $categories);
        $customers = $this->seedCustomers($merchant);
        $vendors = $this->seedVendors($merchant);
        $this->seedPurchases($merchant, $business, $branches, $vendors, $products, $variants, $staff);
        $this->seedSales($merchant, $branches, $customers, $products, $variants, $staff);
        $this->seedExpenses($merchant, $business, $branches, $staff);
        $this->seedPayrolls($merchant, $staff);
        $this->seedCashFlows($merchant, $business, $branches, $customers, $vendors, $staff);
        $this->seedAssets($merchant, $business, $branches, $vendors, $staff);
        $this->seedSaleReturns($merchant, $staff);
        $this->seedPurchaseReturns($merchant, $staff);
        $this->seedMerchantProfile($merchant);

        $this->command->info('');
        $this->command->info('✅ DemoSeeder completed successfully!');

        if ($this->staffEmailNamespace === null) {
            $this->command->info('─────────────────────────────────────────────');
            $this->command->info('  Login credentials for seeded staff:');
            $this->command->info('  Manager  → manager@demo.com  / password123');
            $this->command->info('  Staff 1  → staff1@demo.com   / password123');
            $this->command->info('  Staff 2  → staff2@demo.com   / password123');
            $this->command->info('─────────────────────────────────────────────');
        }
    }

    private function scopedStaffEmail(string $localPart): string
    {
        if ($this->staffEmailNamespace === null) {
            return "{$localPart}@demo.com";
        }

        return "{$localPart}.{$this->staffEmailNamespace}@demo.com";
    }

    private function demoDocumentSuffix(): string
    {
        if ($this->staffEmailNamespace === null) {
            return '';
        }

        return '-'.$this->staffEmailNamespace;
    }

    // ═══════════════════════════════════════════════════════════════
    // 1. BUSINESS
    // ═══════════════════════════════════════════════════════════════
    private function seedBusiness(Merchant $merchant): Business
    {
        $business = Business::firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'name' => 'Flowdesk Demo Business',
            ],
            [
                'id' => Str::uuid()->toString(),
            ]
        );

        $this->command->info("  📦 Business: {$business->name}");

        return $business;
    }

    // ═══════════════════════════════════════════════════════════════
    // 2. BRANCHES (2)
    // ═══════════════════════════════════════════════════════════════
    private function seedBranches(Merchant $merchant, Business $business): array
    {
        $branchData = [
            ['name' => 'Main Branch - Lahore', 'address' => 'Main Boulevard, Lahore'],
            ['name' => 'Branch 2 - Karachi',   'address' => 'Clifton, Karachi'],
        ];

        $branches = [];

        foreach ($branchData as $data) {
            $branch = Branch::firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'name' => $data['name'],
                ],
                [
                    'id' => Str::uuid()->toString(),
                    'business_id' => (string) $business->id,
                    'address' => $data['address'],
                    'postal_code' => '54000',
                    'status' => 'active',
                    'is_active' => true,
                ]
            );

            $branches[] = $branch;
            $this->command->info("  🏢 Branch: {$branch->name}");
        }

        return $branches;
    }

    // ═══════════════════════════════════════════════════════════════
    // 3. ROLES (2)
    // ═══════════════════════════════════════════════════════════════
    private function seedRoles(): array
    {
        $roleNames = ['Manager', 'Sales Staff'];
        $roles = [];

        foreach ($roleNames as $name) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'staff'],
                ['id' => Str::uuid()->toString()]
            );

            // Assign permissions via raw DB to avoid UUID casting issue with Spatie
            if ($name === 'Manager') {
                $permIds = \DB::table('permissions')->where('guard_name', 'staff')->pluck('id');
            } else {
                $permIds = \DB::table('permissions')->where('guard_name', 'staff')
                    ->where(function ($q) {
                        $q->where('name', 'like', 'sales.%')
                            ->orWhere('name', 'like', 'customers.%')
                            ->orWhere('name', 'like', 'products.%')
                            ->orWhere('name', 'like', 'dashboard.%');
                    })->pluck('id');
            }

            \DB::table('role_has_permissions')->where('role_id', (string) $role->id)->delete();
            foreach ($permIds as $permId) {
                \DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => (string) $permId,
                    'role_id' => (string) $role->id,
                ]);
            }

            $roles[$name] = $role;
            $this->command->info("  🔐 Role: {$name}");
        }

        return $roles;
    }

    // ═══════════════════════════════════════════════════════════════
    // 4. STAFF (3)
    // ═══════════════════════════════════════════════════════════════
    private function seedStaff(Merchant $merchant, array $branches, array $roles): array
    {
        $staffData = [
            [
                'name' => 'Demo Manager',
                'email' => $this->scopedStaffEmail('manager'),
                'role' => 'Manager',
                'branch' => 0,
            ],
            [
                'name' => 'Staff Member One',
                'email' => $this->scopedStaffEmail('staff1'),
                'role' => 'Sales Staff',
                'branch' => 0,
            ],
            [
                'name' => 'Staff Member Two',
                'email' => $this->scopedStaffEmail('staff2'),
                'role' => 'Sales Staff',
                'branch' => 1,
            ],
        ];

        $staff = [];

        foreach ($staffData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'id' => Str::uuid()->toString(),
                    'name' => $data['name'],
                    'password' => Hash::make('password123'),
                    'merchant_id' => (string) $merchant->id,
                    'is_active' => true,
                    'status' => 'verified',
                    'email_verified_at' => now(),
                ]
            );

            // Assign role via raw DB to avoid guard mismatch
            if (isset($roles[$data['role']])) {
                $roleId = (string) $roles[$data['role']]->id;
                \DB::table('model_has_roles')
                    ->where('model_id', (string) $user->id)
                    ->where('model_type', 'App\\Models\\User')
                    ->delete();
                \DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $roleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => (string) $user->id,
                ]);
            }

            // Assign branch — fix: cast UUID to string
            $branch = $branches[$data['branch']];
            $branch->users()->syncWithoutDetaching([(string) $user->id]);

            // Assign all staff permissions directly via raw DB
            $permIds = \DB::table('permissions')->where('guard_name', 'staff')->pluck('id');
            \DB::table('model_has_permissions')
                ->where('model_id', (string) $user->id)
                ->where('model_type', 'App\\Models\\User')
                ->delete();
            foreach ($permIds as $permId) {
                \DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => (string) $permId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => (string) $user->id,
                ]);
            }

            $staff[] = $user;
            $this->command->info("  👤 Staff: {$user->name} ({$data['email']})");
        }

        return $staff;
    }

    // ═══════════════════════════════════════════════════════════════
    // 5. CATEGORIES → BRANDS → MODELS → PRODUCTS → VARIANTS
    // ═══════════════════════════════════════════════════════════════
    private function seedCategories(Merchant $merchant): array
    {
        $categoryData = [
            [
                'name' => 'Inverters',
                'brands' => [
                    ['name' => 'Solis',  'model' => 'Hybrid Series'],
                    ['name' => 'Huawei', 'model' => 'SUN2000 Series'],
                ],
            ],
            [
                'name' => 'Batteries',
                'brands' => [
                    ['name' => 'Pylontech', 'model' => 'US Series'],
                    ['name' => 'Apex',      'model' => 'LiFePO4 Series'],
                ],
            ],
            [
                'name' => 'Solar Panels',
                'brands' => [
                    ['name' => 'TCL',      'model' => 'Mono PERC'],
                    ['name' => 'Canadian', 'model' => 'HiKu Series'],
                ],
            ],
            [
                'name' => 'Cables & Wiring',
                'brands' => [
                    ['name' => 'Finolex', 'model' => 'Solar DC Cable'],
                    ['name' => 'Nexans',  'model' => 'PV1-F Series'],
                ],
            ],
            [
                'name' => 'Accessories',
                'brands' => [
                    ['name' => 'Schneider', 'model' => 'Easy9 Series'],
                    ['name' => 'ABB',       'model' => 'S200 Series'],
                ],
            ],
        ];

        $allCategories = [];

        foreach ($categoryData as $catData) {
            $category = Category::firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'name' => $catData['name'],
                ],
                [
                    'id' => Str::uuid()->toString(),
                ]
            );

            $brandsData = [];

            foreach ($catData['brands'] as $brandData) {
                $brandModel = Brand::firstOrCreate(
                    [
                        'merchant_id' => $merchant->id,
                        'name' => $brandData['name'],
                    ],
                    [
                        'id' => Str::uuid()->toString(),
                    ]
                );

                // Fix: pivot table has id and merchant_id columns, insert manually
                $exists = \DB::table('brand_category')
                    ->where('brand_id', (string) $brandModel->id)
                    ->where('category_id', (string) $category->id)
                    ->exists();

                if (! $exists) {
                    \DB::table('brand_category')->insert([
                        'id' => Str::uuid()->toString(),
                        'brand_id' => (string) $brandModel->id,
                        'category_id' => (string) $category->id,
                        'merchant_id' => (string) $merchant->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $model = BrandModel::firstOrCreate(
                    [
                        'brand_id' => (string) $brandModel->id,
                        'name' => $brandData['model'],
                    ],
                    [
                        'id' => Str::uuid()->toString(),
                        'merchant_id' => (string) $merchant->id,
                    ]
                );

                $brandsData[] = [
                    'brand' => $brandModel,
                    'model' => $model,
                ];
            }

            // Use stdClass to avoid Eloquent dynamic property restriction
            $entry = new \stdClass;
            $entry->id = $category->id;
            $entry->name = $category->name;
            $entry->brands_data = $brandsData;

            $allCategories[] = $entry;
            $this->command->info("  📂 Category: {$category->name}");
        }

        return $allCategories;
    }

    // ═══════════════════════════════════════════════════════════════
    // 6. PRODUCTS (2 per model, 2 variants each)
    // ═══════════════════════════════════════════════════════════════
    private function seedProducts(Merchant $merchant, array $branches, array $categories): array
    {
        $allProducts = [];
        $allVariants = [];

        $productSuffixes = [
            'Inverters' => [['5kw Single Phase', 8000, 9500], ['10kw Three Phase', 18000, 21000]],
            'Batteries' => [['5kwh Lithium', 45000, 52000], ['10kwh Lithium', 85000, 95000]],
            'Solar Panels' => [['620W Mono PERC', 12000, 14000], ['720W Bifacial', 16000, 18500]],
            'Cables & Wiring' => [['4mm DC Cable 100m', 3500, 4200], ['6mm DC Cable 100m', 5500, 6500]],
            'Accessories' => [['MCB 32A', 800, 1100], ['Surge Protector SPD', 2500, 3200]],
        ];

        foreach ($categories as $category) {
            $suffixes = $productSuffixes[$category->name] ?? [
                ['Standard Model A', 5000, 6000],
                ['Standard Model B', 8000, 9500],
            ];

            foreach ($category->brands_data as $brandData) {
                $brand = $brandData['brand'];
                $model = $brandData['model'];

                foreach ($suffixes as $index => [$suffix, $purchasePrice, $sellingPrice]) {
                    $productName = "{$brand->name} {$suffix}";
                    $sku = strtoupper(substr($brand->name, 0, 3)).'-'.($index + 1).'-'.strtoupper(Str::random(4));

                    $product = Product::firstOrCreate(
                        [
                            'merchant_id' => $merchant->id,
                            'sku' => $sku,
                        ],
                        [
                            'id' => Str::uuid()->toString(),
                            'name' => $productName,
                            'brand_model_id' => (string) $model->id,
                            'category_id' => (string) $category->id,
                            'purchase_price' => $purchasePrice,
                            'selling_price' => $sellingPrice,
                            'is_active' => true,
                        ]
                    );

                    // Update category_id if product already exists but has no category
                    if (! $product->category_id) {
                        $product->update(['category_id' => (string) $category->id]);
                    }

                    // Fix: cast UUIDs to string
                    foreach ($branches as $branch) {
                        $branch->products()->syncWithoutDetaching([(string) $product->id]);
                        $product->branches()->syncWithoutDetaching([(string) $branch->id]);
                    }

                    // Create 2 variants per product
                    $variantSpecs = [
                        ['Standard', $sellingPrice],
                        ['Premium',  round($sellingPrice * 1.15, 2)],
                    ];

                    foreach ($variantSpecs as [$variantName, $variantPrice]) {
                        $variantSku = $sku.'-'.strtoupper(substr($variantName, 0, 3));

                        $variant = ProductVariant::firstOrCreate(
                            [
                                'product_id' => (string) $product->id,
                                'name' => $variantName,
                            ],
                            [
                                'id' => Str::uuid()->toString(),
                                'merchant_id' => (string) $merchant->id,
                                'sku' => $variantSku,
                                'selling_price' => $variantPrice,
                                'purchase_price' => $purchasePrice,
                                'is_active' => true,
                            ]
                        );

                        $allVariants[] = $variant;
                    }

                    // Generate and attach a placeholder image for the product
                    if (! $product->productImage) {
                        $this->attachPlaceholderImage($product, $category->name);
                    }

                    $allProducts[] = $product;
                }
            }
        }

        $this->command->info('  🛍️  Products created: '.count($allProducts));
        $this->command->info('  🔖  Variants created: '.count($allVariants));

        return [$allProducts, $allVariants];
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPER: Generate placeholder image for product
    // ═══════════════════════════════════════════════════════════════
    private function attachPlaceholderImage(Product $product, string $categoryName): void
    {
        $colors = [
            'Inverters' => ['bg' => [29, 158, 117],  'text' => [255, 255, 255]],
            'Batteries' => ['bg' => [59, 130, 246],  'text' => [255, 255, 255]],
            'Solar Panels' => ['bg' => [245, 158, 11],  'text' => [255, 255, 255]],
            'Cables & Wiring' => ['bg' => [239, 68, 68],   'text' => [255, 255, 255]],
            'Accessories' => ['bg' => [139, 92, 246],  'text' => [255, 255, 255]],
        ];

        $color = $colors[$categoryName] ?? ['bg' => [107, 114, 128], 'text' => [255, 255, 255]];

        $initials = collect(preg_split('/\s+/', trim($product->name)) ?: [])
            ->take(2)
            ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
            ->implode('');

        // Create PNG using GD
        $size = 400;
        $img = imagecreatetruecolor($size, $size);

        $bg = imagecolorallocate($img, ...$color['bg']);
        $fg = imagecolorallocate($img, ...$color['text']);

        imagefill($img, 0, 0, $bg);

        // Draw initials text centered
        $fontSize = 5; // built-in font size (1-5)
        $charWidth = imagefontwidth($fontSize);
        $charHeight = imagefontheight($fontSize);
        $textWidth = strlen($initials) * $charWidth * 6;
        $x = ($size - $textWidth) / 2;
        $y = ($size - $charHeight * 6) / 2;

        // Scale up by drawing each char
        $scale = 6;
        imagestring($img, $fontSize, (int) (($size - strlen($initials) * imagefontwidth($fontSize) * $scale) / 2),
            (int) (($size - imagefontheight($fontSize) * $scale) / 2), $initials, $fg);

        // Use imagestring with large font
        $font = 5;
        $tw = strlen($initials) * imagefontwidth($font);
        $th = imagefontheight($font);
        $px = (int) (($size - $tw) / 2);
        $py = (int) (($size - $th) / 2);
        imagestring($img, $font, $px, $py, $initials, $fg);

        $directory = 'products/images';
        $filename = Str::uuid()->toString().'.png';
        $path = $directory.'/'.$filename;
        $fullPath = storage_path('app/public/'.$path);

        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        imagepng($img, $fullPath);
        imagedestroy($img);

        $product->productImage()->create([
            'merchant_id' => $product->merchant_id,
            'type' => AttachmentType::IMAGE,
            'meta_type' => AttachmentMetaType::PRODUCT_IMAGE,
            'photo_url' => $path,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // 7. CUSTOMERS (5)
    // ═══════════════════════════════════════════════════════════════
    private function seedCustomers(Merchant $merchant): array
    {
        $customerData = [
            ['name' => 'Ahmad Solar Solutions', 'phone' => '+923001111001', 'email' => 'ahmad.solar@demo.com'],
            ['name' => 'Bilal Energy Systems',  'phone' => '+923001111002', 'email' => 'bilal.energy@demo.com'],
            ['name' => 'Chaudhry Power Works',  'phone' => '+923001111003', 'email' => 'chaudhry.power@demo.com'],
            ['name' => 'Danish Tech Store',     'phone' => '+923001111004', 'email' => 'danish.tech@demo.com'],
            ['name' => 'Farooq Enterprises',    'phone' => '+923001111005', 'email' => 'farooq.ent@demo.com'],
        ];

        $customers = [];

        // Ensure Pakistan exists in countries table
        $pakistan = \DB::table('countries')->where('name', 'Pakistan')->first();
        if (! $pakistan) {
            $pakistanId = Str::uuid()->toString();
            \DB::table('countries')->insert([
                'id' => $pakistanId,
                'name' => 'Pakistan',
                'code' => 'PK',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $pakistanId = $pakistan->id;
        }

        // Ensure Lahore exists in cities table
        $lahore = \DB::table('cities')->where('name', 'Lahore')->where('country_id', $pakistanId)->first();
        if (! $lahore) {
            $lahoreId = Str::uuid()->toString();
            \DB::table('cities')->insert([
                'id' => $lahoreId,
                'name' => 'Lahore',
                'country_id' => $pakistanId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $lahoreId = $lahore->id;
        }

        foreach ($customerData as $data) {
            $customer = Customer::firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'email' => $data['email'],
                ],
                [
                    'id' => Str::uuid()->toString(),
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'address' => 'Lahore, Pakistan',
                    'country_id' => $pakistanId,
                    'city_id' => $lahoreId,
                ]
            );

            $customers[] = $customer;
            $this->command->info("  👥 Customer: {$customer->name}");
        }

        return $customers;
    }

    // ═══════════════════════════════════════════════════════════════
    // 8. VENDORS (5)
    // ═══════════════════════════════════════════════════════════════
    private function seedVendors(Merchant $merchant): array
    {
        $vendorData = [
            ['name' => 'AU Solar Suppliers',   'phone' => '+923002221001', 'email' => 'au.solar@vendor.com'],
            ['name' => 'Apex Battery Depot',   'phone' => '+923002221002', 'email' => 'apex.battery@vendor.com'],
            ['name' => 'Prime Panel Traders',  'phone' => '+923002221003', 'email' => 'prime.panel@vendor.com'],
            ['name' => 'Madina Cable House',   'phone' => '+923002221004', 'email' => 'madina.cable@vendor.com'],
            ['name' => 'National Accessories', 'phone' => '+923002221005', 'email' => 'national.acc@vendor.com'],
        ];

        $vendors = [];

        // Ensure Pakistan + Lahore exist (reuse or re-fetch)
        $pakistanV = \DB::table('countries')->where('name', 'Pakistan')->first();
        if (! $pakistanV) {
            $pakistanVId = Str::uuid()->toString();
            \DB::table('countries')->insert([
                'id' => $pakistanVId,
                'name' => 'Pakistan',
                'code' => 'PK',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $pakistanVId = $pakistanV->id;
        }

        $lahoreV = \DB::table('cities')->where('name', 'Lahore')->where('country_id', $pakistanVId)->first();
        if (! $lahoreV) {
            $lahoreVId = Str::uuid()->toString();
            \DB::table('cities')->insert([
                'id' => $lahoreVId,
                'name' => 'Lahore',
                'country_id' => $pakistanVId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $lahoreVId = $lahoreV->id;
        }

        foreach ($vendorData as $data) {
            $vendor = Vendor::firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'email' => $data['email'],
                ],
                [
                    'id' => Str::uuid()->toString(),
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'address' => 'Lahore, Pakistan',
                    'country_id' => $pakistanVId,
                    'city_id' => $lahoreVId,
                ]
            );

            $vendors[] = $vendor;
            $this->command->info("  🏭 Vendor: {$vendor->name}");
        }

        return $vendors;
    }

    // ═══════════════════════════════════════════════════════════════
    // 9. PURCHASES (2)
    // ═══════════════════════════════════════════════════════════════
    private function seedPurchases(
        Merchant $merchant,
        Business $business,
        array $branches,
        array $vendors,
        array $products,
        array $variants,
        array $staff
    ): void {
        $purchaseConfigs = [
            [
                'vendor' => $vendors[0],
                'branch' => $branches[0],
                'created_by' => $staff[0],
                'days_ago' => 10,
                'product_count' => 3,
            ],
            [
                'vendor' => $vendors[1],
                'branch' => $branches[1],
                'created_by' => $staff[1],
                'days_ago' => 5,
                'product_count' => 2,
            ],
            [
                'vendor' => $vendors[2],
                'branch' => $branches[0],
                'created_by' => $staff[2],
                'days_ago' => 1,
                'product_count' => 2,
            ],
            [
                'vendor' => $vendors[3],
                'branch' => $branches[1],
                'created_by' => $staff[0],
                'days_ago' => 0,
                'product_count' => 2,
            ],
        ];

        foreach ($purchaseConfigs as $i => $config) {
            $purchaseDate = now()->subDays($config['days_ago']);
            $purchaseNo = 'PUR-'.$purchaseDate->format('Ymd').'-DEMO'.($i + 1).$this->demoDocumentSuffix();

            if (Purchase::query()
                ->where('merchant_id', $merchant->id)
                ->where('purchase_no', $purchaseNo)
                ->exists()) {
                $this->command->info("  ⏭️  Purchase {$purchaseNo} already exists, skipping.");

                continue;
            }

            $purchase = Purchase::create([
                'id' => Str::uuid()->toString(),
                'purchase_no' => $purchaseNo,
                'merchant_id' => (string) $merchant->id,
                'vendor_id' => (string) $config['vendor']->id,
                'purchase_date' => $purchaseDate,
                'subtotal' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'due_amount' => 0,
                'payment_type' => 'cash',
                'notes' => 'Demo purchase #'.($i + 1),
                'created_by' => (string) $config['created_by']->id,
            ]);

            $subtotal = 0;
            $selectedProducts = array_slice($products, $i * 3, $config['product_count']);

            foreach ($selectedProducts as $product) {
                $qty = rand(2, 5);
                $unitPrice = $product->purchase_price ?? 10000;
                $lineTotal = $qty * $unitPrice;
                $subtotal += $lineTotal;

                $purchaseItem = PurchaseItem::create([
                    'id' => Str::uuid()->toString(),
                    'purchase_id' => (string) $purchase->id,
                    'business_id' => (string) $business->id,
                    'branch_id' => (string) $config['branch']->id,
                    'product_id' => (string) $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'discount' => 0,
                    'tax' => 0,
                ]);

                $variant = $product->variants()->first();
                if ($variant) {
                    PurchaseItemVariant::create([
                        'id' => Str::uuid()->toString(),
                        'purchase_item_id' => (string) $purchaseItem->id,
                        'product_variant_id' => (string) $variant->id,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ]);
                }
            }

            $purchase->update([
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
                'paid_amount' => $subtotal,
                'due_amount' => 0,
            ]);

            $this->command->info("  🛒 Purchase: {$purchaseNo} — PKR ".number_format($subtotal));
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // 10. SALES (2)
    // ═══════════════════════════════════════════════════════════════
    private function seedSales(
        Merchant $merchant,
        array $branches,
        array $customers,
        array $products,
        array $variants,
        array $staff
    ): void {
        $saleConfigs = [
            [
                'customer' => $customers[0],
                'branch' => $branches[0],
                'created_by' => $staff[0],
                'days_ago' => 7,
                'product_count' => 2,
            ],
            [
                'customer' => $customers[1],
                'branch' => $branches[1],
                'created_by' => $staff[2],
                'days_ago' => 3,
                'product_count' => 3,
            ],
            [
                'customer' => $customers[2],
                'branch' => $branches[0],
                'created_by' => $staff[1],
                'days_ago' => 1,
                'product_count' => 2,
            ],
            [
                'customer' => $customers[3],
                'branch' => $branches[0],
                'created_by' => $staff[0],
                'days_ago' => 0,
                'product_count' => 2,
            ],
            [
                'customer' => $customers[4],
                'branch' => $branches[1],
                'created_by' => $staff[2],
                'days_ago' => 0,
                'product_count' => 2,
                'credit_ratio' => 0.45,
            ],
        ];

        foreach ($saleConfigs as $i => $config) {
            $saleDate = now()->subDays($config['days_ago']);
            $saleNo = 'SAL-'.$saleDate->format('Ymd').'-DEMO'.($i + 1).$this->demoDocumentSuffix();

            if (Sale::query()
                ->where('merchant_id', $merchant->id)
                ->where('sale_no', $saleNo)
                ->exists()) {
                $this->command->info("  ⏭️  Sale {$saleNo} already exists, skipping.");

                continue;
            }

            $sale = Sale::create([
                'id' => Str::uuid()->toString(),
                'sale_no' => $saleNo,
                'merchant_id' => (string) $merchant->id,
                'customer_id' => (string) $config['customer']->id,
                'sale_date' => $saleDate,
                'subtotal' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'due_amount' => 0,
                'notes' => 'Demo sale #'.($i + 1),
                'created_by' => (string) $config['created_by']->id,
            ]);

            $subtotal = 0;
            $selectedProducts = array_slice($products, $i * 2, $config['product_count']);

            foreach ($selectedProducts as $product) {
                $qty = rand(1, 3);
                $unitPrice = $product->selling_price ?? 12000;
                $lineTotal = $qty * $unitPrice;
                $subtotal += $lineTotal;

                $branch = $config['branch'];
                $business = $branch->business;

                $saleItem = SaleItem::create([
                    'id' => Str::uuid()->toString(),
                    'sale_id' => (string) $sale->id,
                    'business_id' => (string) $business->id,
                    'branch_id' => (string) $branch->id,
                    'product_id' => (string) $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'discount' => 0,
                    'tax' => 0,
                ]);

                $variant = $product->variants()->first();
                if ($variant) {
                    SaleItemVariant::create([
                        'id' => Str::uuid()->toString(),
                        'sale_item_id' => (string) $saleItem->id,
                        'product_variant_id' => (string) $variant->id,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ]);
                }
            }

            $creditRatio = (float) ($config['credit_ratio'] ?? 0);
            $paidAmount = round($subtotal * (1 - $creditRatio), 2);
            $dueAmount = round($subtotal - $paidAmount, 2);

            $sale->update([
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
            ]);

            Order::create([
                'id' => Str::uuid()->toString(),
                'merchant_id' => (string) $merchant->id,
                'sale_id' => (string) $sale->id,
                'status' => 'pending',
            ]);

            $this->command->info("  💰 Sale: {$saleNo} — PKR ".number_format($subtotal));
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // 11. EXPENSES
    // ═══════════════════════════════════════════════════════════════
    private function seedExpenses(Merchant $merchant, Business $business, array $branches, array $staff): void
    {
        $expenseDescriptions = [
            'Office Supplies',
            'Utilities - Electricity',
            'Internet & Phone',
            'Rent Payment',
            'Transportation - Fuel',
            'Marketing - Digital Ads',
            'Equipment Maintenance',
            'Software Subscription',
        ];

        $expenseConfigs = [
            ['days_ago' => 0, 'items' => 2],
            ['days_ago' => 2, 'items' => 3],
            ['days_ago' => 5, 'items' => 2],
            ['days_ago' => 12, 'items' => 3],
            ['days_ago' => 18, 'items' => 2],
            ['days_ago' => 25, 'items' => 2],
        ];

        $createdBy = $staff[0] ?? null;

        foreach ($expenseConfigs as $i => $config) {
            $expenseDate = now()->subDays($config['days_ago']);
            $expenseNo = 'EXP-'.$expenseDate->format('Ymd').'-DEMO'.($i + 1).$this->demoDocumentSuffix();

            if (Expense::query()->where('merchant_id', $merchant->id)->where('expense_no', $expenseNo)->exists()) {
                continue;
            }

            $branch = $branches[$i % count($branches)];

            $expense = Expense::create([
                'id' => Str::uuid()->toString(),
                'merchant_id' => (string) $merchant->id,
                'business_id' => (string) $business->id,
                'branch_id' => (string) $branch->id,
                'expense_no' => $expenseNo,
                'expense_date' => $expenseDate,
                'subtotal' => 0,
                'discount' => 0,
                'tax' => 0,
                'total_amount' => 0,
                'notes' => 'Demo expense #'.($i + 1),
                'created_by' => $createdBy ? (string) $createdBy->id : null,
            ]);

            $selectedDescriptions = collect($expenseDescriptions)->random($config['items']);
            $subtotal = 0;

            foreach ($selectedDescriptions as $description) {
                $quantity = rand(1, 3);
                $unitPrice = rand(5000, 250000) / 100;
                $lineTotal = round($quantity * $unitPrice, 2);
                $subtotal += $lineTotal;

                ExpenseItem::create([
                    'id' => Str::uuid()->toString(),
                    'expense_id' => (string) $expense->id,
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);
            }

            $tax = round($subtotal * 0.05, 2);
            $totalAmount = $subtotal + $tax;

            $expense->update([
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total_amount' => $totalAmount,
            ]);

            $this->command->info("  📋 Expense: {$expenseNo} — PKR ".number_format($totalAmount));
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // 12. PAYROLLS
    // ═══════════════════════════════════════════════════════════════
    private function seedPayrolls(Merchant $merchant, array $staff): void
    {
        $periods = [
            ['month' => now()->subMonth()->month, 'year' => now()->subMonth()->year],
            ['month' => now()->month, 'year' => now()->year],
        ];

        foreach ($staff as $staffIndex => $user) {
            $baseSalary = str_contains(strtolower($user->email), 'manager')
                ? 180000.00
                : 95000.00;

            foreach ($periods as $period) {
                if (Payroll::query()
                    ->where('merchant_id', $merchant->id)
                    ->where('user_id', $user->id)
                    ->where('period_month', $period['month'])
                    ->where('period_year', $period['year'])
                    ->exists()) {
                    continue;
                }

                $allowances = [
                    ['name' => 'Housing Allowance', 'amount' => round($baseSalary * 0.12, 2)],
                    ['name' => 'Transport Allowance', 'amount' => 8000.00],
                ];
                $deductions = [
                    ['name' => 'Income Tax', 'amount' => round($baseSalary * 0.08, 2)],
                ];

                $totalAllowances = collect($allowances)->sum(fn ($item) => (float) $item['amount']);
                $totalDeductions = collect($deductions)->sum(fn ($item) => (float) $item['amount']);
                $netSalary = $baseSalary + $totalAllowances - $totalDeductions;

                $isCurrentPeriod = $period['month'] === now()->month && $period['year'] === now()->year;
                $status = $isCurrentPeriod ? Payroll::STATUS_PENDING : Payroll::STATUS_PAID;

                Payroll::create([
                    'id' => Str::uuid()->toString(),
                    'merchant_id' => (string) $merchant->id,
                    'user_id' => (string) $user->id,
                    'payroll_no' => 'PAY-'.$period['year'].str_pad((string) $period['month'], 2, '0', STR_PAD_LEFT).'-'.($staffIndex + 1).$this->demoDocumentSuffix(),
                    'period_month' => $period['month'],
                    'period_year' => $period['year'],
                    'base_salary' => $baseSalary,
                    'allowances' => $allowances,
                    'deductions' => $deductions,
                    'net_salary' => $netSalary,
                    'status' => $status,
                    'payment_date' => $isCurrentPeriod ? null : now()->setMonth($period['month'])->setYear($period['year'])->setDay(5),
                    'notes' => 'Demo payroll for '.$period['month'].'/'.$period['year'],
                    'created_by' => null,
                ]);
            }
        }

        $this->command->info('  💼 Payrolls seeded for demo staff');
    }

    // ═══════════════════════════════════════════════════════════════
    // 13. CASH FLOWS
    // ═══════════════════════════════════════════════════════════════
    private function seedCashFlows(
        Merchant $merchant,
        Business $business,
        array $branches,
        array $customers,
        array $vendors,
        array $staff
    ): void {
        $createdBy = $staff[0] ?? null;
        $branch = $branches[0];

        $flowConfigs = [
            ['party' => $customers[0], 'party_type' => Customer::class, 'flow_type' => 'loan', 'amount' => 125000, 'days_ago' => 14],
            ['party' => $customers[1], 'party_type' => Customer::class, 'flow_type' => 'loan', 'amount' => 85000, 'days_ago' => 7],
            ['party' => $customers[2], 'party_type' => Customer::class, 'flow_type' => 'advance', 'amount' => 45000, 'days_ago' => 3],
            ['party' => $vendors[0], 'party_type' => Vendor::class, 'flow_type' => 'advance', 'amount' => 320000, 'days_ago' => 10],
            ['party' => $vendors[1], 'party_type' => Vendor::class, 'flow_type' => 'loan', 'amount' => 95000, 'days_ago' => 0],
        ];

        foreach ($flowConfigs as $i => $config) {
            $referenceNo = 'CF-DEMO-'.($i + 1).$this->demoDocumentSuffix();

            if (CashFlow::query()
                ->where('merchant_id', $merchant->id)
                ->where('reference_no', $referenceNo)
                ->exists()) {
                continue;
            }

            CashFlow::create([
                'id' => Str::uuid()->toString(),
                'merchant_id' => (string) $merchant->id,
                'business_id' => (string) $business->id,
                'branch_id' => (string) $branch->id,
                'party_type' => $config['party_type'],
                'party_id' => (string) $config['party']->id,
                'settlement_for_id' => null,
                'flow_type' => $config['flow_type'],
                'direction' => CashFlow::primaryDirectionForFlowType($config['flow_type']),
                'amount' => $config['amount'],
                'flow_date' => now()->subDays($config['days_ago']),
                'method' => 'Cash',
                'reference_no' => $referenceNo,
                'notes' => 'Demo cash flow entry',
                'created_by' => $createdBy ? (string) $createdBy->id : null,
            ]);
        }

        $this->command->info('  💵 Cash flows seeded');
    }

    // ═══════════════════════════════════════════════════════════════
    // 14. ASSETS
    // ═══════════════════════════════════════════════════════════════
    private function seedAssets(
        Merchant $merchant,
        Business $business,
        array $branches,
        array $vendors,
        array $staff
    ): void {
        $assetTypeConfigs = [
            ['name' => 'Office Equipment', 'code' => 'OFF-EQ'],
            ['name' => 'Vehicles', 'code' => 'VEH'],
            ['name' => 'Tools & Machinery', 'code' => 'TOOLS'],
        ];

        $assetTypes = [];

        foreach ($assetTypeConfigs as $typeConfig) {
            $assetTypes[] = AssetType::firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'code' => $typeConfig['code'],
                ],
                [
                    'id' => Str::uuid()->toString(),
                    'name' => $typeConfig['name'],
                    'description' => 'Demo asset type',
                    'is_active' => true,
                ]
            );
        }

        $assetConfigs = [
            ['name' => 'Demo Laptop', 'type' => 0, 'branch' => 0, 'cost' => 185000, 'days_ago' => 120],
            ['name' => 'Delivery Van', 'type' => 1, 'branch' => 0, 'cost' => 2800000, 'days_ago' => 365],
            ['name' => 'Power Drill Set', 'type' => 2, 'branch' => 1, 'cost' => 45000, 'days_ago' => 60],
            ['name' => 'Office Printer', 'type' => 0, 'branch' => 0, 'cost' => 65000, 'days_ago' => 90],
        ];

        $createdBy = $staff[0] ?? null;

        foreach ($assetConfigs as $i => $config) {
            $assetCode = 'AST-DEMO-'.($i + 1).$this->demoDocumentSuffix();

            if (Asset::query()->where('merchant_id', $merchant->id)->where('asset_code', $assetCode)->exists()) {
                continue;
            }

            $purchaseDate = now()->subDays($config['days_ago']);
            $purchaseCost = $config['cost'];

            Asset::create([
                'id' => Str::uuid()->toString(),
                'merchant_id' => (string) $merchant->id,
                'business_id' => (string) $business->id,
                'branch_id' => (string) $branches[$config['branch']]->id,
                'asset_type_id' => (string) $assetTypes[$config['type']]->id,
                'asset_code' => $assetCode,
                'name' => $config['name'],
                'description' => 'Demo company asset',
                'purchase_date' => $purchaseDate,
                'purchase_cost' => $purchaseCost,
                'current_value' => round($purchaseCost * 0.85, 2),
                'status' => AssetStatus::Active,
                'condition' => AssetCondition::Good,
                'location' => $branches[$config['branch']]->name,
                'assigned_to' => $createdBy ? (string) $createdBy->id : null,
                'vendor_id' => (string) $vendors[$i % count($vendors)]->id,
                'created_by' => $createdBy ? (string) $createdBy->id : null,
            ]);
        }

        $this->command->info('  🏗️  Assets seeded');
    }

    // ═══════════════════════════════════════════════════════════════
    // 15. SALE RETURNS
    // ═══════════════════════════════════════════════════════════════
    private function seedSaleReturns(Merchant $merchant, array $staff): void
    {
        $returnNo = 'SR-DEMO-01'.$this->demoDocumentSuffix();

        if (SaleReturn::query()->where('merchant_id', $merchant->id)->where('return_no', $returnNo)->exists()) {
            return;
        }

        $sale = Sale::query()
            ->where('merchant_id', $merchant->id)
            ->with(['items.product', 'items.variants'])
            ->oldest('sale_date')
            ->first();

        if (! $sale || $sale->items->isEmpty()) {
            return;
        }

        $saleItem = $sale->items->first();
        $returnQty = min(1, (int) $saleItem->quantity);
        $lineTotal = round($returnQty * (float) $saleItem->unit_price, 2);
        $createdBy = $staff[0] ?? null;

        $saleReturn = SaleReturn::create([
            'id' => Str::uuid()->toString(),
            'merchant_id' => (string) $merchant->id,
            'sale_id' => (string) $sale->id,
            'customer_id' => (string) $sale->customer_id,
            'return_no' => $returnNo,
            'return_date' => now()->subDays(2),
            'subtotal' => $lineTotal,
            'total_discount' => 0,
            'total_tax' => 0,
            'total_amount' => $lineTotal,
            'reason' => 'Demo return — defective unit',
            'created_by' => $createdBy ? (string) $createdBy->id : null,
        ]);

        $returnItem = SaleReturnItem::create([
            'id' => Str::uuid()->toString(),
            'sale_return_id' => (string) $saleReturn->id,
            'sale_item_id' => (string) $saleItem->id,
            'business_id' => (string) $saleItem->business_id,
            'branch_id' => (string) $saleItem->branch_id,
            'product_id' => (string) $saleItem->product_id,
            'quantity' => $returnQty,
            'unit_price' => $saleItem->unit_price,
            'line_total' => $lineTotal,
            'discount' => 0,
            'tax' => 0,
        ]);

        $variant = $saleItem->variants->first();
        if ($variant) {
            SaleReturnItemVariant::create([
                'id' => Str::uuid()->toString(),
                'sale_return_item_id' => (string) $returnItem->id,
                'product_variant_id' => (string) $variant->product_variant_id,
                'quantity' => $returnQty,
                'unit_price' => $variant->unit_price,
                'line_total' => $lineTotal,
            ]);
        }

        $this->command->info("  ↩️  Sale return: {$returnNo}");
    }

    // ═══════════════════════════════════════════════════════════════
    // 16. PURCHASE RETURNS
    // ═══════════════════════════════════════════════════════════════
    private function seedPurchaseReturns(Merchant $merchant, array $staff): void
    {
        $returnNo = 'PR-DEMO-01'.$this->demoDocumentSuffix();

        if (PurchaseReturn::query()->where('merchant_id', $merchant->id)->where('return_no', $returnNo)->exists()) {
            return;
        }

        $purchase = Purchase::query()
            ->where('merchant_id', $merchant->id)
            ->with(['items.product', 'items.variants'])
            ->oldest('purchase_date')
            ->first();

        if (! $purchase || $purchase->items->isEmpty()) {
            return;
        }

        $purchaseItem = $purchase->items->first();
        $returnQty = min(1, (int) $purchaseItem->quantity);
        $lineTotal = round($returnQty * (float) $purchaseItem->unit_price, 2);
        $createdBy = $staff[0] ?? null;

        $purchaseReturn = PurchaseReturn::create([
            'id' => Str::uuid()->toString(),
            'merchant_id' => (string) $merchant->id,
            'purchase_id' => (string) $purchase->id,
            'return_no' => $returnNo,
            'return_date' => now()->subDays(3),
            'subtotal' => $lineTotal,
            'total_discount' => 0,
            'total_tax' => 0,
            'total_amount' => $lineTotal,
            'reason' => 'Demo return — wrong specification',
            'created_by' => $createdBy ? (string) $createdBy->id : null,
        ]);

        $returnItem = PurchaseReturnItem::create([
            'id' => Str::uuid()->toString(),
            'purchase_return_id' => (string) $purchaseReturn->id,
            'purchase_item_id' => (string) $purchaseItem->id,
            'business_id' => (string) $purchaseItem->business_id,
            'branch_id' => (string) $purchaseItem->branch_id,
            'product_id' => (string) $purchaseItem->product_id,
            'quantity' => $returnQty,
            'unit_price' => $purchaseItem->unit_price,
            'line_total' => $lineTotal,
            'discount' => 0,
            'tax' => 0,
        ]);

        $variant = $purchaseItem->variants->first();
        if ($variant) {
            PurchaseReturnItemVariant::create([
                'id' => Str::uuid()->toString(),
                'purchase_return_item_id' => (string) $returnItem->id,
                'product_variant_id' => (string) $variant->product_variant_id,
                'quantity' => $returnQty,
                'unit_price' => $variant->unit_price,
                'line_total' => $lineTotal,
            ]);
        }

        $this->command->info("  ↩️  Purchase return: {$returnNo}");
    }

    // ═══════════════════════════════════════════════════════════════
    // 17. MERCHANT PROFILE (funds + settings)
    // ═══════════════════════════════════════════════════════════════
    private function seedMerchantProfile(Merchant $merchant): void
    {
        MerchantSetting::firstOrCreate(
            ['merchant_id' => $merchant->id],
            [
                'id' => Str::uuid()->toString(),
                'primary_color' => '#6d28d9',
                'secondary_color' => '#4f46e5',
                'warning_color' => '#f59e0b',
                'danger_color' => '#ef4444',
                'success_color' => '#10b981',
                'default_color' => '#6b7280',
            ]
        );

        $merchant->update([
            'cash_in_hand' => 850000,
            'cash_in_bank' => 2450000,
        ]);

        $this->command->info('  🏦 Merchant funds and settings seeded');
    }
}

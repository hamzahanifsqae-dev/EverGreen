<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Services\Demo\TemporaryDemoCleanup;
use App\Support\DemoAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TemporaryDemoCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_merchant_with_sale_returns_does_not_null_branch_id(): void
    {
        $sessionId = Str::uuid()->toString();

        $merchant = Merchant::query()->create([
            'id' => Str::uuid()->toString(),
            'email' => DemoAccount::temporaryEmailForSession($sessionId),
            'name' => 'Demo Cleanup Test',
            'address_line_1' => 'Demo City',
            'city' => 'Karachi',
            'status' => Merchant::STATUS_VERIFIED,
            'is_active' => true,
            'password' => 'password',
        ]);

        app(TemporaryDemoCleanup::class)->deleteMerchant($merchant);

        $this->assertDatabaseMissing('merchants', ['id' => $merchant->id]);
    }
}

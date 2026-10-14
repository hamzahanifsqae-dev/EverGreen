<?php

namespace Tests\Feature;

use App\Models\DemoVisitorSession;
use App\Models\Merchant;
use App\Services\Demo\TemporaryDemoProvisioner;
use App\Support\DemoAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoVisitorSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockProvisioner();
    }

    public function test_demo_login_starts_a_new_visitor_timer_and_provisions_merchant(): void
    {
        $response = $this->get(route('demo.login'));

        $response->assertRedirect('/merchant');

        $this->assertDatabaseCount('demo_visitor_sessions', 1);

        $visitorSession = DemoVisitorSession::query()->first();

        $this->assertNotNull($visitorSession);
        $this->assertNotNull($visitorSession->merchant_id);
        $this->assertFalse($visitorSession->isExpired());
        $this->assertGreaterThan(0, $visitorSession->remainingSeconds());

        $this->assertTrue(DemoAccount::isTemporaryDemoEmail($visitorSession->merchant?->email));
        $this->assertAuthenticatedAs($visitorSession->merchant, 'merchant');
    }

    public function test_demo_login_resumes_existing_visitor_timer(): void
    {
        // DemoVisitorSession::$fillable does not include 'id', so an explicit
        // 'id' in create() is silently dropped by mass-assignment and HasUuids
        // assigns its own ordered UUID instead. Read back the real id and key
        // the merchant's session-derived email off that, not the intended one,
        // so the "resume" path computes the same email the first call did.
        $createdSession = DemoVisitorSession::query()->create([
            'visitor_hash' => DemoAccount::visitorFingerprint(),
            'ip_address' => '127.0.0.1',
            'started_at' => now()->subMinutes(20),
            'expires_at' => now()->addMinutes(10),
            'last_seen_at' => now()->subMinutes(5),
        ]);

        $merchant = $this->createTemporaryMerchant($createdSession->id);
        $createdSession->update(['merchant_id' => $merchant->id]);

        $this->get(route('demo.exit'));
        $this->get(route('demo.login'))->assertRedirect('/merchant');

        $this->assertDatabaseCount('demo_visitor_sessions', 1);
        $this->assertDatabaseCount('merchants', 1);

        $visitorSession = DemoVisitorSession::query()->first();

        $this->assertNotNull($visitorSession);
        $this->assertSame($merchant->id, $visitorSession->merchant_id);
        $this->assertLessThanOrEqual(600, $visitorSession->remainingSeconds());
        $this->assertGreaterThan(0, $visitorSession->remainingSeconds());
    }

    public function test_expired_visitor_cannot_start_demo_again(): void
    {
        $createdSession = DemoVisitorSession::query()->create([
            'visitor_hash' => DemoAccount::visitorFingerprint(),
            'ip_address' => '127.0.0.1',
            'started_at' => now()->subMinutes(40),
            'expires_at' => now()->subMinutes(10),
            'last_seen_at' => now()->subMinutes(10),
        ]);

        $merchant = $this->createTemporaryMerchant($createdSession->id);
        $createdSession->update(['merchant_id' => $merchant->id]);

        $response = $this->get(route('demo.login'));

        $response->assertRedirect(route('filament.merchant.auth.login'));
        $response->assertSessionHas('demo_expired');
        $this->assertGuest('merchant');
        $this->assertDatabaseMissing('merchants', ['id' => $merchant->id]);

        $visitorSession = DemoVisitorSession::query()->first();
        $this->assertNotNull($visitorSession);
        $this->assertNull($visitorSession->merchant_id);
    }

    private function mockProvisioner(): void
    {
        $this->mock(TemporaryDemoProvisioner::class, function ($mock): void {
            $mock->shouldReceive('provisionForSession')
                ->andReturnUsing(fn (string $sessionId): Merchant => $this->createTemporaryMerchant($sessionId));
        });
    }

    private function createTemporaryMerchant(string $sessionId): Merchant
    {
        // Mirrors the real TemporaryDemoProvisioner: reuse the merchant already
        // provisioned for this session's email instead of creating a duplicate,
        // so a "resume" call behaves the same as the real implementation.
        return Merchant::query()->firstOrCreate(
            ['email' => DemoAccount::temporaryEmailForSession($sessionId)],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'EverGreen Cold Storage Demo',
                'address_line_1' => 'Demo City',
                'city' => 'Karachi',
                'status' => Merchant::STATUS_VERIFIED,
                'is_active' => true,
                'password' => 'Demo@123456',
            ],
        );
    }
}

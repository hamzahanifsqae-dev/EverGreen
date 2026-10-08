<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\Merchant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

class PanelLoginPersistenceTest extends TestCase
{
    public function test_merchant_login_persists_authentication(): void
    {
        $merchant = Merchant::query()
            ->where('email', 'info@evergreen.com')
            ->where('is_active', true)
            ->first();

        if (! $merchant) {
            $this->markTestSkipped('Seeded merchant account not available.');
        }

        Filament::setCurrentPanel(Filament::getPanel('merchant'));

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'info@evergreen.com',
                'password' => 'Evergreen@123',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($merchant, 'merchant');

        $this->get(route('filament.merchant.pages.dashboard'))
            ->assertSuccessful();
    }

    public function test_staff_login_persists_authentication(): void
    {
        $staff = User::query()
            ->where('email', 'admin@flowdesk.com')
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            $this->markTestSkipped('Seeded staff account not available.');
        }

        Filament::setCurrentPanel(Filament::getPanel('user'));

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@flowdesk.com',
                'password' => 'DD@2025@DD',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($staff, 'staff');

        $this->get(route('filament.user.pages.dashboard'))
            ->assertSuccessful();
    }
}

<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_home_page_redirects_to_the_merchant_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('filament.merchant.auth.login'));
    }

    public function test_merchant_and_staff_login_pages_are_available(): void
    {
        $this->get(route('filament.merchant.auth.login'))->assertOk();
        $this->get(route('filament.user.auth.login'))->assertOk();
    }
}

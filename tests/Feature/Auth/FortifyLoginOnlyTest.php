<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FortifyLoginOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_routes_are_available(): void
    {
        $this->get(route('login'))->assertOk();
        $this->post(route('login.store'), [
            'name' => 'unknown',
            'password' => 'invalid',
        ])->assertSessionHasErrors();
    }

    public function test_non_login_fortify_pages_are_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/reset-password/token')->assertNotFound();
        $this->get('/email/verify')->assertNotFound();
        $this->get('/two-factor-challenge')->assertNotFound();
        $this->get('/user/confirm-password')->assertNotFound();
    }
}

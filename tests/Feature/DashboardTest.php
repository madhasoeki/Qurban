<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_visit_the_dashboard_without_sidebar_navigation(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('users.index'));
    }

    public function test_authenticated_users_can_visit_the_dashboard_with_sidebar_navigation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('dashboard'));
    }
}

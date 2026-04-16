<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('users.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_non_admin_users_cannot_visit_the_users_page(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin']);

        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('users.index'));

        $response->assertForbidden();
    }

    public function test_admin_users_can_visit_the_users_page(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);

        $user = User::factory()->create();
        $user->assignRole($adminRole);

        $this->actingAs($user);

        $response = $this->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('Users');
    }
}

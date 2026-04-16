<?php

namespace Tests\Feature;

use App\Models\Sohibul;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SohibulPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_page(): void
    {
        $response = $this->get(route('sohibul.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_users_can_visit_sohibul_page(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);

        $user = User::factory()->create();
        $user->assignRole($adminRole);

        $this->actingAs($user);

        $response = $this->get(route('sohibul.index'));

        $response->assertOk();
        $response->assertSee('Sohibul');
    }

    public function test_can_create_sohibul_with_up_to_7_names(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);

        $user = User::factory()->create();
        $user->assignRole($adminRole);

        $this->actingAs($user);

        Livewire::test('pages::sohibul.index')
            ->set('names', ['Nama 1', 'Nama 2', 'Nama 3', 'Nama 4', 'Nama 5', 'Nama 6', 'Nama 7'])
            ->set('jenisQurban', 'sapi')
            ->set('requestNote', 'Mohon diprioritaskan')
            ->call('save');

        $this->assertDatabaseCount('sohibul', 1);

        $sohibul = Sohibul::query()->firstOrFail();

        $this->assertCount(7, $sohibul->nama);
        $this->assertSame('sapi', $sohibul->jenis_qurban);
        $this->assertSame('Mohon diprioritaskan', $sohibul->request);
    }

    public function test_can_not_create_sohibul_with_more_than_7_names(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);

        $user = User::factory()->create();
        $user->assignRole($adminRole);

        $this->actingAs($user);

        Livewire::test('pages::sohibul.index')
            ->set('names', ['Nama 1', 'Nama 2', 'Nama 3', 'Nama 4', 'Nama 5', 'Nama 6', 'Nama 7', 'Nama 8'])
            ->set('jenisQurban', 'kambing')
            ->set('requestNote', 'Lebih dari batas')
            ->call('save')
            ->assertHasErrors(['names' => ['max']]);

        $this->assertDatabaseCount('sohibul', 0);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Distribusi;
use App\Models\Hewan;
use App\Models\Sohibul;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_jagal_page_shows_sohibul_options_when_data_exists(): void
    {
        $jagalRole = Role::query()->firstOrCreate(['name' => 'jagal']);

        $user = User::factory()->create();
        $user->assignRole($jagalRole);

        Sohibul::query()->create([
            'nama' => ['Ahmad', 'Budi'],
            'jenis_qurban' => 'sapi',
            'request' => null,
        ]);

        $this->actingAs($user);

        $this->get(route('workflow.jagal'))
            ->assertOk()
            ->assertSee('Sapi - Ahmad, Budi')
            ->assertSee('Filter Jenis Qurban')
            ->assertSee('Daftar Sohibul Qurban');
    }

    public function test_jagal_role_can_access_jagal_page_but_not_kuliti_page(): void
    {
        $jagalRole = Role::query()->firstOrCreate(['name' => 'jagal']);
        Role::query()->firstOrCreate(['name' => 'kuliti']);

        $user = User::factory()->create();
        $user->assignRole($jagalRole);

        $this->actingAs($user);

        $this->get(route('workflow.jagal'))->assertOk();
        $this->get(route('workflow.kuliti'))->assertForbidden();
    }

    public function test_distribusi_updates_global_bags_without_changing_workflow_data(): void
    {
        $distribusiRole = Role::query()->firstOrCreate(['name' => 'distribusi']);

        $user = User::factory()->create();
        $user->assignRole($distribusiRole);

        $sohibul = Sohibul::query()->create([
            'nama' => ['Pak Ahmad'],
            'jenis_qurban' => 'sapi',
            'request' => null,
        ]);

        $hewan = Hewan::query()->create([
            'kode' => 'Sapi-01',
            'sohibul_id' => $sohibul->id,
            'mulai_jagal' => now(),
            'selesai_jagal' => now(),
            'mulai_kuliti' => now(),
            'selesai_kuliti' => now(),
            'mulai_cacah_daging' => now(),
            'selesai_cacah_daging' => now(),
            'mulai_cacah_tulang' => now(),
            'selesai_cacah_tulang' => now(),
            'mulai_jeroan' => now(),
            'selesai_jeroan' => now(),
            'mulai_packing' => now(),
            'selesai_packing' => now(),
            'kantong_packing' => 25,
        ]);

        $this->actingAs($user);

        Livewire::test('pages::hewan.distribusi')
            ->set('jumlahByUser.'.$user->id, 20)
            ->call('updateJumlah', $user->id)
            ->assertHasNoErrors();

        $distribusi = Distribusi::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($distribusi);
        $this->assertSame(20, $distribusi->jumlah);

        $hewan->refresh();
        $this->assertNotNull($hewan->selesai_packing);
    }
}

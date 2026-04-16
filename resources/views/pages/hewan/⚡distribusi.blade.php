<?php

use App\Models\Hewan;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.dashboard')] #[Title('Distribusi')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $jenisQurbanFilter = 'all';

    /** @var array<int, int|string> */
    public array $distribusiBags = [];

    public function mount(): void
    {
        //
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedJenisQurbanFilter(): void
    {
        $this->resetPage();
    }

    public function updateDistribusi(int $hewanId): void
    {
        $hewan = Hewan::query()->findOrFail($hewanId);
        $bags = (int) ($this->distribusiBags[$hewan->id] ?? 0);

        if ($bags < 0) {
            $this->addError('distribusiBags.'.$hewan->id, __('Jumlah kantong distribusi tidak boleh negatif.'));

            return;
        }

        $hewan->update([
            'kantong_distribusi' => $bags,
            'distribusi' => $bags > 0 ? 1 : 0,
        ]);

        Flux::toast(variant: 'success', text: __('Distribusi berhasil diperbarui.'));
    }

    public function with(): array
    {
        $hewanItems = Hewan::query()
            ->with('sohibul:id,nama,jenis_qurban')
            ->whereNotNull('selesai_jagal')
            ->when($this->jenisQurbanFilter !== 'all', fn ($query) => $query->whereHas('sohibul', fn ($relation) => $relation->where('jenis_qurban', $this->jenisQurbanFilter)))
            ->when($this->search, fn ($query) => $query->where('kode', 'like', '%'.$this->search.'%'))
            ->latest('id')
            ->paginate(12);

        foreach ($hewanItems as $hewan) {
            $this->distribusiBags[$hewan->id] = $this->distribusiBags[$hewan->id] ?? $hewan->kantong_distribusi;
        }

        return [
            'pageTitle' => __('Distribusi'),
            'hewanItems' => $hewanItems,
            'nowLabel' => now()->format('H:i:s'),
        ];
    }

    public function statusLabel(Hewan $hewan): string
    {
        if ((int) $hewan->distribusi === 1) {
            return 'Selesai - '.$hewan->kantong_distribusi.' Kantong';
        }

        return 'Belum';
    }

    /**
     * @return array<int, string>
     */
    public function hewanSohibulNames(Hewan $hewan): array
    {
        if (is_array($hewan->sohibul?->nama)) {
            return array_values(array_filter($hewan->sohibul->nama, fn ($name) => filled($name)));
        }

        if (filled($hewan->sohibul?->nama)) {
            return [(string) $hewan->sohibul->nama];
        }

        return [];
    }
}; ?>

<section class="w-full space-y-6 p-6" wire:poll.10s>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $pageTitle }}</flux:heading>
            <flux:subheading>{{ __('Pantau dan update proses distribusi secara realtime.') }}</flux:subheading>
        </div>
        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Last updated at') }}: {{ $nowLabel }}</flux:text>
    </div>

    <div class="flex flex-col gap-3 md:flex-row md:items-end">
        <flux:input wire:model.live="search" icon="magnifying-glass" :placeholder="__('Cari kode hewan...')" class="md:max-w-sm" />

        <flux:select wire:model.live="jenisQurbanFilter" class="md:max-w-xs" :label="__('Filter Jenis Qurban')">
            <option value="all">Semua</option>
            <option value="sapi">Sapi</option>
            <option value="kambing">Kambing</option>
        </flux:select>
    </div>

    <div class="space-y-3 md:hidden">
        @forelse ($hewanItems as $hewan)
            <article class="space-y-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:subheading class="text-zinc-500">{{ __('Kode') }}</flux:subheading>
                        <flux:heading size="lg">{{ $hewan->kode }}</flux:heading>
                    </div>
                    <flux:badge size="sm">{{ $this->statusLabel($hewan) }}</flux:badge>
                </div>

                <div>
                    <flux:subheading class="mb-1 text-zinc-500">{{ __('Sohibul') }}</flux:subheading>
                    @php($names = $this->hewanSohibulNames($hewan))

                    @if (count($names) > 1)
                        <ol class="list-decimal pl-4 text-sm">
                            @foreach ($names as $name)
                                <li>{{ $name }}</li>
                            @endforeach
                        </ol>
                    @elseif (count($names) === 1)
                        <flux:text>{{ $names[0] }}</flux:text>
                    @else
                        <flux:text>-</flux:text>
                    @endif
                </div>

                <div class="space-y-2">
                    <flux:input type="number" wire:model="distribusiBags.{{ $hewan->id }}" min="0" :label="__('Jumlah Kantong')" />
                    <flux:button size="sm" variant="primary" class="w-full" wire:click="updateDistribusi({{ $hewan->id }})">{{ __('Simpan') }}</flux:button>
                </div>
            </article>
        @empty
            <div class="rounded-lg border border-zinc-200 px-4 py-6 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('Belum ada data untuk tahap ini.') }}
            </div>
        @endforelse

        {{ $hewanItems->links() }}
    </div>

    <div class="hidden md:block">
        <flux:table :paginate="$hewanItems">
            <flux:table.columns>
                <flux:table.column>{{ __('Kode') }}</flux:table.column>
                <flux:table.column>{{ __('Sohibul') }}</flux:table.column>
                <flux:table.column>{{ __('Status Distribusi') }}</flux:table.column>
                <flux:table.column>{{ __('Jumlah Kantong') }}</flux:table.column>
                <flux:table.column>{{ __('Aksi') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($hewanItems as $hewan)
                    <flux:table.row :key="'distribusi-hewan-'.$hewan->id">
                        <flux:table.cell>{{ $hewan->kode }}</flux:table.cell>
                        <flux:table.cell>
                            @php($names = $this->hewanSohibulNames($hewan))

                            @if (count($names) > 1)
                                <ol class="list-decimal pl-4">
                                    @foreach ($names as $name)
                                        <li>{{ $name }}</li>
                                    @endforeach
                                </ol>
                            @elseif (count($names) === 1)
                                {{ $names[0] }}
                            @else
                                -
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->statusLabel($hewan) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:input type="number" wire:model="distribusiBags.{{ $hewan->id }}" min="0" class="w-28" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" variant="primary" wire:click="updateDistribusi({{ $hewan->id }})">{{ __('Simpan') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-500 dark:text-zinc-400">{{ __('Belum ada data untuk tahap ini.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>

<?php

use App\Models\Hewan;
use Carbon\CarbonInterface;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.dashboard')] #[Title('Packing')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $jenisQurbanFilter = 'all';

    public bool $showConfirmModal = false;

    public ?int $confirmHewanId = null;

    public string $confirmAction = '';

    public string $confirmTitle = 'Konfirmasi Aksi';

    public string $confirmMessage = 'Apakah Anda yakin ingin melanjutkan?';

    /** @var array<int, int|string> */
    public array $packingBags = [];

    /** @var array<int, bool> */
    public array $isEditingPackingBags = [];

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

    public function openConfirmModal(int $hewanId, string $action, string $title, string $message): void
    {
        $this->confirmHewanId = $hewanId;
        $this->confirmAction = $action;
        $this->confirmTitle = $title;
        $this->confirmMessage = $message;
        $this->showConfirmModal = true;
    }

    public function closeConfirmModal(): void
    {
        $this->showConfirmModal = false;
        $this->confirmHewanId = null;
        $this->confirmAction = '';
        $this->confirmTitle = 'Konfirmasi Aksi';
        $this->confirmMessage = 'Apakah Anda yakin ingin melanjutkan?';
    }

    public function runConfirmedAction(): void
    {
        if ($this->confirmHewanId === null || $this->confirmAction === '') {
            $this->closeConfirmModal();

            return;
        }

        $action = $this->confirmAction;

        if (method_exists($this, $action)) {
            $this->{$action}($this->confirmHewanId);
        }

        $this->closeConfirmModal();
    }

    public function startPacking(int $hewanId): void
    {
        $hewan = Hewan::query()->findOrFail($hewanId);

        if ($hewan->selesai_jagal === null || $hewan->mulai_packing !== null) {
            return;
        }

        $hewan->update(['mulai_packing' => now()]);
        Flux::toast(variant: 'success', text: __('Tahap packing berhasil dimulai.'));
    }

    public function finishPacking(int $hewanId): void
    {
        $hewan = Hewan::query()->findOrFail($hewanId);

        if ($hewan->mulai_packing === null || $hewan->selesai_packing !== null) {
            return;
        }

        $bags = (int) ($this->packingBags[$hewan->id] ?? 0);
        if ($bags <= 0) {
            $this->addError('packingBags.'.$hewan->id, __('Jumlah kantong packing wajib diisi dan harus lebih dari 0.'));
            Flux::toast(variant: 'danger', text: 'Jumlah kantong belum valid. Isi lebih dari 0 sebelum menyelesaikan packing.');

            return;
        }

        $hewan->update([
            'kantong_packing' => $bags,
            'selesai_packing' => now(),
        ]);

        Flux::toast(variant: 'success', text: __('Tahap packing berhasil diselesaikan.'));
    }

    public function updatePackingBags(int $hewanId): void
    {
        $hewan = Hewan::query()->findOrFail($hewanId);

        if ($hewan->selesai_packing === null) {
            return;
        }

        $bags = (int) ($this->packingBags[$hewan->id] ?? 0);

        if ($bags <= 0) {
            $this->addError('packingBags.'.$hewan->id, __('Jumlah kantong packing wajib diisi dan harus lebih dari 0.'));
            Flux::toast(variant: 'danger', text: 'Jumlah kantong belum valid. Isi lebih dari 0 sebelum menyimpan perubahan.');

            return;
        }

        $hewan->update([
            'kantong_packing' => $bags,
        ]);

        $this->isEditingPackingBags[$hewan->id] = false;

        Flux::toast(variant: 'success', text: 'Jumlah kantong packing berhasil diperbarui.');
    }

    public function startEditPackingBags(int $hewanId): void
    {
        $hewan = Hewan::query()->findOrFail($hewanId);

        if ($hewan->selesai_packing === null) {
            return;
        }

        $this->packingBags[$hewan->id] = $hewan->kantong_packing;
        $this->isEditingPackingBags[$hewan->id] = true;
    }

    public function cancelEditPackingBags(int $hewanId): void
    {
        $hewan = Hewan::query()->findOrFail($hewanId);

        $this->packingBags[$hewan->id] = $hewan->kantong_packing;
        $this->isEditingPackingBags[$hewan->id] = false;
        $this->resetErrorBag('packingBags.'.$hewan->id);
    }

    public function with(): array
    {
        $hewanItems = Hewan::query()
            ->with('sohibul:id,nama,jenis_qurban,request')
            ->whereNotNull('selesai_jagal')
            ->when($this->jenisQurbanFilter !== 'all', fn ($query) => $query->whereHas('sohibul', fn ($relation) => $relation->where('jenis_qurban', $this->jenisQurbanFilter)))
            ->when($this->search, fn ($query) => $query->where('kode', 'like', '%'.$this->search.'%'))
            ->orderByRaw("CASE WHEN mulai_packing IS NOT NULL AND selesai_packing IS NULL THEN 0 WHEN mulai_packing IS NULL THEN 1 ELSE 2 END")
            ->orderByRaw("CASE WHEN mulai_packing IS NOT NULL AND selesai_packing IS NULL THEN mulai_packing END ASC")
            ->orderByRaw("CASE WHEN mulai_packing IS NULL THEN id END DESC")
            ->orderByRaw("CASE WHEN selesai_packing IS NOT NULL THEN selesai_packing END DESC")
            ->paginate(12);

        foreach ($hewanItems as $hewan) {
            $this->packingBags[$hewan->id] = $this->packingBags[$hewan->id] ?? $hewan->kantong_packing;
        }

        return [
            'pageTitle' => __('Packing'),
            'hewanItems' => $hewanItems,
            'nowLabel' => now()->format('H:i:s'),
        ];
    }

    public function statusLabel(Hewan $hewan): string
    {
        if ($hewan->selesai_packing !== null) {
            return 'Selesai';
        }

        if ($hewan->mulai_packing !== null) {
            return 'Sedang Proses';
        }

        return 'Belum Mulai';
    }

    public function statusColor(Hewan $hewan): string
    {
        if ($hewan->selesai_packing !== null) {
            return 'green';
        }

        if ($hewan->mulai_packing !== null) {
            return 'yellow';
        }

        return 'red';
    }

    public function durationLabel(?CarbonInterface $start, ?CarbonInterface $finish): string
    {
        if ($start === null) {
            return '-';
        }

        $endTime = $finish ?? now();
        $seconds = max(0, $start->diffInSeconds($endTime));

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
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

<section class="w-full space-y-6" wire:poll.10s x-data="dashboardDurations">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $pageTitle }}</flux:heading>
            <flux:subheading>{{ __('Pantau dan update proses packing secara realtime.') }}</flux:subheading>
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
                    <flux:badge size="sm" :color="$this->statusColor($hewan)">{{ $this->statusLabel($hewan) }}</flux:badge>
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

                <div class="flex items-center justify-between gap-2">
                    <flux:text class="text-xs text-zinc-500">{{ __('Waktu Proses') }}</flux:text>
                    <flux:text class="font-medium" x-text="stageDurationValue({{ $hewan->mulai_packing?->timestamp ?? 'null' }}, {{ $hewan->selesai_packing?->timestamp ?? 'null' }})">{{ $this->durationLabel($hewan->mulai_packing, $hewan->selesai_packing) }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <flux:text class="text-xs text-zinc-500">Request</flux:text>
                    <flux:text class="text-right font-medium">{{ $hewan->sohibul?->request ?: '-' }}</flux:text>
                </div>

                <div class="space-y-2">
                    @if ($hewan->mulai_packing === null)
                        <flux:button size="sm" class="w-full" wire:click="openConfirmModal({{ $hewan->id }}, 'startPacking', 'Mulai Packing', 'Yakin ingin memulai proses packing?')">{{ __('Mulai') }}</flux:button>
                    @elseif ($hewan->selesai_packing === null)
                        <flux:input type="number" wire:model="packingBags.{{ $hewan->id }}" min="1" :placeholder="__('Kantong')" />
                        <flux:button size="sm" variant="primary" class="w-full" wire:click="openConfirmModal({{ $hewan->id }}, 'finishPacking', 'Selesaikan Packing', 'Yakin ingin menyelesaikan proses packing?')">{{ __('Selesai') }}</flux:button>
                    @else
                        @if (($isEditingPackingBags[$hewan->id] ?? false) === true)
                            <flux:input type="number" wire:model="packingBags.{{ $hewan->id }}" min="1" :placeholder="__('Kantong')" />
                            <div class="flex items-center gap-2">
                                <flux:button size="sm" variant="filled" class="w-full" wire:click="updatePackingBags({{ $hewan->id }})">Simpan</flux:button>
                                <flux:button size="sm" variant="ghost" class="w-full" wire:click="cancelEditPackingBags({{ $hewan->id }})">Batal</flux:button>
                            </div>
                        @else
                            <flux:text class="text-sm font-medium">{{ number_format((int) ($packingBags[$hewan->id] ?? $hewan->kantong_packing)) }} Kantong</flux:text>
                            <flux:button size="sm" variant="ghost" class="w-full" wire:click="startEditPackingBags({{ $hewan->id }})">Edit Jumlah Kantong</flux:button>
                        @endif
                    @endif
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
                <flux:table.column>Request</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Waktu Proses') }}</flux:table.column>
                <flux:table.column>{{ __('Aksi') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($hewanItems as $hewan)
                    <flux:table.row :key="'packing-hewan-'.$hewan->id">
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
                        <flux:table.cell>{{ $hewan->sohibul?->request ?: '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$this->statusColor($hewan)">{{ $this->statusLabel($hewan) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell x-text="stageDurationValue({{ $hewan->mulai_packing?->timestamp ?? 'null' }}, {{ $hewan->selesai_packing?->timestamp ?? 'null' }})">{{ $this->durationLabel($hewan->mulai_packing, $hewan->selesai_packing) }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                @if ($hewan->mulai_packing === null)
                                    <flux:button size="sm" wire:click="openConfirmModal({{ $hewan->id }}, 'startPacking', 'Mulai Packing', 'Yakin ingin memulai proses packing?')">{{ __('Mulai') }}</flux:button>
                                @elseif ($hewan->selesai_packing === null)
                                    <flux:input type="number" wire:model="packingBags.{{ $hewan->id }}" min="1" class="w-24" :placeholder="__('Kantong')" />
                                    <flux:button size="sm" variant="primary" wire:click="openConfirmModal({{ $hewan->id }}, 'finishPacking', 'Selesaikan Packing', 'Yakin ingin menyelesaikan proses packing?')">{{ __('Selesai') }}</flux:button>
                                @else
                                    @if (($isEditingPackingBags[$hewan->id] ?? false) === true)
                                        <flux:input type="number" wire:model="packingBags.{{ $hewan->id }}" min="1" class="w-24" :placeholder="__('Kantong')" />
                                        <flux:button size="sm" variant="filled" wire:click="updatePackingBags({{ $hewan->id }})">Simpan</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="cancelEditPackingBags({{ $hewan->id }})">Batal</flux:button>
                                    @else
                                        <flux:text class="text-sm font-medium">{{ number_format((int) ($packingBags[$hewan->id] ?? $hewan->kantong_packing)) }} Kantong</flux:text>
                                        <flux:button size="sm" variant="ghost" wire:click="startEditPackingBags({{ $hewan->id }})">Edit Jumlah Kantong</flux:button>
                                    @endif
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500 dark:text-zinc-400">{{ __('Belum ada data untuk tahap ini.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showConfirmModal" class="md:w-[28rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $confirmTitle }}</flux:heading>
            <flux:text>{{ $confirmMessage }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeConfirmModal">Batal</flux:button>
                <flux:button variant="primary" wire:click="runConfirmedAction">Ya, Lanjutkan</flux:button>
            </div>
        </div>
    </flux:modal>
</section>

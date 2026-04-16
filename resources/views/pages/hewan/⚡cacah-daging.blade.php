<?php

use App\Models\Hewan;
use Carbon\CarbonInterface;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.dashboard')] #[Title('Cacah Daging')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $jenisQurbanFilter = 'all';

    public bool $showConfirmModal = false;

    public ?int $confirmHewanId = null;

    public string $confirmAction = '';

    public string $confirmTitle = 'Konfirmasi Aksi';

    public string $confirmMessage = 'Apakah Anda yakin ingin melanjutkan?';

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

    public function startCacahDaging(int $hewanId): void
    {
        $hewan = Hewan::query()->findOrFail($hewanId);

        if ($hewan->selesai_jagal === null || $hewan->mulai_cacah_daging !== null) {
            return;
        }

        $hewan->update(['mulai_cacah_daging' => now()]);
        Flux::toast(variant: 'success', text: __('Tahap cacah daging berhasil dimulai.'));
    }

    public function finishCacahDaging(int $hewanId): void
    {
        $hewan = Hewan::query()->findOrFail($hewanId);

        if ($hewan->mulai_cacah_daging === null || $hewan->selesai_cacah_daging !== null) {
            return;
        }

        $hewan->update(['selesai_cacah_daging' => now()]);
        Flux::toast(variant: 'success', text: __('Tahap cacah daging berhasil diselesaikan.'));
    }

    public function with(): array
    {
        return [
            'pageTitle' => __('Cacah Daging'),
            'hewanItems' => Hewan::query()
                ->with('sohibul:id,nama,jenis_qurban')
                ->whereNotNull('selesai_jagal')
                ->when($this->jenisQurbanFilter !== 'all', fn ($query) => $query->whereHas('sohibul', fn ($relation) => $relation->where('jenis_qurban', $this->jenisQurbanFilter)))
                ->when($this->search, fn ($query) => $query->where('kode', 'like', '%'.$this->search.'%'))
                ->latest('id')
                ->paginate(12),
            'nowLabel' => now()->format('H:i:s'),
        ];
    }

    public function statusLabel(Hewan $hewan): string
    {
        if ($hewan->selesai_cacah_daging !== null) {
            return 'Selesai';
        }

        if ($hewan->mulai_cacah_daging !== null) {
            return $hewan->mulai_cacah_daging->format('H:i:s');
        }

        return 'Belum Mulai';
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

<section class="w-full space-y-6 p-6" wire:poll.10s>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $pageTitle }}</flux:heading>
            <flux:subheading>{{ __('Pantau dan update proses cacah daging secara realtime.') }}</flux:subheading>
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

                <div class="flex items-center justify-between gap-2">
                    <flux:text class="text-xs text-zinc-500">{{ __('Waktu Proses') }}</flux:text>
                    <flux:text class="font-medium">{{ $this->durationLabel($hewan->mulai_cacah_daging, $hewan->selesai_cacah_daging) }}</flux:text>
                </div>

                <div>
                    @if ($hewan->mulai_cacah_daging === null)
                        <flux:button size="sm" class="w-full" wire:click="openConfirmModal({{ $hewan->id }}, 'startCacahDaging', 'Mulai Cacah Daging', 'Yakin ingin memulai proses cacah daging?')">{{ __('Mulai') }}</flux:button>
                    @elseif ($hewan->selesai_cacah_daging === null)
                        <flux:button size="sm" variant="primary" class="w-full" wire:click="openConfirmModal({{ $hewan->id }}, 'finishCacahDaging', 'Selesaikan Cacah Daging', 'Yakin ingin menyelesaikan proses cacah daging?')">{{ __('Selesai') }}</flux:button>
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
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Waktu Proses') }}</flux:table.column>
                <flux:table.column>{{ __('Aksi') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($hewanItems as $hewan)
                    <flux:table.row :key="'cacah-daging-hewan-'.$hewan->id">
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
                        <flux:table.cell>{{ $this->durationLabel($hewan->mulai_cacah_daging, $hewan->selesai_cacah_daging) }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($hewan->mulai_cacah_daging === null)
                                <flux:button size="sm" wire:click="openConfirmModal({{ $hewan->id }}, 'startCacahDaging', 'Mulai Cacah Daging', 'Yakin ingin memulai proses cacah daging?')">{{ __('Mulai') }}</flux:button>
                            @elseif ($hewan->selesai_cacah_daging === null)
                                <flux:button size="sm" variant="primary" wire:click="openConfirmModal({{ $hewan->id }}, 'finishCacahDaging', 'Selesaikan Cacah Daging', 'Yakin ingin menyelesaikan proses cacah daging?')">{{ __('Selesai') }}</flux:button>
                            @endif
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

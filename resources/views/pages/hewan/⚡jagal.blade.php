<?php

use App\Models\Hewan;
use App\Models\Sohibul;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.dashboard')] #[Title('Jagal')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $jenisQurbanFilter = 'all';

    public ?int $startSohibulId = null;

    public string $jagalCodeNumber = '';

    public bool $showStartModal = false;

    public bool $showConfirmFinishModal = false;

    public ?int $confirmFinishHewanId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedJenisQurbanFilter(): void
    {
        $this->resetPage();
    }

    public function openStartModal(int $sohibulId): void
    {
        $this->startSohibulId = $sohibulId;
        $this->jagalCodeNumber = '';
        $this->resetErrorBag();
        $this->showStartModal = true;
    }

    public function closeStartModal(): void
    {
        $this->showStartModal = false;
        $this->startSohibulId = null;
        $this->jagalCodeNumber = '';
        $this->resetErrorBag();
    }

    public function openFinishConfirmModal(int $hewanId): void
    {
        $this->confirmFinishHewanId = $hewanId;
        $this->showConfirmFinishModal = true;
    }

    public function closeFinishConfirmModal(): void
    {
        $this->showConfirmFinishModal = false;
        $this->confirmFinishHewanId = null;
    }

    public function confirmFinishJagal(): void
    {
        if ($this->confirmFinishHewanId !== null) {
            $this->finishJagal($this->confirmFinishHewanId);
        }

        $this->closeFinishConfirmModal();
    }

    public function createFromJagal(): void
    {
        $validated = $this->validate([
            'startSohibulId' => ['required', 'exists:sohibul,id'],
            'jagalCodeNumber' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $sohibul = Sohibul::query()->findOrFail((int) $validated['startSohibulId']);

        $prefix = Str::lower($sohibul->jenis_qurban) === 'sapi' ? 'Sapi' : 'Kambing';
        $kode = $prefix.'-'.str_pad((string) ((int) $validated['jagalCodeNumber']), 2, '0', STR_PAD_LEFT);

        if (Hewan::query()->where('kode', $kode)->exists()) {
            $this->addError('jagalCodeNumber', 'Kode hewan sudah digunakan.');

            return;
        }

        Hewan::query()->create([
            'kode' => $kode,
            'sohibul_id' => $sohibul->id,
            'mulai_jagal' => now(),
        ]);

        $this->closeStartModal();
        Flux::toast(variant: 'success', text: 'Data hewan berhasil dibuat dan proses jagal dimulai.');
    }

    public function finishJagal(int $hewanId): void
    {
        $hewan = Hewan::query()->findOrFail($hewanId);

        if ($hewan->mulai_jagal === null || $hewan->selesai_jagal !== null) {
            return;
        }

        $hewan->update([
            'selesai_jagal' => now(),
        ]);

        Flux::toast(variant: 'success', text: 'Tahap jagal berhasil diselesaikan.');
    }

    public function with(): array
    {
        $sohibulItems = Sohibul::query()
            ->with(['hewan' => fn ($query) => $query->orderBy('id')])
            ->when($this->jenisQurbanFilter !== 'all', function ($query) {
                $query->where('jenis_qurban', $this->jenisQurbanFilter);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($inner) {
                    $inner->where('nama', 'like', '%'.$this->search.'%')
                        ->orWhere('request', 'like', '%'.$this->search.'%')
                        ->orWhereHas('hewan', function ($hewanQuery) {
                            $hewanQuery->where('kode', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->get();

        $sorted = $sohibulItems->sort(function (Sohibul $a, Sohibul $b): int {
            $metaA = $this->sortingMeta($a);
            $metaB = $this->sortingMeta($b);

            if ($metaA['group_rank'] !== $metaB['group_rank']) {
                return $metaA['group_rank'] <=> $metaB['group_rank'];
            }

            if ($metaA['group_rank'] === 0) {
                return $metaB['duration_seconds'] <=> $metaA['duration_seconds'];
            }

            if ($metaA['group_rank'] === 1) {
                return strcasecmp($metaA['name'], $metaB['name']);
            }

            return ($metaB['finished_at'] ?? 0) <=> ($metaA['finished_at'] ?? 0);
        })->values();

        $perPage = 12;
        $currentPage = $this->getPage();
        $pagedItems = $sorted->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $sohibulPaginator = new LengthAwarePaginator(
            $pagedItems,
            $sorted->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );

        return [
            'pageTitle' => 'Jagal',
            'sohibulItems' => $sohibulPaginator,
            'nowLabel' => now()->format('H:i:s'),
        ];
    }

    protected function currentJagalHewan(Sohibul $sohibul): ?Hewan
    {
        /** @var Collection<int, Hewan> $hewanCollection */
        $hewanCollection = $sohibul->hewan;

        return $hewanCollection
            ->sortByDesc('id')
            ->first();
    }

    /**
     * @return array{group_rank:int,duration_seconds:int,name:string,finished_at:?int}
     */
    protected function sortingMeta(Sohibul $sohibul): array
    {
        $hewan = $this->currentJagalHewan($sohibul);
        $name = $this->sohibulNameOnly($sohibul);

        if (! $hewan || $hewan->mulai_jagal === null) {
            return [
                'group_rank' => 1,
                'duration_seconds' => 0,
                'name' => $name,
                'finished_at' => null,
            ];
        }

        if ($hewan->selesai_jagal === null) {
            return [
                'group_rank' => 0,
                'duration_seconds' => $hewan->mulai_jagal->diffInSeconds(now()),
                'name' => $name,
                'finished_at' => null,
            ];
        }

        return [
            'group_rank' => 2,
            'duration_seconds' => $hewan->mulai_jagal->diffInSeconds($hewan->selesai_jagal),
            'name' => $name,
            'finished_at' => $hewan->selesai_jagal->timestamp,
        ];
    }

    public function sohibulNameOnly(Sohibul $sohibul): string
    {
        return is_array($sohibul->nama) ? implode(', ', $sohibul->nama) : (string) $sohibul->nama;
    }

    /**
     * @return array<int, string>
     */
    public function sohibulNames(Sohibul $sohibul): array
    {
        if (is_array($sohibul->nama)) {
            return array_values(array_filter($sohibul->nama, fn ($name) => filled($name)));
        }

        if (filled($sohibul->nama)) {
            return [(string) $sohibul->nama];
        }

        return [];
    }

    public function actionLabel(Sohibul $sohibul): string
    {
        $hewan = $this->currentJagalHewan($sohibul);

        if (! $hewan || $hewan->mulai_jagal === null) {
            return 'Mulai Jagal';
        }

        if ($hewan->selesai_jagal === null) {
            return 'Selesai Jagal';
        }

        return '-';
    }

    public function durationForSohibul(Sohibul $sohibul): string
    {
        $hewan = $this->currentJagalHewan($sohibul);

        return $this->durationLabel($hewan?->mulai_jagal, $hewan?->selesai_jagal);
    }

    public function doAction(Sohibul $sohibul): void
    {
        $hewan = $this->currentJagalHewan($sohibul);

        if (! $hewan || $hewan->mulai_jagal === null) {
            $this->openStartModal($sohibul->id);

            return;
        }

        if ($hewan->selesai_jagal === null) {
            $this->finishJagal($hewan->id);
        }
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
}; ?>

<section class="w-full space-y-6" wire:poll.10s x-data="dashboardDurations">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $pageTitle }}</flux:heading>
            <flux:subheading>Pantau dan update proses jagal secara realtime.</flux:subheading>
        </div>

        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
            Last updated at: {{ $nowLabel }}
        </flux:text>
    </div>

    <div class="flex flex-col gap-3 md:flex-row md:items-end">
        <flux:input wire:model.live="search" icon="magnifying-glass" :placeholder="__('Cari sohibul/request/kode hewan...')" class="md:max-w-sm" />

        <flux:select wire:model.live="jenisQurbanFilter" class="md:max-w-xs" :label="__('Filter Jenis Qurban')">
            <option value="all">Semua</option>
            <option value="sapi">Sapi</option>
            <option value="kambing">Kambing</option>
        </flux:select>
    </div>

    <div class="sr-only">Daftar Sohibul Qurban</div>

    <div class="sr-only">
        @foreach ($sohibulItems as $sohibul)
            <span>{{ ucfirst($sohibul->jenis_qurban) }} - {{ $this->sohibulNameOnly($sohibul) }}</span>
        @endforeach
    </div>

    <div class="space-y-3 md:hidden">
        @forelse ($sohibulItems as $sohibul)
            @php($hewan = $this->currentJagalHewan($sohibul))
            @php($actionLabel = $this->actionLabel($sohibul))

            <article class="space-y-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:subheading class="text-zinc-500">Sohibul Qurban</flux:subheading>
                        @php($names = $this->sohibulNames($sohibul))

                        @if (count($names) > 1)
                            <ol class="list-decimal pl-4 text-sm">
                                @foreach ($names as $name)
                                    <li>{{ $name }}</li>
                                @endforeach
                            </ol>
                        @elseif (count($names) === 1)
                            <flux:text class="font-medium">{{ $names[0] }}</flux:text>
                        @else
                            <flux:text>-</flux:text>
                        @endif
                    </div>
                    <flux:badge size="sm">{{ ucfirst($sohibul->jenis_qurban) }}</flux:badge>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <flux:text class="text-xs text-zinc-500">Durasi</flux:text>
                    <flux:text class="font-medium" x-text="stageDurationLabel({{ $hewan?->mulai_jagal?->timestamp ?? 'null' }}, {{ $hewan?->selesai_jagal?->timestamp ?? 'null' }})">{{ $this->durationForSohibul($sohibul) }}</flux:text>
                </div>

                <div>
                    <flux:subheading class="mb-1 text-zinc-500">Request</flux:subheading>
                    <flux:text>{{ $sohibul->request ?: '-' }}</flux:text>
                </div>

                <div>
                    @if ($actionLabel === 'Mulai Jagal')
                        <flux:button size="sm" class="w-full" variant="filled" wire:click="openStartModal({{ $sohibul->id }})">
                            {{ $actionLabel }}
                        </flux:button>
                    @elseif ($actionLabel === 'Selesai Jagal')
                        <flux:button size="sm" class="w-full" variant="primary" wire:click="openFinishConfirmModal({{ $hewan?->id }})">
                            {{ $actionLabel }}
                        </flux:button>
                    @else
                        <flux:text class="text-center text-zinc-500">-</flux:text>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-lg border border-zinc-200 px-4 py-6 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                Belum ada data untuk tahap ini.
            </div>
        @endforelse

        {{ $sohibulItems->links() }}
    </div>

    <div class="hidden md:block">
        <flux:table :paginate="$sohibulItems">
            <flux:table.columns>
                <flux:table.column>Sohibul Qurban</flux:table.column>
                <flux:table.column>Jenis Qurban</flux:table.column>
                <flux:table.column>Request</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
                <flux:table.column>Durasi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($sohibulItems as $sohibul)
                    @php($hewan = $this->currentJagalHewan($sohibul))
                    <flux:table.row :key="'jagal-sohibul-'.$sohibul->id">
                        <flux:table.cell>
                            @php($names = $this->sohibulNames($sohibul))

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
                        <flux:table.cell>{{ ucfirst($sohibul->jenis_qurban) }}</flux:table.cell>
                        <flux:table.cell>{{ $sohibul->request ?: '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @php($actionLabel = $this->actionLabel($sohibul))

                            @if ($actionLabel === 'Mulai Jagal')
                                <flux:button
                                    size="sm"
                                    variant="filled"
                                    wire:click="openStartModal({{ $sohibul->id }})"
                                >
                                    {{ $actionLabel }}
                                </flux:button>
                            @elseif ($actionLabel === 'Selesai Jagal')
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    wire:click="openFinishConfirmModal({{ $hewan?->id }})"
                                >
                                    {{ $actionLabel }}
                                </flux:button>
                            @else
                                -
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <span x-text="stageDurationLabel({{ $hewan?->mulai_jagal?->timestamp ?? 'null' }}, {{ $hewan?->selesai_jagal?->timestamp ?? 'null' }})">{{ $this->durationForSohibul($sohibul) }}</span>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-500 dark:text-zinc-400">Belum ada data untuk tahap ini.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showStartModal" class="md:w-[28rem]">
        <div class="space-y-4">
            <flux:heading size="lg">Mulai Jagal</flux:heading>
            <flux:subheading>Masukkan nomor kode hewan. Prefix jenis hewan dibuat otomatis.</flux:subheading>

            <flux:input
                type="number"
                wire:model="jagalCodeNumber"
                label="Nomor Kode"
                placeholder="Contoh: 1"
                min="1"
            />
            <flux:error name="jagalCodeNumber" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeStartModal">Batal</flux:button>
                <flux:button variant="primary" wire:click="createFromJagal">Mulai Jagal</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showConfirmFinishModal" class="md:w-[28rem]">
        <div class="space-y-4">
            <flux:heading size="lg">Selesaikan Jagal</flux:heading>
            <flux:text>Yakin ingin menyelesaikan proses jagal?</flux:text>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeFinishConfirmModal">Batal</flux:button>
                <flux:button variant="primary" wire:click="confirmFinishJagal">Ya, Selesaikan</flux:button>
            </div>
        </div>
    </flux:modal>
</section>

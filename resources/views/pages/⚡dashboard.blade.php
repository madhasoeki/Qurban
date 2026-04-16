<?php

use App\Models\Hewan;
use App\Models\Sohibul;
use Carbon\CarbonInterface;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.dashboard')] #[Title('Dashboard')] class extends Component
{
    public string $jenisFilter = 'all';

    public function with(): array
    {
        $sohibulQuery = Sohibul::query()
            ->when($this->jenisFilter !== 'all', function ($query) {
                $query->where('jenis_qurban', $this->jenisFilter);
            });

        $sohibulItems = (clone $sohibulQuery)
            ->with(['hewan' => fn ($query) => $query->latest('id')])
            ->orderByRaw("CASE WHEN jenis_qurban = 'sapi' THEN 0 WHEN jenis_qurban = 'kambing' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE WHEN (SELECT mulai_jagal FROM hewan WHERE hewan.sohibul_id = sohibul.id ORDER BY hewan.id DESC LIMIT 1) IS NULL THEN 1 ELSE 0 END")
            ->orderByRaw("(SELECT mulai_jagal FROM hewan WHERE hewan.sohibul_id = sohibul.id ORDER BY hewan.id DESC LIMIT 1) ASC")
            ->orderByDesc('id')
            ->paginate(15);

        $hewanQuery = Hewan::query()
            ->with('sohibul:id,nama,jenis_qurban,request')
            ->when($this->jenisFilter !== 'all', function ($query) {
                $query->whereHas('sohibul', function ($relation) {
                    $relation->where('jenis_qurban', $this->jenisFilter);
                });
            });

        $totalSohibulQurban = (clone $sohibulQuery)->count();
        $completedSohibulQurban = (clone $sohibulQuery)
            ->whereHas('hewan')
            ->whereDoesntHave('hewan', function ($query) {
                $query->whereNull('selesai_jagal')
                    ->orWhereNull('selesai_kuliti')
                    ->orWhereNull('selesai_cacah_daging')
                    ->orWhereNull('selesai_cacah_tulang')
                    ->orWhereNull('selesai_jeroan')
                    ->orWhereNull('selesai_packing')
                    ->orWhere('distribusi', '!=', 1);
            })
            ->count();

        $totalKantongPacking = (clone $hewanQuery)->sum('kantong_packing');
        $totalKantongDistribusi = (clone $hewanQuery)->sum('kantong_distribusi');

        $progressQurbanPercent = $totalSohibulQurban > 0
            ? round(($completedSohibulQurban / $totalSohibulQurban) * 100)
            : 0;

        return [
            'sohibulItems' => $sohibulItems,
            'progressQurbanPercent' => $progressQurbanPercent,
            'totalSohibulQurban' => $totalSohibulQurban,
            'completedSohibulQurban' => $completedSohibulQurban,
            'totalKantongPacking' => (int) $totalKantongPacking,
            'totalKantongDistribusi' => (int) $totalKantongDistribusi,
            'lastUpdatedAt' => now()->format('H:i:s'),
        ];
    }

    public function stageStatusLabel(?Hewan $hewan, string $startField, string $finishField): string
    {
        if (! $hewan) {
            return 'Belum Dimulai';
        }

        $start = $hewan->{$startField};
        $finish = $hewan->{$finishField};

        return $this->durationStageLabel($start, $finish);
    }

    public function packingStatusLabel(?Hewan $hewan): string
    {
        if (! $hewan) {
            return 'Belum Dimulai';
        }

        return $this->durationStageLabel($hewan->mulai_packing, $hewan->selesai_packing);
    }

    public function durationStageLabel(?CarbonInterface $start, ?CarbonInterface $finish = null): string
    {
        if ($start === null) {
            return 'Belum Dimulai';
        }

        $seconds = max(0, $start->diffInSeconds($finish ?? now()));
        $duration = sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);

        if ($finish !== null) {
            return 'Selesai - '.$duration;
        }

        return $duration;
    }

    public function distribusiStatusLabel(?Hewan $hewan): string
    {
        if (! $hewan) {
            return 'Belum';
        }

        if ((int) $hewan->distribusi === 1) {
            return 'Selesai - '.$hewan->kantong_distribusi.' Kantong';
        }

        return 'Belum';
    }

    public function progressPercent(?Hewan $hewan): int
    {
        if (! $hewan) {
            return 0;
        }

        $totalSteps = 7;
        $completedSteps = 0;

        $completedSteps += $hewan->selesai_jagal !== null ? 1 : 0;
        $completedSteps += $hewan->selesai_kuliti !== null ? 1 : 0;
        $completedSteps += $hewan->selesai_cacah_daging !== null ? 1 : 0;
        $completedSteps += $hewan->selesai_cacah_tulang !== null ? 1 : 0;
        $completedSteps += $hewan->selesai_jeroan !== null ? 1 : 0;
        $completedSteps += $hewan->selesai_packing !== null ? 1 : 0;
        $completedSteps += (int) $hewan->distribusi === 1 ? 1 : 0;

        return (int) round(($completedSteps / $totalSteps) * 100);
    }

    public function currentHewan(Sohibul $sohibul): ?Hewan
    {
        return $sohibul->hewan->first();
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
}; ?>

<section class="w-full space-y-6 p-6" wire:poll.1s x-data="dashboardDurations">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Dashboard Operasional Qurban') }}</flux:heading>
            <flux:subheading>{{ __('Realtime workflow monitor untuk seluruh proses.') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:select wire:model.live="jenisFilter" class="w-40">
                <option value="all">{{ __('Semua Jenis') }}</option>
                <option value="sapi">{{ __('Sapi') }}</option>
                <option value="kambing">{{ __('Kambing') }}</option>
            </flux:select>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Last updated at') }}: {{ $lastUpdatedAt }}
            </flux:text>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
        <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:subheading>{{ __('Progress Qurban') }}</flux:subheading>
            <div class="mt-2 flex items-center gap-4">
                <div class="relative h-20 w-20 rounded-full" style="background: conic-gradient(#22c55e {{ $progressQurbanPercent }}%, #e4e4e7 0%);">
                    <div class="absolute inset-2 grid place-content-center rounded-full bg-white text-sm font-semibold text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                        {{ $progressQurbanPercent }}%
                    </div>
                </div>
                <div>
                    <flux:text>{{ __('Selesai') }}: {{ $completedSohibulQurban }}</flux:text>
                    <flux:text>{{ __('Total Sohibul') }}: {{ $totalSohibulQurban }}</flux:text>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:subheading>{{ __('Total Hewan') }}</flux:subheading>
            <flux:heading size="xl" class="mt-1">{{ number_format($totalSohibulQurban) }}</flux:heading>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:subheading>{{ __('Total Kantong') }}</flux:subheading>
            <flux:heading size="xl" class="mt-1">{{ number_format($totalKantongPacking) }}</flux:heading>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:subheading>{{ __('Total Distribusi') }}</flux:subheading>
            <flux:heading size="xl" class="mt-1">{{ number_format($totalKantongDistribusi) }}</flux:heading>
        </div>
    </div>

    <div class="space-y-3 md:hidden">
        @forelse ($sohibulItems as $sohibul)
            @php($hewan = $this->currentHewan($sohibul))
            @php($progress = $this->progressPercent($hewan))

            <article class="space-y-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:subheading class="text-zinc-500">{{ __('Kode Hewan') }}</flux:subheading>
                        <flux:heading size="sm">{{ $hewan?->kode ?? '-' }}</flux:heading>
                    </div>
                    <flux:badge color="sky" size="sm">{{ $progress }}%</flux:badge>
                </div>

                <div>
                    <flux:subheading class="mb-1 text-zinc-500">{{ __('Sohibul Qurban') }}</flux:subheading>
                    @php($names = $this->sohibulNames($sohibul))

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

                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <flux:text class="text-zinc-500">{{ __('Penjagalan') }}</flux:text>
                        <flux:text>{{ $this->stageStatusLabel($hewan, 'mulai_jagal', 'selesai_jagal') }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500">{{ __('Pengulitan') }}</flux:text>
                        <flux:text>{{ $this->stageStatusLabel($hewan, 'mulai_kuliti', 'selesai_kuliti') }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500">{{ __('Cacah Daging') }}</flux:text>
                        <flux:text>{{ $this->stageStatusLabel($hewan, 'mulai_cacah_daging', 'selesai_cacah_daging') }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500">{{ __('Cacah Tulang') }}</flux:text>
                        <flux:text>{{ $this->stageStatusLabel($hewan, 'mulai_cacah_tulang', 'selesai_cacah_tulang') }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500">{{ __('Jeroan') }}</flux:text>
                        <flux:text>{{ $this->stageStatusLabel($hewan, 'mulai_jeroan', 'selesai_jeroan') }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500">{{ __('Packing') }}</flux:text>
                        <flux:text>{{ $this->packingStatusLabel($hewan) }}</flux:text>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <flux:text class="text-zinc-500">{{ __('Distribusi') }}</flux:text>
                        <flux:text>{{ $this->distribusiStatusLabel($hewan) }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500">{{ __('Request') }}</flux:text>
                        <flux:text>{{ $sohibul->request ?: '-' }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500">{{ __('Berat Awal') }}</flux:text>
                        <flux:text>{{ $hewan?->berat_awal ?? '-' }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500">{{ __('Berat Daging') }}</flux:text>
                        <flux:text>{{ $hewan?->berat_daging ?? '-' }}</flux:text>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-lg border border-zinc-200 px-4 py-6 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('Belum ada data hewan.') }}
            </div>
        @endforelse

        {{ $sohibulItems->links() }}
    </div>

    <div class="hidden md:block">
        <flux:table :paginate="$sohibulItems">
            <flux:table.columns>
                <flux:table.column>{{ __('Kode Hewan') }}</flux:table.column>
                <flux:table.column>{{ __('Sohibul Qurban') }}</flux:table.column>
                <flux:table.column>{{ __('Penjagalan') }}</flux:table.column>
                <flux:table.column>{{ __('Pengulitan') }}</flux:table.column>
                <flux:table.column>{{ __('Cacah Daging') }}</flux:table.column>
                <flux:table.column>{{ __('Cacah Tulang') }}</flux:table.column>
                <flux:table.column>{{ __('Jeroan') }}</flux:table.column>
                <flux:table.column>{{ __('Packing') }}</flux:table.column>
                <flux:table.column>{{ __('Distribusi') }}</flux:table.column>
                <flux:table.column>{{ __('Progress') }}</flux:table.column>
                <flux:table.column>{{ __('Berat Awal') }}</flux:table.column>
                <flux:table.column>{{ __('Berat Daging') }}</flux:table.column>
                <flux:table.column>{{ __('Berat Tulang') }}</flux:table.column>
                <flux:table.column>{{ __('Request') }}</flux:table.column>
                <flux:table.column>{{ __('Keterangan') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($sohibulItems as $sohibul)
                    @php($hewan = $this->currentHewan($sohibul))
                    @php($progress = $this->progressPercent($hewan))

                    <flux:table.row :key="'dashboard-sohibul-'.$sohibul->id">
                        <flux:table.cell>{{ $hewan?->kode ?? '-' }}</flux:table.cell>
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
                        <flux:table.cell>
                            @if ($hewan?->selesai_jagal !== null)
                                <div class="flex flex-col items-start gap-1 leading-tight">
                                    <flux:badge color="green">Selesai</flux:badge>
                                    <flux:badge color="cyan" x-text="stageDurationValue({{ $hewan?->mulai_jagal?->timestamp ?? 'null' }}, {{ $hewan?->selesai_jagal?->timestamp ?? 'null' }})">{{ $this->durationStageLabel($hewan?->mulai_jagal, $hewan?->selesai_jagal) }}</flux:badge>
                                </div>
                            @elseif ($hewan?->mulai_jagal !== null)
                                <flux:badge color="yellow" x-text="stageDurationValue({{ $hewan?->mulai_jagal?->timestamp ?? 'null' }}, {{ $hewan?->selesai_jagal?->timestamp ?? 'null' }})">{{ $this->stageStatusLabel($hewan, 'mulai_jagal', 'selesai_jagal') }}</flux:badge>
                            @else
                                <flux:badge color="red">Belum Mulai</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($hewan?->selesai_kuliti !== null)
                                <div class="flex flex-col items-start gap-1 leading-tight">
                                    <flux:badge color="green">Selesai</flux:badge>
                                    <flux:badge color="cyan" x-text="stageDurationValue({{ $hewan?->mulai_kuliti?->timestamp ?? 'null' }}, {{ $hewan?->selesai_kuliti?->timestamp ?? 'null' }})">{{ $this->durationStageLabel($hewan?->mulai_kuliti, $hewan?->selesai_kuliti) }}</flux:badge>
                                </div>
                            @elseif ($hewan?->mulai_kuliti !== null)
                                <flux:badge color="yellow" x-text="stageDurationValue({{ $hewan?->mulai_kuliti?->timestamp ?? 'null' }}, {{ $hewan?->selesai_kuliti?->timestamp ?? 'null' }})">{{ $this->stageStatusLabel($hewan, 'mulai_kuliti', 'selesai_kuliti') }}</flux:badge>
                            @else
                                <flux:badge color="red">Belum Mulai</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($hewan?->selesai_cacah_daging !== null)
                                <div class="flex flex-col items-start gap-1 leading-tight">
                                    <flux:badge color="green">Selesai</flux:badge>
                                    <flux:badge color="cyan" x-text="stageDurationValue({{ $hewan?->mulai_cacah_daging?->timestamp ?? 'null' }}, {{ $hewan?->selesai_cacah_daging?->timestamp ?? 'null' }})">{{ $this->durationStageLabel($hewan?->mulai_cacah_daging, $hewan?->selesai_cacah_daging) }}</flux:badge>
                                </div>
                            @elseif ($hewan?->mulai_cacah_daging !== null)
                                <flux:badge color="yellow" x-text="stageDurationValue({{ $hewan?->mulai_cacah_daging?->timestamp ?? 'null' }}, {{ $hewan?->selesai_cacah_daging?->timestamp ?? 'null' }})">{{ $this->stageStatusLabel($hewan, 'mulai_cacah_daging', 'selesai_cacah_daging') }}</flux:badge>
                            @else
                                <flux:badge color="red">Belum Mulai</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($hewan?->selesai_cacah_tulang !== null)
                                <div class="flex flex-col items-start gap-1 leading-tight">
                                    <flux:badge color="green">Selesai</flux:badge>
                                    <flux:badge color="cyan" x-text="stageDurationValue({{ $hewan?->mulai_cacah_tulang?->timestamp ?? 'null' }}, {{ $hewan?->selesai_cacah_tulang?->timestamp ?? 'null' }})">{{ $this->durationStageLabel($hewan?->mulai_cacah_tulang, $hewan?->selesai_cacah_tulang) }}</flux:badge>
                                </div>
                            @elseif ($hewan?->mulai_cacah_tulang !== null)
                                <flux:badge color="yellow" x-text="stageDurationValue({{ $hewan?->mulai_cacah_tulang?->timestamp ?? 'null' }}, {{ $hewan?->selesai_cacah_tulang?->timestamp ?? 'null' }})">{{ $this->stageStatusLabel($hewan, 'mulai_cacah_tulang', 'selesai_cacah_tulang') }}</flux:badge>
                            @else
                                <flux:badge color="red">Belum Mulai</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($hewan?->selesai_jeroan !== null)
                                <div class="flex flex-col items-start gap-1 leading-tight">
                                    <flux:badge color="green">Selesai</flux:badge>
                                    <flux:badge color="cyan" x-text="stageDurationValue({{ $hewan?->mulai_jeroan?->timestamp ?? 'null' }}, {{ $hewan?->selesai_jeroan?->timestamp ?? 'null' }})">{{ $this->durationStageLabel($hewan?->mulai_jeroan, $hewan?->selesai_jeroan) }}</flux:badge>
                                </div>
                            @elseif ($hewan?->mulai_jeroan !== null)
                                <flux:badge color="yellow" x-text="stageDurationValue({{ $hewan?->mulai_jeroan?->timestamp ?? 'null' }}, {{ $hewan?->selesai_jeroan?->timestamp ?? 'null' }})">{{ $this->stageStatusLabel($hewan, 'mulai_jeroan', 'selesai_jeroan') }}</flux:badge>
                            @else
                                <flux:badge color="red">Belum Mulai</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($hewan?->selesai_packing !== null)
                                <div class="flex flex-col items-start gap-1 leading-tight">
                                    <flux:badge color="green">Selesai</flux:badge>
                                    <flux:badge color="cyan" x-text="stageDurationValue({{ $hewan?->mulai_packing?->timestamp ?? 'null' }}, {{ $hewan?->selesai_packing?->timestamp ?? 'null' }})">{{ $this->durationStageLabel($hewan?->mulai_packing, $hewan?->selesai_packing) }}</flux:badge>
                                    <flux:badge color="zinc">{{ $hewan->kantong_packing }} Kantong</flux:badge>
                                </div>
                            @elseif ($hewan?->mulai_packing !== null)
                                <flux:badge color="yellow" x-text="stageDurationValue({{ $hewan?->mulai_packing?->timestamp ?? 'null' }}, {{ $hewan?->selesai_packing?->timestamp ?? 'null' }})">{{ $this->packingStatusLabel($hewan) }}</flux:badge>
                            @else
                                <flux:badge color="red">Belum Mulai</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ((int) ($hewan?->distribusi ?? 0) === 1)
                                <div class="flex flex-col items-start gap-1 leading-tight">
                                    <flux:badge color="green">Selesai</flux:badge>
                                    <flux:badge color="cyan">{{ $hewan?->kantong_distribusi ?? 0 }} Kantong</flux:badge>
                                </div>
                            @else
                                <flux:badge color="red">Belum Mulai</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex min-w-40 items-center gap-2">
                                <div class="h-2 w-24 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $progress }}%"></div>
                                </div>
                                <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $progress }}%</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $hewan?->berat_awal ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $hewan?->berat_daging ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $hewan?->berat_tulang ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $sohibul->request ?: '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $hewan?->keterangan ?? '-' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="15" class="text-center text-zinc-500 dark:text-zinc-400">
                            {{ __('Belum ada data hewan.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>

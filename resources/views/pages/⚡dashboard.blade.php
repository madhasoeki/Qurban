<?php

use App\Models\Distribusi;
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
                    ->orWhereNull('selesai_packing');
            })
            ->count();

        $totalKantongPacking = (clone $hewanQuery)->sum('kantong_packing');
        $totalKantongDistribusi = (int) Distribusi::query()->sum('jumlah');

        $hewanOverallItems = (clone $hewanQuery)
            ->select([
                'mulai_jagal',
                'selesai_jagal',
                'selesai_kuliti',
                'selesai_cacah_daging',
                'selesai_cacah_tulang',
                'selesai_jeroan',
                'selesai_packing',
            ])
            ->get();

        $overallStartAt = $hewanOverallItems
            ->pluck('mulai_jagal')
            ->filter()
            ->sortBy(fn (CarbonInterface $time) => $time->timestamp)
            ->first();

        $allHewanCompleted = $totalSohibulQurban > 0
            && $completedSohibulQurban === $totalSohibulQurban;

        $overallFinishAt = $allHewanCompleted
            ? $hewanOverallItems
                ->map(fn (Hewan $hewan) => $this->hewanDurationFinishTime($hewan))
                ->filter()
                ->sortByDesc(fn (CarbonInterface $time) => $time->timestamp)
                ->first()
            : null;

        $progressQurbanPercent = $totalSohibulQurban > 0
            ? round(($completedSohibulQurban / $totalSohibulQurban) * 100)
            : 0;

        return [
            'sohibulItems' => $sohibulItems,
            'progressQurbanPercent' => $progressQurbanPercent,
            'totalSohibulQurban' => $totalSohibulQurban,
            'completedSohibulQurban' => $completedSohibulQurban,
            'totalKantongPacking' => (int) $totalKantongPacking,
            'totalKantongDistribusi' => $totalKantongDistribusi,
            'overallStartLabel' => $this->timeLabel($overallStartAt),
            'overallFinishLabel' => $this->timeLabel($overallFinishAt),
            'overallDurationLabel' => $this->durationValueLabel($overallStartAt, $overallFinishAt),
            'overallStartTimestamp' => $overallStartAt?->timestamp,
            'overallFinishTimestamp' => $overallFinishAt?->timestamp,
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

    public function progressPercent(?Hewan $hewan): int
    {
        if (! $hewan) {
            return 0;
        }

        $totalSteps = 6;
        $completedSteps = 0;

        $completedSteps += $hewan->selesai_jagal !== null ? 1 : 0;
        $completedSteps += $hewan->selesai_kuliti !== null ? 1 : 0;
        $completedSteps += $hewan->selesai_cacah_daging !== null ? 1 : 0;
        $completedSteps += $hewan->selesai_cacah_tulang !== null ? 1 : 0;
        $completedSteps += $hewan->selesai_jeroan !== null ? 1 : 0;
        $completedSteps += $hewan->selesai_packing !== null ? 1 : 0;

        return (int) round(($completedSteps / $totalSteps) * 100);
    }

    public function hewanStartTime(?Hewan $hewan): ?CarbonInterface
    {
        return $hewan?->mulai_jagal;
    }

    public function hewanFinishTime(?Hewan $hewan): ?CarbonInterface
    {
        if (! $hewan) {
            return null;
        }

        return collect([
            $hewan->selesai_jagal,
            $hewan->selesai_kuliti,
            $hewan->selesai_cacah_daging,
            $hewan->selesai_cacah_tulang,
            $hewan->selesai_jeroan,
            $hewan->selesai_packing,
        ])
            ->filter()
            ->sortByDesc(fn (CarbonInterface $time) => $time->timestamp)
            ->first();
    }

    public function isHewanCompleted(?Hewan $hewan): bool
    {
        if (! $hewan) {
            return false;
        }

        return $hewan->selesai_jagal !== null
            && $hewan->selesai_kuliti !== null
            && $hewan->selesai_cacah_daging !== null
            && $hewan->selesai_cacah_tulang !== null
            && $hewan->selesai_jeroan !== null
            && $hewan->selesai_packing !== null;
    }

    public function hewanDurationFinishTime(?Hewan $hewan): ?CarbonInterface
    {
        if (! $this->isHewanCompleted($hewan)) {
            return null;
        }

        return $hewan?->selesai_packing;
    }

    public function timeLabel(?CarbonInterface $time): string
    {
        return $time?->format('H:i:s') ?? '-';
    }

    public function durationValueLabel(?CarbonInterface $start, ?CarbonInterface $finish = null): string
    {
        if ($start === null) {
            return '-';
        }

        $seconds = max(0, $start->diffInSeconds($finish ?? now()));

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
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

<section class="w-full space-y-6 p-0 lg:p-10" x-data="dashboardDurations" x-on:dashboard-data-updated.window="$wire.$refresh()">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">Dashboard Qurban Bahagia 2026</flux:heading>
            <flux:subheading>Realtime workflow monitor untuk seluruh proses qurban Masjid Ismuhu Yahya.</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:select wire:model.live="jenisFilter" class="w-40">
                <option value="all">Semua Jenis</option>
                <option value="sapi">Sapi</option>
                <option value="kambing">Kambing</option>
            </flux:select>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ $lastUpdatedAt }}
            </flux:text>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-10">
        <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900 lg:col-span-4">
            <flux:subheading size="xl">Progress Qurban</flux:subheading>
            <div class="mt-2 flex flex-col items-center gap-4 sm:flex-row sm:items-center">
                <div class="relative aspect-square w-full max-w-63 shrink-0 rounded-full" style="background: conic-gradient(#22c55e {{ $progressQurbanPercent }}%, #e4e4e7 0%);">
                    <div class="absolute inset-6 grid place-content-center rounded-full bg-white text-3xl font-semibold text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                        {{ $progressQurbanPercent }}%
                    </div>
                </div>
                <div class="text-center sm:text-left">
                    <flux:text size="xl">Selesai: {{ $completedSohibulQurban }} Ekor</flux:text>
                    <flux:text size="xl">Total Hewan: {{ $totalSohibulQurban }} Ekor</flux:text>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:col-span-3">
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:subheading size="lg">Total Hewan</flux:subheading>
                <flux:heading size="xl" class="mt-1">{{ number_format($totalSohibulQurban) }} Ekor</flux:heading>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:subheading size="lg">Total Kantong</flux:subheading>
                <flux:heading size="xl" class="mt-1">{{ number_format($totalKantongPacking) }} Kantong</flux:heading>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:subheading size="lg">Total Distribusi</flux:subheading>
                <flux:heading size="xl" class="mt-1">{{ number_format($totalKantongDistribusi) }} Kantong</flux:heading>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:col-span-3">
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:subheading size="lg">Waktu Mulai</flux:subheading>
                <flux:heading size="xl" class="mt-1">{{ $overallStartLabel }}</flux:heading>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:subheading size="lg">Waktu Selesai</flux:subheading>
                <flux:heading size="xl" class="mt-1">{{ $overallFinishLabel }}</flux:heading>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:subheading size="lg">Durasi Pengerjaan</flux:subheading>
                <flux:heading size="xl" class="mt-1" x-text="stageDurationValue({{ $overallStartTimestamp ?? 'null' }}, {{ $overallFinishTimestamp ?? 'null' }})">{{ $overallDurationLabel }}</flux:heading>
            </div>
        </div>
    </div>

    <div class="space-y-3 md:hidden">
        @forelse ($sohibulItems as $sohibul)
            @php($hewan = $this->currentHewan($sohibul))
            @php($progress = $this->progressPercent($hewan))

            <article class="space-y-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:subheading class="text-zinc-500 font-bold">Kode Hewan</flux:subheading>
                        <flux:heading size="sm">{{ $hewan?->kode ?? '-' }}</flux:heading>
                    </div>
                    <flux:badge color="sky" size="sm">{{ $progress }}%</flux:badge>
                </div>

                <div>
                    <flux:subheading class="mb-1 text-zinc-500 font-bold">Sohibul Qurban</flux:subheading>
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
                        <flux:text class="text-zinc-500 font-bold">Penjagalan</flux:text>
                        <flux:text>{{ $this->stageStatusLabel($hewan, 'mulai_jagal', 'selesai_jagal') }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500 font-bold">Pengulitan</flux:text>
                        <flux:text>{{ $this->stageStatusLabel($hewan, 'mulai_kuliti', 'selesai_kuliti') }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500 font-bold">Cacah Daging</flux:text>
                        <flux:text>{{ $this->stageStatusLabel($hewan, 'mulai_cacah_daging', 'selesai_cacah_daging') }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500 font-bold">Cacah Tulang</flux:text>
                        <flux:text>{{ $this->stageStatusLabel($hewan, 'mulai_cacah_tulang', 'selesai_cacah_tulang') }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500 font-bold">Jeroan</flux:text>
                        <flux:text>{{ $this->stageStatusLabel($hewan, 'mulai_jeroan', 'selesai_jeroan') }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500 font-bold">Packing</flux:text>
                        <flux:text>{{ $this->packingStatusLabel($hewan) }}</flux:text>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <flux:text class="text-zinc-500 font-bold">Request</flux:text>
                        <flux:text>{{ $sohibul->request ?: '-' }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500 font-bold">Berat Awal</flux:text>
                        <flux:text>{{ $hewan?->berat_awal ?? '-' }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500 font-bold">Berat Daging</flux:text>
                        <flux:text>{{ $hewan?->berat_daging ?? '-' }}</flux:text>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-lg border border-zinc-200 px-4 py-6 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                Belum ada data hewan.
            </div>
        @endforelse

        {{ $sohibulItems->links() }}
    </div>

    <div class="hidden md:block">
        <flux:table :paginate="$sohibulItems">
            <flux:table.columns>
                <flux:table.column>Kode </br>Hewan</flux:table.column>
                <flux:table.column>Sohibul Qurban</flux:table.column>
                <flux:table.column>Penjagalan</flux:table.column>
                <flux:table.column>Pengulitan</flux:table.column>
                <flux:table.column>Cacah Daging</flux:table.column>
                <flux:table.column>Cacah Tulang</flux:table.column>
                <flux:table.column>Jeroan</flux:table.column>
                <flux:table.column>Packing</flux:table.column>
                <flux:table.column>Progress</flux:table.column>
                <flux:table.column>Berat</br>Awal</flux:table.column>
                <flux:table.column>Berat</br>Daging</flux:table.column>
                <flux:table.column>Berat</br>Tulang</flux:table.column>
                <flux:table.column>Request</flux:table.column>
                <flux:table.column>Keterangan</flux:table.column>
                <flux:table.column>Waktu Mulai</flux:table.column>
                <flux:table.column>Waktu Selesai</flux:table.column>
                <flux:table.column>Durasi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($sohibulItems as $sohibul)
                    @php($hewan = $this->currentHewan($sohibul))
                    @php($progress = $this->progressPercent($hewan))
                    @php($hewanStartAt = $this->hewanStartTime($hewan))
                    @php($hewanFinishAt = $this->hewanDurationFinishTime($hewan))

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
                            <div class="flex min-w-40 items-center gap-2">
                                <div class="h-2 w-24 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $progress }}%"></div>
                                </div>
                                <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $progress }}%</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-center">{{ $hewan?->berat_awal ?? '-' }} Kg</flux:table.cell>
                        <flux:table.cell class="text-center">{{ $hewan?->berat_daging ?? '-' }} Kg</flux:table.cell>
                        <flux:table.cell class="text-center">{{ $hewan?->berat_tulang ?? '-' }} Kg</flux:table.cell>
                        <flux:table.cell class="max-w-[20ch] whitespace-normal break-words leading-snug">{{ $sohibul->request ?: '-' }}</flux:table.cell>
                        <flux:table.cell class="max-w-[20ch] whitespace-normal break-words leading-snug">{{ $hewan?->keterangan ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $this->timeLabel($hewanStartAt) }}</flux:table.cell>
                        <flux:table.cell>{{ $this->timeLabel($hewanFinishAt) }}</flux:table.cell>
                        <flux:table.cell>
                            <span x-text="stageDurationValue({{ $hewanStartAt?->timestamp ?? 'null' }}, {{ $hewanFinishAt?->timestamp ?? 'null' }})">{{ $this->durationValueLabel($hewanStartAt, $hewanFinishAt) }}</span>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="17" class="text-center text-zinc-500 dark:text-zinc-400">
                            Belum ada data hewan.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>

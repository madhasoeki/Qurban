<?php

use App\Models\Distribusi;
use App\Models\User;
use Carbon\Carbon;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.dashboard')] #[Title('Distribusi')] class extends Component
{
    /** @var array<int, int|string> */
    public array $jumlahByUser = [];

    public function mount(): void
    {
        $this->syncCounters();
    }

    public function increment(int $userId): void
    {
        if (! $this->canManageUserCounter($userId)) {
            return;
        }

        $current = (int) ($this->jumlahByUser[$userId] ?? 0);
        $this->jumlahByUser[$userId] = $current + 1;

        $this->persistJumlahForUser($userId);
    }

    public function decrement(int $userId): void
    {
        if (! $this->canManageUserCounter($userId)) {
            return;
        }

        $current = (int) ($this->jumlahByUser[$userId] ?? 0);
        $this->jumlahByUser[$userId] = max(0, $current - 1);

        $this->persistJumlahForUser($userId);
    }

    public function updateJumlah(int $userId): void
    {
        if (! $this->canManageUserCounter($userId)) {
            return;
        }

        $validated = $this->validate([
            'jumlahByUser.'.$userId => ['required', 'integer', 'min:0'],
        ]);

        $this->jumlahByUser[$userId] = (int) ($validated['jumlahByUser'][$userId] ?? 0);
        $this->persistJumlahForUser($userId);
    }

    private function persistJumlahForUser(int $userId): void
    {
        $jumlah = (int) ($this->jumlahByUser[$userId] ?? 0);

        if ($jumlah < 0) {
            $this->addError('jumlahByUser.'.$userId, 'Jumlah kantong distribusi tidak boleh negatif.');

            return;
        }

        Distribusi::query()->updateOrCreate(
            ['user_id' => $userId],
            ['jumlah' => $jumlah]
        );

        Flux::toast(variant: 'success', text: 'Distribusi berhasil diperbarui.');
    }

    private function syncCounters(): void
    {
        $distributorIds = $this->visibleDistributorUsers()->pluck('id');

        foreach ($distributorIds as $userId) {
            $counter = Distribusi::query()->firstOrCreate(['user_id' => $userId], ['jumlah' => 0]);

            if (! array_key_exists($userId, $this->jumlahByUser)) {
                $this->jumlahByUser[$userId] = (int) $counter->jumlah;
            }
        }

        $this->jumlahByUser = array_intersect_key($this->jumlahByUser, array_flip($distributorIds->all()));
    }

    private function visibleDistributorUsers()
    {
        $currentUser = auth()->user();

        $query = User::query()
            ->role('distribusi')
            ->orderBy('name');

        if ($currentUser && ! $currentUser->hasRole('admin')) {
            $query->whereKey($currentUser->id);
        }

        return $query->get(['id', 'name']);
    }

    public function canManageUserCounter(int $userId): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('admin') || (int) $user->id === $userId;
    }

    public function with(): array
    {
        $this->syncCounters();

        $distributorUsers = $this->visibleDistributorUsers();

        $totalDistribusi = Distribusi::query()
            ->sum('jumlah');

        $lastUpdatedAt = Distribusi::query()
            ->whereIn('user_id', $distributorUsers->pluck('id'))
            ->max('updated_at');

        return [
            'pageTitle' => 'Distribusi',
            'distributorUsers' => $distributorUsers,
            'totalDistribusi' => (int) $totalDistribusi,
            'nowLabel' => now()->format('H:i:s'),
            'lastUpdatedAt' => $lastUpdatedAt ? Carbon::parse($lastUpdatedAt)->format('d M Y H:i:s') : '-',
        ];
    }
}; ?>

<section class="w-full space-y-6" wire:poll.10s>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $pageTitle }}</flux:heading>
            <flux:subheading>Update jumlah kantong distribusi secara realtime.</flux:subheading>
        </div>
        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Last updated at: {{ $nowLabel }}</flux:text>
    </div>

    <div class="mx-auto max-w-2xl rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="text-center">
            <flux:subheading size="lg">Total Kantong Terdistribusi</flux:subheading>
            <flux:heading size="xl" class="mt-2">{{ number_format($totalDistribusi) }} Kantong</flux:heading>
            <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Record updated: {{ $lastUpdatedAt }}</flux:text>
        </div>

        <div class="mt-6 space-y-3">
            @forelse ($distributorUsers as $distributor)
                <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <flux:text class="font-medium">{{ $distributor->name }}</flux:text>
                        <flux:badge>{{ number_format((int) ($jumlahByUser[$distributor->id] ?? 0)) }} Kantong</flux:badge>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:button
                            variant="ghost"
                            icon="minus"
                            wire:click="decrement({{ $distributor->id }})"
                            :disabled="! $this->canManageUserCounter($distributor->id)"
                        ></flux:button>

                        <flux:input
                            type="number"
                            min="0"
                            wire:model.blur="jumlahByUser.{{ $distributor->id }}"
                            class="w-full text-center"
                            :disabled="! $this->canManageUserCounter($distributor->id)"
                        />

                        <flux:button
                            variant="primary"
                            icon="plus"
                            wire:click="increment({{ $distributor->id }})"
                            :disabled="! $this->canManageUserCounter($distributor->id)"
                        ></flux:button>

                        <flux:button
                            variant="primary"
                            wire:click="updateJumlah({{ $distributor->id }})"
                            :disabled="! $this->canManageUserCounter($distributor->id)"
                        >Update</flux:button>
                    </div>

                    @error('jumlahByUser.'.$distributor->id)
                        <flux:text class="mt-2 block text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>
            @empty
                <div class="rounded-lg border border-zinc-200 px-4 py-6 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    Belum ada user dengan role distribusi.
                </div>
            @endforelse
        </div>
    </div>
</section>

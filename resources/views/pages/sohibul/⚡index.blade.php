<?php

use App\Models\Sohibul;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Manage Sohibul')] class extends Component {
    use WithPagination;

    public string $search = '';

    public ?int $sohibulId = null;

    /** @var array<int, string> */
    public array $names = [''];

    public string $jenisQurban = 'sapi';

    public string $requestNote = '';

    protected function rules(): array
    {
        return [
            'names' => ['required', 'array', 'min:1', 'max:7'],
            'names.*' => ['required', 'string', 'max:255', 'distinct'],
            'jenisQurban' => ['required', 'in:sapi,kambing'],
            'requestNote' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearForm(): void
    {
        $this->sohibulId = null;
        $this->names = [''];
        $this->jenisQurban = 'sapi';
        $this->requestNote = '';
        $this->resetErrorBag();
    }

    public function addNameField(): void
    {
        if (count($this->names) < 7) {
            $this->names[] = '';
        }
    }

    public function removeNameField(int $index): void
    {
        unset($this->names[$index]);
        $this->names = array_values($this->names);

        if ($this->names === []) {
            $this->names = [''];
        }
    }

    public function edit(Sohibul $sohibul): void
    {
        $this->clearForm();

        $this->sohibulId = $sohibul->id;
        $this->names = $sohibul->nama;
        $this->jenisQurban = $sohibul->jenis_qurban;
        $this->requestNote = $sohibul->request ?? '';

        $this->modal('sohibul-modal')->show();
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'nama' => $this->names,
            'jenis_qurban' => $this->jenisQurban,
            'request' => $this->requestNote ?: null,
        ];

        if ($this->sohibulId) {
            $sohibul = Sohibul::query()->findOrFail($this->sohibulId);
            $sohibul->update($payload);
            Flux::toast(variant: 'success', text: __('Sohibul updated successfully.'));
        } else {
            Sohibul::query()->create($payload);
            Flux::toast(variant: 'success', text: __('Sohibul created successfully.'));
        }

        $this->dispatch('refresh-sohibul');
        $this->modal('sohibul-modal')->close();
        $this->clearForm();
    }

    public function delete(Sohibul $sohibul): void
    {
        $this->sohibulId = $sohibul->id;
        $this->modal('delete-sohibul-modal')->show();
    }

    public function confirmDelete(): void
    {
        Sohibul::query()->findOrFail($this->sohibulId)?->delete();

        $this->dispatch('refresh-sohibul');
        Flux::toast(variant: 'success', text: __('Sohibul deleted successfully.'));
        $this->modal('delete-sohibul-modal')->close();
        $this->clearForm();
    }

    protected $listeners = ['refresh-sohibul' => '$refresh'];

    public function with(): array
    {
        $sohibul = Sohibul::query()
            ->when($this->search, function ($query) {
                $query->where(function ($inner) {
                    $inner->whereJsonContains('nama', $this->search)
                        ->orWhere('nama', 'like', '%' . $this->search . '%')
                        ->orWhere('request', 'like', '%' . $this->search . '%');
                });
            })
            ->latest()
            ->paginate(10);

        return [
            'sohibulItems' => $sohibul,
        ];
    }
}; ?>

<section class="w-full p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Sohibul') }}</flux:heading>
            <flux:subheading>{{ __('Manage sohibul data and up to 7 names per entry.') }}</flux:subheading>
        </div>

        <flux:modal.trigger name="sohibul-modal">
            <flux:button variant="primary" icon="plus" wire:click="clearForm">{{ __('Add Sohibul') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="flex items-center justify-between gap-4">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="{{ __('Search sohibul...') }}" class="max-w-xs" />
    </div>

    <div class="space-y-3 md:hidden">
        @foreach ($sohibulItems as $sohibul)
            <article class="space-y-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex flex-wrap gap-1">
                        @foreach ($sohibul->nama as $name)
                            <flux:badge :key="'sohibul-mobile-'.$sohibul->id.'-name-'.$loop->index" size="sm" inset="top bottom">{{ $name }}</flux:badge>
                        @endforeach
                    </div>
                    <flux:badge color="sky" size="sm">{{ ucfirst($sohibul->jenis_qurban) }}</flux:badge>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <flux:text class="text-xs text-zinc-500">{{ __('Request') }}</flux:text>
                    <flux:text class="text-xs text-zinc-500">{{ $sohibul->created_at->format('M d, Y') }}</flux:text>
                </div>
                <flux:text>{{ $sohibul->request ?: '-' }}</flux:text>

                <div class="grid grid-cols-2 gap-2">
                    <flux:button size="sm" variant="ghost" wire:click="edit({{ $sohibul->id }})">{{ __('Edit') }}</flux:button>
                    <flux:button size="sm" variant="danger" wire:click="delete({{ $sohibul->id }})">{{ __('Delete') }}</flux:button>
                </div>
            </article>
        @endforeach

        {{ $sohibulItems->links() }}
    </div>

    <div class="hidden md:block">
        <flux:table :paginate="$sohibulItems">
            <flux:table.columns>
                <flux:table.column>{{ __('Names') }}</flux:table.column>
                <flux:table.column>{{ __('Jenis Qurban') }}</flux:table.column>
                <flux:table.column>{{ __('Request') }}</flux:table.column>
                <flux:table.column>{{ __('Created At') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($sohibulItems as $sohibul)
                    <flux:table.row :key="$sohibul->id">
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($sohibul->nama as $name)
                                    <flux:badge :key="'sohibul-'.$sohibul->id.'-name-'.$loop->index" size="sm" inset="top bottom">{{ $name }}</flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="sky" size="sm">{{ ucfirst($sohibul->jenis_qurban) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $sohibul->request ?: '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $sohibul->created_at->format('M d, Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $sohibul->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $sohibul->id }})">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="sohibul-modal" class="md:w-[34rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $sohibulId ? __('Edit Sohibul') : __('Add Sohibul') }}</flux:heading>
                <flux:subheading>{{ __('You can add up to 7 names in one entry.') }}</flux:subheading>
            </div>

            <form wire:submit="save" class="space-y-4">
                <flux:field>
                    <div class="flex items-center justify-between">
                        <flux:label>{{ __('Names') }}</flux:label>
                        <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addNameField" :disabled="count($names) >= 7">
                            {{ __('Add Name') }}
                        </flux:button>
                    </div>

                    <div class="space-y-2 mt-2">
                        @foreach ($names as $index => $name)
                            <div class="flex items-center gap-2" wire:key="name-input-{{ $index }}">
                                <flux:input wire:model="names.{{ $index }}" :placeholder="__('Name').' #'.($index + 1)" class="flex-1" />
                                @if (count($names) > 1)
                                    <flux:button type="button" variant="ghost" size="sm" icon="x-mark" wire:click="removeNameField({{ $index }})" />
                                @endif
                            </div>
                            <flux:error :name="'names.'.$index" />
                        @endforeach
                    </div>

                    <flux:error name="names" />
                </flux:field>

                <flux:select wire:model="jenisQurban" :label="__('Jenis Qurban')">
                    <option value="sapi">{{ __('Sapi') }}</option>
                    <option value="kambing">{{ __('Kambing') }}</option>
                </flux:select>
                <flux:error name="jenisQurban" />

                <flux:textarea wire:model="requestNote" :label="__('Request')" :placeholder="__('Optional request note')" rows="4" />

                <div class="flex gap-2 justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="delete-sohibul-modal" class="md:w-[25rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Sohibul') }}</flux:heading>
                <flux:subheading>{{ __('Are you sure you want to delete this sohibul data? This action cannot be undone.') }}</flux:subheading>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="confirmDelete">{{ __('Delete Sohibul') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
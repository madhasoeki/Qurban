<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Title('Manage Users')] class extends Component
{
    use WithPagination;

    public string $search = '';

    // Form fields
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    /** @var array<int, string> */
    public array $selectedRoles = [];

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'password' => $this->userId ? 'nullable|min:8' : 'required|min:8',
            'selectedRoles' => 'required|array|min:1',
            'selectedRoles.*' => ['required', 'string', Rule::exists('roles', 'name')],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearForm(): void
    {
        $this->userId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->selectedRoles = [];
        $this->resetErrorBag();
    }

    public function edit(User $user): void
    {
        $this->clearForm();
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->selectedRoles = $user->roles->pluck('name')->toArray();

        $this->modal('user-modal')->show();
    }

    public function save(): void
    {
        $this->validate();

        if ($this->userId) {
            $user = User::findOrFail($this->userId);
            $user->update([
                'name' => $this->name,
                'email' => $this->email,
            ]);

            if ($this->password) {
                $user->update(['password' => Hash::make($this->password)]);
            }

            $user->syncRoles($this->selectedRoles);
            Flux::toast(variant: 'success', text: __('User updated successfully.'));
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
            ]);

            $user->assignRole($this->selectedRoles);
            Flux::toast(variant: 'success', text: __('User created successfully.'));
        }

        $this->dispatch('refresh-users');
        $this->modal('user-modal')->close();
        $this->clearForm();
    }

    public function delete(User $user): void
    {
        $this->userId = $user->id;
        $this->modal('delete-user-modal')->show();
    }

    public function confirmDelete(): void
    {
        $user = User::findOrFail($this->userId);

        if ($user->id === auth()->id()) {
            Flux::toast(variant: 'danger', text: __('You cannot delete yourself.'));
            $this->modal('delete-user-modal')->close();

            return;
        }

        $user->delete();
        $this->dispatch('refresh-users');
        Flux::toast(variant: 'success', text: __('User deleted successfully.'));
        $this->modal('delete-user-modal')->close();
    }

    protected $listeners = ['refresh-users' => '$refresh'];

    public function with(): array
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate(10);

        return [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
        ];
    }
}; ?>

<section class="w-full p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Users') }}</flux:heading>
            <flux:subheading>{{ __('Manage your application users and their roles.') }}</flux:subheading>
        </div>

        <flux:modal.trigger name="user-modal">
            <flux:button variant="primary" icon="plus" wire:click="clearForm">{{ __('Add User') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="flex items-center justify-between gap-4">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="{{ __('Search users...') }}" class="max-w-xs" />
    </div>

    <div class="space-y-3 md:hidden">
        @foreach ($users as $user)
            <article class="space-y-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <flux:avatar :name="$user->name" :initials="$user->initials()" size="sm" />
                        <div>
                            <flux:heading size="sm">{{ $user->name }}</flux:heading>
                            <flux:text class="text-xs text-zinc-500">{{ $user->email }}</flux:text>
                        </div>
                    </div>
                    <flux:text class="text-xs text-zinc-500">{{ $user->created_at->format('M d, Y') }}</flux:text>
                </div>

                <div class="flex flex-wrap gap-1">
                    @foreach ($user->roles as $role)
                        <flux:badge :key="'user-mobile-'.$user->id.'-role-'.$role->id" size="sm" inset="top bottom">{{ $role->name }}</flux:badge>
                    @endforeach
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <flux:button size="sm" variant="ghost" wire:click="edit({{ $user->id }})">{{ __('Edit') }}</flux:button>
                    <flux:button size="sm" variant="danger" wire:click="delete({{ $user->id }})">{{ __('Delete') }}</flux:button>
                </div>
            </article>
        @endforeach

        {{ $users->links() }}
    </div>

    <div class="hidden md:block">
        <flux:table :paginate="$users">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Roles') }}</flux:table.column>
                <flux:table.column>{{ __('Created At') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <flux:avatar :name="$user->name" :initials="$user->initials()" size="sm" />
                                <span class="font-medium text-zinc-800 dark:text-white">{{ $user->name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($user->roles as $role)
                                    <flux:badge :key="'user-'.$user->id.'-role-'.$role->id" size="sm" inset="top bottom">{{ $role->name }}</flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->created_at->format('M d, Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $user->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $user->id }})">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <!-- User Modal (Create/Edit) -->
    <flux:modal name="user-modal" class="md:w-[25rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $userId ? __('Edit User') : __('Add User') }}</flux:heading>
                <flux:subheading>{{ __('Fill in the user information below.') }}</flux:subheading>
            </div>

            <form wire:submit="save" class="space-y-4">
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="email" :label="__('Email')" type="email" required />
                <flux:input wire:model="password" :label="__('Password')" type="password" :placeholder="$userId ? __('Leave blank to keep current password') : ''" />

                <flux:field>
                    <flux:label>{{ __('Roles') }}</flux:label>
                    <div class="grid grid-cols-2 gap-2 mt-2">
                        @foreach ($roles as $role)
                            <flux:checkbox :key="'role-checkbox-'.$role->id" wire:model="selectedRoles" :value="$role->name" :label="$role->name" />
                        @endforeach
                    </div>
                    <flux:error name="selectedRoles" />
                </flux:field>

                <div class="flex gap-2 justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal name="delete-user-modal" class="md:w-[25rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete User') }}</flux:heading>
                <flux:subheading>{{ __('Are you sure you want to delete this user? This action cannot be undone.') }}</flux:subheading>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="confirmDelete">{{ __('Delete User') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>

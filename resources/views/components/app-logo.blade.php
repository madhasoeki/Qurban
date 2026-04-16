@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="{{ config('app.name') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md">
            <img src="{{ asset('assets/logo/Subtract.png') }}" alt="Logo">
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ config('app.name') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md text-accent-foreground">
            <img src="{{ asset('assets/logo/Subtract.png') }}" alt="Logo">
        </x-slot>
    </flux:brand>
@endif

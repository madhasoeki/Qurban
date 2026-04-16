@if (auth()->check())
    <x-layouts::app.sidebar :title="$title ?? null">
        <flux:main>
            {{ $slot }}
        </flux:main>
    </x-layouts::app.sidebar>
@else
    <x-layouts::plain :title="$title ?? null">
        {{ $slot }}
    </x-layouts::plain>
@endif

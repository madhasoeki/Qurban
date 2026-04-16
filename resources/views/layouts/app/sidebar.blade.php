<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800 pb-20 lg:pb-0">
        <flux:sidebar sticky class="hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 lg:flex">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    @if (auth()->user()->hasRole('admin'))
                        <flux:sidebar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.index')" wire:navigate>
                            {{ __('Users') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="user-group" :href="route('sohibul.index')" :current="request()->routeIs('sohibul.index')" wire:navigate>
                            {{ __('Sohibul') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasAnyRole(['admin', 'jagal']))
                        <flux:sidebar.item icon="scissors" :href="route('workflow.jagal')" :current="request()->routeIs('workflow.jagal')" wire:navigate>
                            {{ __('Jagal') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasAnyRole(['admin', 'kuliti']))
                        <flux:sidebar.item icon="sparkles" :href="route('workflow.kuliti')" :current="request()->routeIs('workflow.kuliti')" wire:navigate>
                            {{ __('Kuliti') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasAnyRole(['admin', 'cacah_daging']))
                        <flux:sidebar.item icon="rectangle-stack" :href="route('workflow.cacah_daging')" :current="request()->routeIs('workflow.cacah_daging')" wire:navigate>
                            {{ __('Cacah Daging') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasAnyRole(['admin', 'cacah_tulang']))
                        <flux:sidebar.item icon="cube" :href="route('workflow.cacah_tulang')" :current="request()->routeIs('workflow.cacah_tulang')" wire:navigate>
                            {{ __('Cacah Tulang') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasAnyRole(['admin', 'jeroan']))
                        <flux:sidebar.item icon="fire" :href="route('workflow.jeroan')" :current="request()->routeIs('workflow.jeroan')" wire:navigate>
                            {{ __('Jeroan') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasAnyRole(['admin', 'packing']))
                        <flux:sidebar.item icon="archive-box" :href="route('workflow.packing')" :current="request()->routeIs('workflow.packing')" wire:navigate>
                            {{ __('Packing') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasAnyRole(['admin', 'distribusi']))
                        <flux:sidebar.item icon="truck" :href="route('workflow.distribusi')" :current="request()->routeIs('workflow.distribusi')" wire:navigate>
                            {{ __('Distribusi') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasAnyRole(['admin', 'penimbang']))
                        <flux:sidebar.item icon="scale" :href="route('workflow.penimbang')" :current="request()->routeIs('workflow.penimbang')" wire:navigate>
                            {{ __('Penimbang') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile Header -->
        <flux:header class="lg:hidden">
            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        @php
            $authenticatedUser = auth()->user();

            $mobileBottomNavVisibleCount = 1;
            $mobileBottomNavVisibleCount += (int) $authenticatedUser->hasRole('admin');
            $mobileBottomNavVisibleCount += (int) $authenticatedUser->hasRole('admin');
            $mobileBottomNavVisibleCount += (int) $authenticatedUser->hasAnyRole(['admin', 'jagal']);
            $mobileBottomNavVisibleCount += (int) $authenticatedUser->hasAnyRole(['admin', 'kuliti']);
            $mobileBottomNavVisibleCount += (int) $authenticatedUser->hasAnyRole(['admin', 'cacah_daging']);
            $mobileBottomNavVisibleCount += (int) $authenticatedUser->hasAnyRole(['admin', 'cacah_tulang']);
            $mobileBottomNavVisibleCount += (int) $authenticatedUser->hasAnyRole(['admin', 'jeroan']);
            $mobileBottomNavVisibleCount += (int) $authenticatedUser->hasAnyRole(['admin', 'packing']);
            $mobileBottomNavVisibleCount += (int) $authenticatedUser->hasAnyRole(['admin', 'distribusi']);
            $mobileBottomNavVisibleCount += (int) $authenticatedUser->hasAnyRole(['admin', 'penimbang']);

            $mobileBottomNavIsSplit = $mobileBottomNavVisibleCount === 2;
            $mobileBottomNavContainerClass = $mobileBottomNavIsSplit
                ? 'grid grid-cols-2 gap-1 px-2 py-2'
                : 'flex items-center gap-1 overflow-x-auto px-2 py-2';
            $mobileBottomNavItemBaseClass = $mobileBottomNavIsSplit
                ? 'w-full rounded-lg px-2 py-1.5 text-[10px] font-medium leading-none transition flex flex-col items-center justify-center gap-1 text-center'
                : 'min-w-[4.5rem] rounded-lg px-2 py-1.5 text-[10px] font-medium leading-none transition flex flex-col items-center justify-center gap-1 text-center';
        @endphp

        <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200 bg-white/95 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95 lg:hidden">
            <div class="{{ $mobileBottomNavContainerClass }}">
                <a href="{{ route('dashboard') }}" wire:navigate @class([
                    $mobileBottomNavItemBaseClass,
                    'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => request()->routeIs('dashboard'),
                    'text-zinc-700 dark:text-zinc-200' => ! request()->routeIs('dashboard'),
                ])>
                    <flux:icon.home class="size-5" />
                    {{ __('Dashboard') }}
                </a>

                @if (auth()->user()->hasRole('admin'))
                    <a href="{{ route('users.index') }}" wire:navigate @class([
                        $mobileBottomNavItemBaseClass,
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => request()->routeIs('users.index'),
                        'text-zinc-700 dark:text-zinc-200' => ! request()->routeIs('users.index'),
                    ])>
                        <flux:icon.users class="size-5" />
                        {{ __('Users') }}
                    </a>

                    <a href="{{ route('sohibul.index') }}" wire:navigate @class([
                        $mobileBottomNavItemBaseClass,
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => request()->routeIs('sohibul.index'),
                        'text-zinc-700 dark:text-zinc-200' => ! request()->routeIs('sohibul.index'),
                    ])>
                        <flux:icon.user-group class="size-5" />
                        {{ __('Sohibul') }}
                    </a>
                @endif

                @if (auth()->user()->hasAnyRole(['admin', 'jagal']))
                    <a href="{{ route('workflow.jagal') }}" wire:navigate @class([
                        $mobileBottomNavItemBaseClass,
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => request()->routeIs('workflow.jagal'),
                        'text-zinc-700 dark:text-zinc-200' => ! request()->routeIs('workflow.jagal'),
                    ])>
                        <flux:icon.scissors class="size-5" />
                        {{ __('Jagal') }}
                    </a>
                @endif

                @if (auth()->user()->hasAnyRole(['admin', 'kuliti']))
                    <a href="{{ route('workflow.kuliti') }}" wire:navigate @class([
                        $mobileBottomNavItemBaseClass,
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => request()->routeIs('workflow.kuliti'),
                        'text-zinc-700 dark:text-zinc-200' => ! request()->routeIs('workflow.kuliti'),
                    ])>
                        <flux:icon.sparkles class="size-5" />
                        {{ __('Kuliti') }}
                    </a>
                @endif

                @if (auth()->user()->hasAnyRole(['admin', 'cacah_daging']))
                    <a href="{{ route('workflow.cacah_daging') }}" wire:navigate @class([
                        $mobileBottomNavItemBaseClass,
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => request()->routeIs('workflow.cacah_daging'),
                        'text-zinc-700 dark:text-zinc-200' => ! request()->routeIs('workflow.cacah_daging'),
                    ])>
                        <flux:icon.rectangle-stack class="size-5" />
                        {{ __('Daging') }}
                    </a>
                @endif

                @if (auth()->user()->hasAnyRole(['admin', 'cacah_tulang']))
                    <a href="{{ route('workflow.cacah_tulang') }}" wire:navigate @class([
                        $mobileBottomNavItemBaseClass,
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => request()->routeIs('workflow.cacah_tulang'),
                        'text-zinc-700 dark:text-zinc-200' => ! request()->routeIs('workflow.cacah_tulang'),
                    ])>
                        <flux:icon.cube class="size-5" />
                        {{ __('Tulang') }}
                    </a>
                @endif

                @if (auth()->user()->hasAnyRole(['admin', 'jeroan']))
                    <a href="{{ route('workflow.jeroan') }}" wire:navigate @class([
                        $mobileBottomNavItemBaseClass,
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => request()->routeIs('workflow.jeroan'),
                        'text-zinc-700 dark:text-zinc-200' => ! request()->routeIs('workflow.jeroan'),
                    ])>
                        <flux:icon.fire class="size-5" />
                        {{ __('Jeroan') }}
                    </a>
                @endif

                @if (auth()->user()->hasAnyRole(['admin', 'packing']))
                    <a href="{{ route('workflow.packing') }}" wire:navigate @class([
                        $mobileBottomNavItemBaseClass,
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => request()->routeIs('workflow.packing'),
                        'text-zinc-700 dark:text-zinc-200' => ! request()->routeIs('workflow.packing'),
                    ])>
                        <flux:icon.archive-box class="size-5" />
                        {{ __('Packing') }}
                    </a>
                @endif

                @if (auth()->user()->hasAnyRole(['admin', 'distribusi']))
                    <a href="{{ route('workflow.distribusi') }}" wire:navigate @class([
                        $mobileBottomNavItemBaseClass,
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => request()->routeIs('workflow.distribusi'),
                        'text-zinc-700 dark:text-zinc-200' => ! request()->routeIs('workflow.distribusi'),
                    ])>
                        <flux:icon.truck class="size-5" />
                        {{ __('Distribusi') }}
                    </a>
                @endif

                @if (auth()->user()->hasAnyRole(['admin', 'penimbang']))
                    <a href="{{ route('workflow.penimbang') }}" wire:navigate @class([
                        $mobileBottomNavItemBaseClass,
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => request()->routeIs('workflow.penimbang'),
                        'text-zinc-700 dark:text-zinc-200' => ! request()->routeIs('workflow.penimbang'),
                    ])>
                        <flux:icon.scale class="size-5" />
                        {{ __('Timbang') }}
                    </a>
                @endif
            </div>
        </nav>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>

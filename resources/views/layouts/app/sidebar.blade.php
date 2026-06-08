<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body
        x-data="{ mobileNavOpen: false }"
        @keydown.escape.window="mobileNavOpen = false"
        class="min-h-screen overflow-x-hidden bg-zinc-50 dark:bg-zinc-950"
    >
        @php
            $user = auth()->user();
            $canSeeDashboard = $user?->hasStaffAccess() ?? false;
            $canManageLibrary = $canSeeDashboard;
            $isMember = $user?->effectiveRole() === 'narys';
            $homeRoute = $canSeeDashboard ? route('dashboard') : ($isMember ? route('account.dashboard') : route('books.index'));
            $unreadNotificationsCount = $user ? $user->unreadNotifications()->count() : 0;
            $desktopSearchRoute = $canManageLibrary ? route('manage.search.index') : route('books.index');
            $desktopSearchName = $canManageLibrary ? 'q' : 'search';
        @endphp

        <header class="sticky top-0 z-40 border-b border-zinc-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/85 dark:border-zinc-800 dark:bg-zinc-900/95 dark:supports-[backdrop-filter]:bg-zinc-900/85 lg:hidden">
            <div class="flex h-16 items-center justify-between gap-3 px-4">
                <div class="flex min-w-0 items-center gap-3">
                    <flux:sidebar.toggle
                        @click="mobileNavOpen = true"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-700 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-emerald-500/60 dark:hover:text-emerald-300"
                        icon="bars-2"
                        inset="left"
                        aria-label="{{ __('Atidaryti navigaciją') }}"
                    />

                    <a href="{{ $homeRoute }}" wire:navigate class="flex min-w-0 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-700 text-white shadow-sm">
                            <x-app-logo-icon class="size-5 fill-current" />
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-base font-semibold text-zinc-950 dark:text-white">LibraryApp</span>
                            <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $canSeeDashboard ? 'Bibliotekos valdymas' : 'Mano biblioteka' }}
                            </span>
                        </span>
                    </a>
                </div>
            </div>
        </header>

        <div
            x-cloak
            x-show="mobileNavOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 bg-zinc-950/45 backdrop-blur-sm lg:hidden"
            @click="mobileNavOpen = false"
        ></div>

        <aside
            id="mobile-account-navigation"
            x-cloak
            x-show="mobileNavOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            @click.away="mobileNavOpen = false"
            class="fixed inset-y-0 left-0 z-50 flex w-[min(22rem,calc(100vw-2rem))] flex-col overflow-hidden border-e border-zinc-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900 lg:hidden"
        >
            <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-4 py-4 dark:border-zinc-800">
                <a href="{{ $homeRoute }}" wire:navigate @click="mobileNavOpen = false" class="flex min-w-0 items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-700 text-white shadow-sm">
                        <x-app-logo-icon class="size-5 fill-current" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-base font-semibold text-zinc-950 dark:text-white">LibraryApp</span>
                        <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $canSeeDashboard ? 'Bibliotekos valdymas' : 'Mano biblioteka' }}
                        </span>
                    </span>
                </a>

                <button
                    type="button"
                    @click="mobileNavOpen = false"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-600 transition hover:border-zinc-300 hover:text-zinc-950 focus:outline-none focus:ring-2 focus:ring-emerald-600/30 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:text-white"
                    aria-label="{{ __('Uždaryti navigaciją') }}"
                >
                    <flux:icon.x-mark class="size-5" />
                </button>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
                <div>
                    <div class="px-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Pagrindinis') }}</div>
                    <div class="mt-2 grid gap-1">
                        @if($canSeeDashboard)
                            <a href="{{ route('dashboard') }}" wire:navigate @click="mobileNavOpen = false" @class([
                                'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                                'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('dashboard'),
                                'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('dashboard'),
                            ])>
                                <flux:icon.home class="size-5 shrink-0" />
                                <span>{{ __('Apžvalga') }}</span>
                            </a>
                        @endif

                        <a href="{{ route('notifications.index') }}" wire:navigate @click="mobileNavOpen = false" @class([
                            'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                            'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('notifications.*'),
                            'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('notifications.*'),
                        ])>
                            <flux:icon.bell class="size-5 shrink-0" />
                            <span>{{ __('Pranešimai') }}</span>
                            <span class="{{ $unreadNotificationsCount > 0 ? '' : 'hidden' }} ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-600 px-1.5 py-0.5 text-[11px] font-semibold text-white" data-notification-count="{{ $unreadNotificationsCount }}">
                                {{ $unreadNotificationsCount }}
                            </span>
                        </a>
                    </div>
                </div>

                <div>
                    <div class="px-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Biblioteka') }}</div>
                    <div class="mt-2 grid gap-1">
                        @if($isMember)
                            <a href="{{ route('account.dashboard') }}" wire:navigate @click="mobileNavOpen = false" @class([
                                'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                                'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('account.dashboard'),
                                'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('account.dashboard'),
                            ])>
                                <flux:icon.home class="size-5 shrink-0" />
                                <span>{{ __('Mano paskyra') }}</span>
                            </a>

                            <a href="{{ route('account.profile') }}" wire:navigate @click="mobileNavOpen = false" @class([
                                'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                                'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('account.profile'),
                                'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('account.profile'),
                            ])>
                                <flux:icon.user class="size-5 shrink-0" />
                                <span>{{ __('Profilis') }}</span>
                            </a>
                        @endif

                        <a href="{{ route('books.index') }}" wire:navigate @click="mobileNavOpen = false" @class([
                            'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                            'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('books.*'),
                            'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('books.*'),
                        ])>
                            <flux:icon.book-open-text class="size-5 shrink-0" />
                            <span>{{ __('Knygų katalogas') }}</span>
                        </a>

                        <a href="{{ route('reservations.index') }}" wire:navigate @click="mobileNavOpen = false" @class([
                            'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                            'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('reservations.*'),
                            'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('reservations.*'),
                        ])>
                            <flux:icon.folder-git-2 class="size-5 shrink-0" />
                            <span>{{ __('Rezervacijos') }}</span>
                        </a>

                        <a href="{{ route('loans.index') }}" wire:navigate @click="mobileNavOpen = false" @class([
                            'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                            'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('loans.*'),
                            'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('loans.*'),
                        ])>
                            <flux:icon.clipboard-document-list class="size-5 shrink-0" />
                            <span>{{ __('Išduotos knygos') }}</span>
                        </a>

                        @if($isMember)
                            <a href="{{ route('public.libraries.index') }}" wire:navigate @click="mobileNavOpen = false" @class([
                                'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                                'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('public.libraries.*'),
                                'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('public.libraries.*'),
                            ])>
                                <flux:icon.building-library class="size-5 shrink-0" />
                                <span>{{ __('Viešosios bibliotekos') }}</span>
                            </a>
                        @endif
                    </div>
                </div>

                @if($canManageLibrary)
                    <div>
                        <div class="px-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Valdymas') }}</div>
                        <div class="mt-2 grid gap-1">
                            <a href="{{ route('manage.users.index') }}" wire:navigate @click="mobileNavOpen = false" @class([
                                'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                                'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('manage.users.*'),
                                'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('manage.users.*'),
                            ])>
                                <flux:icon.users class="size-5 shrink-0" />
                                <span>{{ __('Vartotojai') }}</span>
                            </a>

                            <a href="{{ route('manage.book-copies.index') }}" wire:navigate @click="mobileNavOpen = false" @class([
                                'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                                'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('manage.book-copies.*'),
                                'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('manage.book-copies.*'),
                            ])>
                                <flux:icon.squares-plus class="size-5 shrink-0" />
                                <span>{{ __('Egzemplioriai') }}</span>
                            </a>

                            <a href="{{ route('manage.branches.index') }}" wire:navigate @click="mobileNavOpen = false" @class([
                                'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                                'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('manage.branches.*'),
                                'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('manage.branches.*'),
                            ])>
                                <flux:icon.building-library class="size-5 shrink-0" />
                                <span>{{ __('Filialai') }}</span>
                            </a>

                            <a href="{{ route('manage.locations.index') }}" wire:navigate @click="mobileNavOpen = false" @class([
                                'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                                'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('manage.locations.*'),
                                'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('manage.locations.*'),
                            ])>
                                <flux:icon.map-pin class="size-5 shrink-0" />
                                <span>{{ __('Vietos') }}</span>
                            </a>

                            @if($user?->isSuperAdmin())
                                <a href="{{ route('manage.libraries.index') }}" wire:navigate @click="mobileNavOpen = false" @class([
                                    'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                                    'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('manage.libraries.*'),
                                    'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('manage.libraries.*'),
                                ])>
                                    <flux:icon.building-library class="size-5 shrink-0" />
                                    <span>{{ __('Bibliotekos') }}</span>
                                </a>

                                <a href="{{ route('manage.categories.index') }}" wire:navigate @click="mobileNavOpen = false" @class([
                                    'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                                    'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('manage.categories.*'),
                                    'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('manage.categories.*'),
                                ])>
                                    <flux:icon.tag class="size-5 shrink-0" />
                                    <span>{{ __('Kategorijos') }}</span>
                                </a>

                                <a href="{{ route('manage.publishers.index') }}" wire:navigate @click="mobileNavOpen = false" @class([
                                    'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                                    'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('manage.publishers.*'),
                                    'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('manage.publishers.*'),
                                ])>
                                    <flux:icon.building-office-2 class="size-5 shrink-0" />
                                    <span>{{ __('Leidyklos') }}</span>
                                </a>

                                <a href="{{ route('manage.audit-logs.index') }}" wire:navigate @click="mobileNavOpen = false" @class([
                                    'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                                    'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('manage.audit-logs.*'),
                                    'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('manage.audit-logs.*'),
                                ])>
                                    <flux:icon.clipboard-document class="size-5 shrink-0" />
                                    <span>{{ __('Auditų žurnalas') }}</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                <div>
                    <div class="px-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Paskyra') }}</div>
                    <div class="mt-2 grid gap-1">
                        <a href="{{ route('profile.edit') }}" wire:navigate @click="mobileNavOpen = false" @class([
                            'flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition',
                            'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20' => request()->routeIs('profile.edit'),
                            'text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white' => ! request()->routeIs('profile.edit'),
                        ])>
                            <flux:icon.cog-6-tooth class="size-5 shrink-0" />
                            <span>{{ __('Nustatymai') }}</span>
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex h-11 w-full items-center gap-3 rounded-xl px-3 text-left text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800/70 dark:hover:text-white">
                                <flux:icon.arrow-right-start-on-rectangle class="size-5 shrink-0" />
                                <span>{{ __('Atsijungti') }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </nav>

            <div class="border-t border-zinc-200 px-4 py-4 dark:border-zinc-800">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-sm font-semibold text-white">
                        {{ $user?->initials() }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-zinc-950 dark:text-white">{{ $user?->name }}</span>
                        <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $user?->email }}</span>
                    </span>
                </div>
            </div>
        </aside>

        <flux:sidebar sticky class="max-lg:hidden w-72 border-e border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.header class="border-b border-zinc-200 px-4 py-5 dark:border-zinc-800">
                <a href="{{ $homeRoute }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3 rounded-2xl">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-700 text-white shadow-sm">
                        <x-app-logo-icon class="size-5 fill-current" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-[28px]/none text-base font-semibold text-zinc-950 dark:text-white">LibraryApp</span>
                        <span class="mt-1 block truncate text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $canSeeDashboard ? 'Bibliotekos valdymas' : 'Mano biblioteka' }}
                        </span>
                    </span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="px-3 py-4">
                <flux:sidebar.group :heading="__('Pagrindinis')" class="grid gap-1">
                    @if($canSeeDashboard)
                        <flux:sidebar.item icon="home" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Apžvalga') }}
                        </flux:sidebar.item>
                    @endif

                    <flux:sidebar.item icon="bell" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('notifications.index')" :current="request()->routeIs('notifications.*')" wire:navigate>
                        <span class="flex items-center gap-2">
                            <span>{{ __('Pranešimai') }}</span>
                            <span class="{{ $unreadNotificationsCount > 0 ? '' : 'hidden' }} ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-600 px-1.5 py-0.5 text-[11px] font-semibold text-white" data-notification-count="{{ $unreadNotificationsCount }}">
                                {{ $unreadNotificationsCount }}
                            </span>
                        </span>
                    </flux:sidebar.item>

                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Biblioteka')" class="mt-6 grid gap-1">
                    @if($isMember)
                        <flux:sidebar.item icon="home" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('account.dashboard')" :current="request()->routeIs('account.dashboard')" wire:navigate>
                            {{ __('Mano paskyra') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="user" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('account.profile')" :current="request()->routeIs('account.profile')" wire:navigate>
                            {{ __('Profilis') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="building-library" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('public.libraries.index')" :current="request()->routeIs('public.libraries.*')" wire:navigate>
                            {{ __('Viešosios bibliotekos') }}
                        </flux:sidebar.item>
                    @endif

                    <flux:sidebar.item icon="book-open-text" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('books.index')" :current="request()->routeIs('books.*')" wire:navigate>
                        {{ __('Knygų katalogas') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="clipboard-document-list" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('loans.index')" :current="request()->routeIs('loans.*')" wire:navigate>
                        {{ __('Išduotos knygos') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="folder-git-2" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('reservations.index')" :current="request()->routeIs('reservations.*')" wire:navigate>
                        {{ __('Rezervacijos') }}
                    </flux:sidebar.item>

                    @if($canManageLibrary)
                        <flux:sidebar.item icon="users" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('manage.users.index')" :current="request()->routeIs('manage.users.*')" wire:navigate>
                            {{ __('Vartotojai') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

                @if($canManageLibrary)
                    <flux:sidebar.group :heading="__('Valdymas')" class="mt-6 grid gap-1">
                        <flux:sidebar.item icon="squares-plus" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('manage.book-copies.index')" :current="request()->routeIs('manage.book-copies.*')" wire:navigate>
                            {{ __('Egzemplioriai') }}
                        </flux:sidebar.item>

                        @if($user?->isSuperAdmin())
                            <flux:sidebar.item icon="building-library" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('manage.libraries.index')" :current="request()->routeIs('manage.libraries.*')" wire:navigate>
                                {{ __('Bibliotekos') }}
                            </flux:sidebar.item>

                            <flux:sidebar.item icon="tag" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('manage.categories.index')" :current="request()->routeIs('manage.categories.*')" wire:navigate>
                                {{ __('Kategorijos') }}
                            </flux:sidebar.item>

                            <flux:sidebar.item icon="building-office-2" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('manage.publishers.index')" :current="request()->routeIs('manage.publishers.*')" wire:navigate>
                                {{ __('Leidyklos') }}
                            </flux:sidebar.item>
                        @endif

                        <flux:sidebar.item icon="building-library" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('manage.branches.index')" :current="request()->routeIs('manage.branches.*')" wire:navigate>
                            {{ __('Filialai') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="map-pin" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('manage.locations.index')" :current="request()->routeIs('manage.locations.*')" wire:navigate>
                            {{ __('Vietos') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Nustatymai')" class="mt-6 grid gap-1">
                        <flux:sidebar.item icon="cog-6-tooth" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>
                            {{ __('Sistemos nustatymai') }}
                        </flux:sidebar.item>

                        @if($user?->isSuperAdmin())
                            <flux:sidebar.item icon="clipboard-document" class="h-11 rounded-xl px-3 text-sm font-medium" :href="route('manage.audit-logs.index')" :current="request()->routeIs('manage.audit-logs.*')" wire:navigate>
                                {{ __('Auditų žurnalas') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="$user?->name" />
        </flux:sidebar>

        {{ $slot }}

        @auth
            <div class="pointer-events-none fixed right-4 top-4 z-50 flex flex-col gap-3" data-notification-toasts></div>
        @endauth

        @stack('scripts')
        @fluxScripts
    </body>
</html>











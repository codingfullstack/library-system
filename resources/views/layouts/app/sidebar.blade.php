<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
        @php
            $user = auth()->user();
            $canSeeDashboard = $user?->hasStaffAccess() ?? false;
            $canManageLibrary = $canSeeDashboard;
            $isMember = $user?->effectiveRole() === 'narys';
            $homeRoute = $canSeeDashboard ? route('dashboard') : ($isMember ? route('account.dashboard') : route('books.index'));
            $unreadNotificationsCount = $user ? $user->notifications()->whereNull('read_at')->count() : 0;
            $desktopSearchRoute = $canManageLibrary ? route('manage.search.index') : route('books.index');
            $desktopSearchName = $canManageLibrary ? 'q' : 'search';
        @endphp

        <flux:sidebar sticky collapsible="mobile" class="w-72 border-e border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.header class="border-b border-zinc-200 px-4 py-5 dark:border-zinc-800">
                <a href="{{ $homeRoute }}" wire:navigate class="flex items-center gap-3 rounded-2xl">
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
                            @if($unreadNotificationsCount > 0)
                                <span class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-600 px-1.5 py-0.5 text-[11px] font-semibold text-white">
                                    {{ $unreadNotificationsCount }}
                                </span>
                            @endif
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
                        {{ __('Knygos') }}
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

        @stack('scripts')
        @fluxScripts
    </body>
</html>











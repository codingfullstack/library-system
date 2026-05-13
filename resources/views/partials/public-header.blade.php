@php
    $user = auth()->user();
    $dashboardUrl = $user
        ? ($user->role === 'narys' ? route('account.dashboard') : route('dashboard'))
        : route('login');

    $navBase = 'flex h-full items-center px-1 transition hover:text-emerald-700 dark:hover:text-emerald-300';
    $navActive = 'border-b-4 border-emerald-600 text-emerald-700 dark:border-emerald-400 dark:text-emerald-300';
    $navInactive = 'text-slate-600 dark:text-zinc-300';
    $accountSubtitle = $user?->library?->name ?? $user?->email;
@endphp

<header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/95 shadow-sm shadow-slate-900/5 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/90 dark:shadow-none">
    <div class="mx-auto flex h-[88px] w-full max-w-[1780px] items-center justify-between px-8 lg:px-12 xl:px-16">
        <a href="{{ route('home') }}" class="flex items-center gap-3 text-emerald-700 dark:text-emerald-300" wire:navigate>
            <x-app-logo-icon class="size-12 fill-current" />
            <span class="leading-tight">
                <span class="block text-xl font-extrabold tracking-normal">Bibliotekos</span>
                <span class="block text-lg font-semibold text-slate-600 dark:text-zinc-300">sistema</span>
            </span>
        </a>

        <nav class="hidden h-full items-center gap-10 text-[15px] font-semibold lg:flex">
            <a href="{{ route('home') }}" class="{{ $navBase }} {{ request()->routeIs('home') ? $navActive : $navInactive }}" wire:navigate>Pradžia</a>
            <a href="{{ route('about') }}" class="{{ $navBase }} {{ request()->routeIs('about') ? $navActive : $navInactive }}" wire:navigate>Apie sistemą</a>
            <a href="{{ route('public.libraries.index') }}" class="{{ $navBase }} {{ request()->routeIs('public.libraries.*') ? $navActive : $navInactive }}" wire:navigate>Bibliotekos</a>
            @auth
                <a href="{{ $dashboardUrl }}" class="{{ $navBase }} {{ request()->routeIs('dashboard', 'account.dashboard') ? $navActive : $navInactive }}" wire:navigate>Apžvalga</a>
            @endauth
            <a href="{{ route('help') }}" class="{{ $navBase }} {{ request()->routeIs('help') ? $navActive : $navInactive }}" wire:navigate>Pagalba</a>
        </nav>

        <div class="flex items-center gap-4">
            @auth
                <flux:dropdown position="bottom" align="end">
                    <button
                        type="button"
                        class="inline-flex h-14 min-w-0 items-center gap-3 rounded-xl bg-white px-2.5 py-2 text-left transition hover:bg-slate-50 dark:bg-zinc-950 dark:hover:bg-zinc-900"
                        aria-label="Atidaryti paskyros meniu"
                    >
                        <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-base font-bold text-white shadow-sm shadow-emerald-700/20">
                            {{ $user->initials() }}
                        </span>

                        <span class="min-w-0 leading-tight">
                            <span class="block max-w-44 truncate text-[17px] font-semibold text-slate-950 dark:text-white lg:max-w-48">
                                {{ $user->name }}
                            </span>
                            <span class="block max-w-44 truncate text-[15px] font-normal text-slate-500 dark:text-zinc-400 lg:max-w-48">
                                {{ $accountSubtitle }}
                            </span>
                        </span>

                        <flux:icon.chevron-up-down class="size-4 shrink-0 text-slate-400 dark:text-zinc-500" />
                    </button>

                    <flux:menu class="min-w-56">
                        <flux:menu.item :href="$dashboardUrl" icon="home" wire:navigate>
                            Apžvalga
                        </flux:menu.item>

                        <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>
                            Nustatymai
                        </flux:menu.item>

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item
                                as="button"
                                type="submit"
                                icon="arrow-right-start-on-rectangle"
                                class="w-full cursor-pointer"
                            >
                                Atsijungti
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @else
                <a
                    href="{{ $dashboardUrl }}"
                    class="inline-flex h-12 items-center gap-2 rounded-lg border border-slate-200 bg-white px-5 text-[15px] font-semibold text-slate-700 shadow-sm transition hover:border-emerald-200 hover:text-emerald-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-emerald-700 dark:hover:text-emerald-300"
                    wire:navigate
                >
                    Prisijungti
                    <flux:icon.arrow-right class="size-4" />
                </a>
            @endauth
        </div>
    </div>
</header>








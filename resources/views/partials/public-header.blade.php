@php
    $user = auth()->user();
    $dashboardUrl = $user
        ? ($user->role === 'narys' ? route('account.dashboard') : route('dashboard'))
        : route('login');

    $navBase = 'flex h-full items-center px-1 transition hover:text-emerald-700 dark:hover:text-emerald-300';
    $navActive = 'border-b-4 border-emerald-600 text-emerald-700 dark:border-emerald-400 dark:text-emerald-300';
    $navInactive = 'text-slate-600 dark:text-zinc-300';
    $mobileNavBase = 'flex items-center justify-between rounded-lg px-4 py-3 text-base font-semibold transition hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-300';
    $mobileNavActive = 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
    $mobileNavInactive = 'text-slate-700 dark:text-zinc-200';
    $accountSubtitle = $user?->library?->name ?? $user?->email;
@endphp

<header
    x-data="{ mobileNavOpen: false }"
    x-on:keydown.escape.window="mobileNavOpen = false"
    class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/95 shadow-sm shadow-slate-900/5 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/90 dark:shadow-none"
>
    <div class="relative mx-auto flex h-20 w-full max-w-[1780px] items-center justify-between gap-3 px-4 sm:px-6 lg:h-[88px] lg:px-12 xl:px-16">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3 text-emerald-700 dark:text-emerald-300" wire:navigate>
            <x-app-logo-icon class="size-10 shrink-0 fill-current sm:size-12" />
            <span class="leading-tight">
                <span class="block text-lg font-extrabold tracking-normal sm:text-xl">Bibliotekos</span>
                <span class="block text-base font-semibold text-slate-600 sm:text-lg dark:text-zinc-300">sistema</span>
            </span>
        </a>

        <nav class="hidden h-full items-center gap-10 text-[15px] font-semibold lg:flex">
            <a href="{{ route('home') }}" class="{{ $navBase }} {{ request()->routeIs('home') ? $navActive : $navInactive }}" wire:navigate>Pradžia</a>
            <a href="{{ route('about') }}" class="{{ $navBase }} {{ request()->routeIs('about') ? $navActive : $navInactive }}" wire:navigate>Apie sistemą</a>
            <a href="{{ route('public.libraries.index') }}" class="{{ $navBase }} {{ request()->routeIs('public.libraries.*') ? $navActive : $navInactive }}" wire:navigate>Bibliotekos</a>
            @auth
                <a href="{{ $dashboardUrl }}" class="{{ $navBase }} {{ request()->routeIs('dashboard', 'account.dashboard') ? $navActive : $navInactive }}" wire:navigate>Apžvalga</a>
            @endauth
            <a href="{{ route('contacts') }}" class="{{ $navBase }} {{ request()->routeIs('contacts') ? $navActive : $navInactive }}" wire:navigate>Kontaktai</a>
            <a href="{{ route('help') }}" class="{{ $navBase }} {{ request()->routeIs('help') ? $navActive : $navInactive }}" wire:navigate>Pagalba</a>
        </nav>

        <div class="flex shrink-0 items-center gap-2 sm:gap-4">
            @auth
                <flux:dropdown position="bottom" align="end">
                    <button
                        type="button"
                        class="hidden h-14 min-w-0 items-center gap-3 rounded-xl bg-white px-2.5 py-2 text-left transition hover:bg-slate-50 sm:inline-flex dark:bg-zinc-950 dark:hover:bg-zinc-900"
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
                    class="hidden h-12 items-center gap-2 rounded-lg border border-slate-200 bg-white px-5 text-[15px] font-semibold text-slate-700 shadow-sm transition hover:border-emerald-200 hover:text-emerald-700 sm:inline-flex dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-emerald-700 dark:hover:text-emerald-300"
                    wire:navigate
                >
                    Prisijungti
                    <flux:icon.arrow-right class="size-4" />
                </a>
            @endauth

            <button
                type="button"
                class="inline-flex size-11 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-emerald-200 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600/25 lg:hidden dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-emerald-700 dark:hover:text-emerald-300"
                x-on:click="mobileNavOpen = ! mobileNavOpen"
                x-bind:aria-expanded="mobileNavOpen.toString()"
                aria-controls="public-mobile-nav"
                aria-label="Atidaryti navigacijos meniu"
            >
                <flux:icon.x-mark x-show="mobileNavOpen" x-cloak class="size-6" />
                <span x-show="! mobileNavOpen" class="flex w-5 flex-col gap-1.5" aria-hidden="true">
                    <span class="h-0.5 rounded-full bg-current"></span>
                    <span class="h-0.5 rounded-full bg-current"></span>
                    <span class="h-0.5 rounded-full bg-current"></span>
                </span>
            </button>
        </div>

        <div
            x-show="mobileNavOpen"
            x-cloak
            x-transition.opacity
            x-on:click="mobileNavOpen = false"
            class="fixed inset-0 top-20 z-40 bg-slate-950/10 backdrop-blur-[1px] lg:hidden dark:bg-black/30"
            aria-hidden="true"
        ></div>

        <nav
            id="public-mobile-nav"
            x-show="mobileNavOpen"
            x-cloak
            x-transition.origin.top.right
            x-on:click.outside="mobileNavOpen = false"
            class="absolute inset-x-4 top-[calc(100%+0.5rem)] z-50 overflow-hidden rounded-xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-900/15 lg:hidden dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/30"
            aria-label="Mobilioji navigacija"
        >
            <a href="{{ route('home') }}" class="{{ $mobileNavBase }} {{ request()->routeIs('home') ? $mobileNavActive : $mobileNavInactive }}" x-on:click="mobileNavOpen = false" wire:navigate>
                Pagrindinis
            </a>
            <a href="{{ route('public.libraries.index') }}" class="{{ $mobileNavBase }} {{ request()->routeIs('public.libraries.*') ? $mobileNavActive : $mobileNavInactive }}" x-on:click="mobileNavOpen = false" wire:navigate>
                Bibliotekos
            </a>
            <a href="{{ route('about') }}" class="{{ $mobileNavBase }} {{ request()->routeIs('about') ? $mobileNavActive : $mobileNavInactive }}" x-on:click="mobileNavOpen = false" wire:navigate>
                Apie
            </a>
            <a href="{{ route('contacts') }}" class="{{ $mobileNavBase }} {{ request()->routeIs('contacts') ? $mobileNavActive : $mobileNavInactive }}" x-on:click="mobileNavOpen = false" wire:navigate>
                Kontaktai
            </a>
            <a href="{{ route('help') }}" class="{{ $mobileNavBase }} {{ request()->routeIs('help') ? $mobileNavActive : $mobileNavInactive }}" x-on:click="mobileNavOpen = false" wire:navigate>
                Pagalba
            </a>
            <a href="{{ $dashboardUrl }}" class="mt-2 flex items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 py-3 text-base font-bold text-white shadow-sm shadow-emerald-700/20 transition hover:bg-emerald-600" x-on:click="mobileNavOpen = false" wire:navigate>
                @auth
                    Apžvalga
                @else
                    Prisijungti
                @endauth
                <flux:icon.arrow-right class="size-5" />
            </a>
        </nav>
    </div>
</header>








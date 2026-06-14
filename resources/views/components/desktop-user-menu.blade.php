@php
    $user = auth()->user();
    $unreadNotificationsCount = $user?->unreadNotifications()->count() ?? 0;
    $displayLibrary = $user?->library?->name;
@endphp

<div {{ $attributes->class('border-t border-zinc-200 px-3 py-3 dark:border-zinc-800') }}>
    <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
        <button
            type="button"
            class="flex w-full items-center gap-3 rounded-2xl px-2 py-2 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-800/70"
            data-test="sidebar-menu-button"
            @click="open = ! open"
            :aria-expanded="open.toString()"
        >
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-sm font-semibold text-white">
                {{ $user?->initials() }}
            </span>

            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-zinc-950 dark:text-white">{{ $user?->name }}</span>
                <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $displayLibrary ?? $user?->email }}
                </span>
            </span>

            <flux:icon.chevron-up-down class="size-4 text-zinc-400" />
        </button>

        <div
            x-cloak
            x-show="open"
            x-transition.origin.bottom.left
            @click.outside="open = false"
            class="overflow-hidden rounded-xl border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
            style="position: absolute; bottom: calc(100% + 0.5rem); left: 0; z-index: 50; width: 100%; min-width: 16rem;"
        >
            <a
                href="{{ route('notifications.index') }}"
                wire:navigate
                class="flex items-center gap-3 px-4 py-2.5 text-sm text-zinc-700 hover:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-800"
                @click="open = false"
            >
                <flux:icon.bell class="size-5 text-zinc-400" />
                <span class="flex min-w-0 flex-1 items-center gap-2">
                    <span>{{ __('Pranešimai') }}</span>
                    <span class="{{ $unreadNotificationsCount > 0 ? '' : 'hidden' }} ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-[11px] font-semibold text-white" data-notification-count="{{ $unreadNotificationsCount }}">
                        {{ $unreadNotificationsCount }}
                    </span>
                </span>
            </a>

            @if($user?->role === 'narys')
                <a
                    href="{{ route('public.libraries.index') }}"
                    wire:navigate
                    class="flex items-center gap-3 px-4 py-2.5 text-sm text-zinc-700 hover:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    @click="open = false"
                >
                    <flux:icon.building-library class="size-5 text-zinc-400" />
                    {{ __('Viešosios bibliotekos') }}
                </a>
            @endif

            <a
                href="{{ route('profile.edit') }}"
                wire:navigate
                class="flex items-center gap-3 px-4 py-2.5 text-sm text-zinc-700 hover:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-800"
                @click="open = false"
            >
                <flux:icon.cog-6-tooth class="size-5 text-zinc-400" />
                {{ __('Nustatymai') }}
            </a>

            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <button
                    type="submit"
                    class="flex w-full cursor-pointer items-center gap-3 px-4 py-2.5 text-left text-sm text-zinc-700 hover:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    data-test="logout-button"
                >
                    <flux:icon.arrow-right-start-on-rectangle class="size-5 text-zinc-400" />
                    {{ __('Atsijungti') }}
                </button>
            </form>
        </div>
    </div>
</div>

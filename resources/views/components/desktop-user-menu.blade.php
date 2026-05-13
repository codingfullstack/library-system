@php
    $user = auth()->user();
    $unreadNotificationsCount = $user?->notifications()->whereNull('read_at')->count() ?? 0;
    $displayLibrary = $user?->library?->name;
@endphp

<div class="border-t border-zinc-200 px-3 py-3 dark:border-zinc-800">
    <flux:dropdown position="top" align="start">
        <button
            type="button"
            class="flex w-full items-center gap-3 rounded-2xl px-2 py-2 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-800/70"
            data-test="sidebar-menu-button"
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

        <flux:menu class="min-w-64">
            <flux:menu.item :href="route('notifications.index')" icon="bell" wire:navigate>
                <span class="flex items-center gap-2">
                    <span>{{ __('Pranešimai') }}</span>
                    @if($unreadNotificationsCount > 0)
                        <span class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-[11px] font-semibold text-white">
                            {{ $unreadNotificationsCount }}
                        </span>
                    @endif
                </span>
            </flux:menu.item>

            @if($user?->role === 'narys')
                <flux:menu.item :href="route('public.libraries.index')" icon="building-library" wire:navigate>
                    {{ __('Viešosios bibliotekos') }}
                </flux:menu.item>
            @endif

            <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>
                {{ __('Nustatymai') }}
            </flux:menu.item>

            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                >
                    {{ __('Atsijungti') }}
                </flux:menu.item>
            </form>
        </flux:menu>
    </flux:dropdown>
</div>








<div class="app-card">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Aktyvios rezervacijos</p>
            <div class="mt-2 text-3xl font-bold tracking-tight text-zinc-950 dark:text-white">
                {{ $activeCount }}
            </div>
        </div>

        <x-ui.status-badge
            :status="$activeCount > 0 ? 'reserved' : 'cancelled'"
            :label="$activeCount > 0 ? 'Eilė aktyvi' : 'Nėra eilės'"
        />
    </div>

    @if ($firstActiveReservation)
        <div class="mt-5 rounded-lg border border-sky-200 bg-sky-50 p-4 dark:border-sky-900/40 dark:bg-sky-500/10">
            <p class="app-label text-sky-700 dark:text-sky-300">Pirmas eilėje</p>
            <p class="mt-2 text-base font-semibold text-zinc-950 dark:text-white">
                {{ $firstActiveReservation->user?->name ?: '-' }}
            </p>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                {{ $firstActiveReservation->user?->membership_number ?: ($firstActiveReservation->user?->email ?: '-') }}
            </p>
            <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                Rezervuota: {{ $firstActiveReservation->reserved_at?->format('Y-m-d H:i') ?: '-' }}
            </p>
        </div>
    @endif
</div>

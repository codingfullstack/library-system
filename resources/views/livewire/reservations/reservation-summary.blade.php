<div class="app-card">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Aktyvios rezervacijos</p>
            <div class="mt-2 text-3xl font-bold tracking-tight text-zinc-950 dark:text-white">
                {{ $activeCount }}
            </div>
        </div>

        <x-ui.status-badge
            :status="$activeCount > 0 ? 'rezervuota' : 'atšaukta'"
            :label="$activeCount > 0 ? 'Yra aktyvių' : 'Nėra aktyvių'"
        />
    </div>

    @if ($readyReservations->isNotEmpty())
        <div class="mt-5 space-y-3">
            <p class="app-label text-violet-700 dark:text-violet-300">Paruošta atsiėmimui</p>
            @foreach($readyReservations as $reservation)
                <div class="rounded-lg border border-violet-200 bg-violet-50 p-4 dark:border-violet-900/40 dark:bg-violet-500/10">
                    <p class="text-base font-semibold text-zinc-950 dark:text-white">
                        {{ $reservation->user?->name ?: '-' }}
                    </p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ $reservation->user?->membership_number ?: ($reservation->user?->email ?: '-') }}
                    </p>
                    <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                        Atsiimti iki: {{ $reservation->expires_at?->format('Y-m-d H:i') ?: '-' }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif

    @if ($waitingReservations->isNotEmpty())
        <div class="mt-5 rounded-lg border border-sky-200 bg-sky-50 p-4 dark:border-sky-900/40 dark:bg-sky-500/10">
            <p class="app-label text-sky-700 dark:text-sky-300">Laukiančių eilė</p>
            <div class="mt-3 space-y-3">
                @foreach($waitingReservations as $reservation)
                    <div class="text-sm">
                        <span class="font-semibold text-zinc-950 dark:text-white">{{ $loop->iteration }}. {{ $reservation->user?->name ?: '-' }}</span>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ $reservation->user?->membership_number ?: ($reservation->user?->email ?: '') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

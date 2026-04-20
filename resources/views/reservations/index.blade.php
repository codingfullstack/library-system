<x-layouts::app :title="__('Rezervacija')">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-zinc-800 dark:text-zinc-100 leading-tight">
                Rezervacijos
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                    <div class="font-semibold mb-2">Nepavyko išsaugoti:</div>

                    <ul class="list-disc pl-5 space-y-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-zinc-900 shadow-sm rounded-2xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                <div class="px-6 py-5 border-b border-zinc-200 dark:border-zinc-800">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                        Visos rezervacijos
                    </h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        Čia matysi aktyvias, įvykdytas ir atšauktas rezervacijas.
                    </p>
                </div>

                @if ($reservations->count())
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                        Knyga
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                        Narys
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                        Statusas
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                        Rezervuota
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                        Galioja iki
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                        Veiksmai
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @foreach ($reservations as $reservation)
                                    @php
                                        $isActive = $reservation->status === 'reserved'
                                            && is_null($reservation->fulfilled_at)
                                            && is_null($reservation->cancelled_at)
                                            && (is_null($reservation->expires_at) || $reservation->expires_at->isFuture());

                                        $isCancelled = $reservation->status === 'cancelled' || ! is_null($reservation->cancelled_at);
                                        $isFulfilled = $reservation->status === 'fulfilled' || ! is_null($reservation->fulfilled_at);
                                    @endphp

                                    <tr class="hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40 transition">
                                        <td class="px-6 py-4 align-top">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $reservation->book->title ?? '—' }}
                                            </div>
                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                ID: {{ $reservation->book_id }}
                                            </div>
                                        </td>

                                        <td class="px-6 py-4 align-top">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $reservation->user->name ?? '—' }}
                                            </div>
                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $reservation->user->membership_number ?? $reservation->user->email ?? '—' }}
                                            </div>
                                        </td>

                                        <td class="px-6 py-4 align-top">
                                            @if ($isActive)
                                                <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                                                    Aktyvi
                                                </span>
                                            @elseif ($isFulfilled)
                                                <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">
                                                    Įvykdyta
                                                </span>
                                            @elseif ($isCancelled)
                                                <span class="inline-flex items-center rounded-full bg-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-700">
                                                    Atšaukta
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-yellow-100 px-3 py-1 text-xs font-semibold text-yellow-700">
                                                    {{ $reservation->status }}
                                                </span>
                                            @endif
                                        </td>

                                        <td class="px-6 py-4 align-top text-sm text-zinc-700 dark:text-zinc-300">
                                            {{ $reservation->reserved_at?->format('Y-m-d H:i') ?? '—' }}
                                        </td>

                                        <td class="px-6 py-4 align-top text-sm text-zinc-700 dark:text-zinc-300">
                                            {{ $reservation->expires_at?->format('Y-m-d H:i') ?? 'Be termino' }}
                                        </td>

                                        <td class="px-6 py-4 align-top text-right">
                                            @if ($isActive)
                                                <form method="POST" action="{{ route('reservations.cancel', $reservation) }}" class="inline-block">
                                                    @csrf
                                                    @method('PATCH')

                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 transition"
                                                    >
                                                        Atšaukti
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-sm text-zinc-400">
                                                    —
                                                </span>
                                            @endif
                                        </td>
                                    </tr>

                                    @if ($reservation->notes)
                                        <tr>
                                            <td colspan="6" class="px-6 pb-4 pt-0">
                                                <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/50 px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                                    <span class="font-semibold">Pastabos:</span> {{ $reservation->notes }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-zinc-200 dark:border-zinc-800">
                        {{ $reservations->links() }}
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <div class="text-lg font-semibold text-zinc-800 dark:text-zinc-100">
                            Rezervacijų dar nėra
                        </div>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            Kai vartotojai pradės rezervuoti knygas, jos atsiras šiame sąraše.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts::app>
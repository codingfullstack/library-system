<x-layouts::app :title="$book->title">
    <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="mb-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">
                        Knygos informacija
                    </p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">
                        {{ $book->title }}
                    </h1>

                    @if($book->subtitle)
                        <p class="mt-2 text-base text-zinc-600 dark:text-zinc-400">
                            {{ $book->subtitle }}
                        </p>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('books.index') }}"
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                        ← Atgal į sąrašą
                    </a>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-500/10 dark:text-red-300">
                <div class="font-semibold">Nepavyko išsaugoti:</div>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $authUser = auth()->user();

            $activeReservations = $book->reservations
                ->filter(fn ($reservation) => $reservation->isActive())
                ->sortBy('reserved_at')
                ->values();

            $firstActiveReservation = $activeReservations->first();
        @endphp

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-6 py-5 dark:border-zinc-800">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                            Bendra informacija
                        </h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            Pagrindiniai knygos bibliografiniai duomenys.
                        </p>
                    </div>

                    <div class="px-6 py-6">
                        <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Autoriai
                                </dt>
                                <dd class="mt-2 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $book->authors->pluck('name')->join(', ') ?: '-' }}
                                </dd>
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Kategorija
                                </dt>
                                <dd class="mt-2 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $book->category?->name ?: '-' }}
                                </dd>
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Leidykla
                                </dt>
                                <dd class="mt-2 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $book->publisher?->name ?: '-' }}
                                </dd>
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    ISBN
                                </dt>
                                <dd class="mt-2 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $book->isbn ?: '-' }}
                                </dd>
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Metai
                                </dt>
                                <dd class="mt-2 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $book->publication_year ?: '-' }}
                                </dd>
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Kalba
                                </dt>
                                <dd class="mt-2 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $book->language ?: '-' }}
                                </dd>
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Puslapiai
                                </dt>
                                <dd class="mt-2 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $book->page_count ?: '-' }}
                                </dd>
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Leidimas
                                </dt>
                                <dd class="mt-2 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $book->edition ?? '-' }}
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-6 rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-950/40">
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">
                                Aprašymas
                            </h3>

                            <div class="mt-3 text-sm leading-7 text-zinc-700 dark:text-zinc-300">
                                {{ $book->description ?: 'Aprašymo nėra.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-1 space-y-6">
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">
                        Kopijos bibliotekoje
                    </p>
                    <div class="mt-3 flex items-end gap-3">
                        <span class="text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">
                            {{ $book->copies_count }}
                        </span>
                        <span class="mb-1 text-sm text-zinc-500 dark:text-zinc-400">
                            vnt.
                        </span>
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">
                                Aktyvios rezervacijos
                            </p>
                            <div class="mt-2 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">
                                {{ $activeReservations->count() }}
                            </div>
                        </div>

                        @if ($activeReservations->count() > 0)
                            <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                                Eilė aktyvi
                            </span>
                        @else
                            <span class="inline-flex rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                Nėra eilės
                            </span>
                        @endif
                    </div>

                    @if ($firstActiveReservation)
                        <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/40 dark:bg-blue-500/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">
                                Pirmas eilėje
                            </p>
                            <p class="mt-2 text-base font-semibold text-zinc-900 dark:text-white">
                                {{ $firstActiveReservation->user?->name ?: '—' }}
                            </p>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $firstActiveReservation->user?->membership_number ?: ($firstActiveReservation->user?->email ?: '—') }}
                            </p>
                            <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                                Rezervuota: {{ $firstActiveReservation->reserved_at?->format('Y-m-d H:i') ?: '-' }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="xl:col-span-1">
                <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-6 py-5 dark:border-zinc-800">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                            Nauja rezervacija
                        </h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            @if ($authUser?->role === 'member')
                                Rezervuok šią knygą sau.
                            @else
                                Sukurk rezervaciją pasirinktam nariui.
                            @endif
                        </p>
                    </div>

                    <div class="px-6 py-6">
                        @if ($authUser && $authUser->role === 'member')
                            <form method="POST" action="{{ route('reservations.store') }}" class="space-y-5">
                                @csrf

                                <input type="hidden" name="book_id" value="{{ $book->id }}">

                                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/40">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        Rezervuos
                                    </p>
                                    <p class="mt-2 text-sm font-semibold text-zinc-900 dark:text-white">
                                        {{ $authUser->name }}
                                    </p>
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $authUser->membership_number ?: $authUser->email }}
                                    </p>
                                </div>

                                <div>
                                    <label for="member_notes" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                        Pastabos
                                    </label>
                                    <textarea
                                        name="notes"
                                        id="member_notes"
                                        rows="4"
                                        class="mt-2 block w-full rounded-xl border-zinc-300 bg-white text-zinc-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                        placeholder="Papildoma informacija..."
                                    >{{ old('notes') }}</textarea>
                                </div>

                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                                >
                                    Rezervuoti sau
                                </button>
                            </form>
                        @elseif ($authUser && in_array($authUser->role, ['admin', 'staff'], true))
                            <form method="POST" action="{{ route('reservations.store') }}" class="space-y-5">
                                @csrf

                                <input type="hidden" name="book_id" value="{{ $book->id }}">

                                <div>
                                    <label for="user_id" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                        Nario ID
                                    </label>
                                    <input
                                        type="number"
                                        name="user_id"
                                        id="user_id"
                                        value="{{ old('user_id') }}"
                                        class="mt-2 block w-full rounded-xl border-zinc-300 bg-white text-zinc-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                        placeholder="Pvz. 12"
                                        required
                                    >
                                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                        Testavimui gali įrašyti nario ID. Vėliau galima bus pakeisti į paiešką.
                                    </p>
                                </div>

                                <div>
                                    <label for="expires_at" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                        Galioja iki
                                    </label>
                                    <input
                                        type="datetime-local"
                                        name="expires_at"
                                        id="expires_at"
                                        value="{{ old('expires_at') }}"
                                        class="mt-2 block w-full rounded-xl border-zinc-300 bg-white text-zinc-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    >
                                </div>

                                <div>
                                    <label for="staff_notes" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                        Pastabos
                                    </label>
                                    <textarea
                                        name="notes"
                                        id="staff_notes"
                                        rows="4"
                                        class="mt-2 block w-full rounded-xl border-zinc-300 bg-white text-zinc-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                        placeholder="Papildoma informacija..."
                                    >{{ old('notes') }}</textarea>
                                </div>

                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                                >
                                    Sukurti rezervaciją nariui
                                </button>
                            </form>
                        @else
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-4 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-300">
                                Norint rezervuoti knygą, reikia prisijungti.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="xl:col-span-2">
                <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-6 py-5 dark:border-zinc-800">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                                    Rezervacijų istorija
                                </h2>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                    Visos šios knygos rezervacijos, naujausios viršuje.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-6">
                        @if($book->reservations->isEmpty())
                            <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 px-6 py-10 text-center dark:border-zinc-700 dark:bg-zinc-800/40">
                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">
                                    Rezervacijų nėra
                                </h3>
                                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    Ši knyga dar nebuvo rezervuota.
                                </p>
                            </div>
                        @else
                            <div class="space-y-4">
                                @foreach($book->reservations->sortByDesc('reserved_at') as $reservation)
                                    @php
                                        $isActive = $reservation->isActive();
                                        $isFulfilled = $reservation->status === 'fulfilled' || ! is_null($reservation->fulfilled_at);
                                        $isCancelled = $reservation->status === 'cancelled' || ! is_null($reservation->cancelled_at);

                                        $statusClasses = match (true) {
                                            $isActive => 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
                                            $isFulfilled => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                                            $isCancelled => 'bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                                            default => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                        };

                                        $statusLabel = match (true) {
                                            $isActive => 'Aktyvi',
                                            $isFulfilled => 'Įvykdyta',
                                            $isCancelled => 'Atšaukta',
                                            default => $reservation->status,
                                        };
                                    @endphp

                                    <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-800">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <h3 class="text-base font-semibold text-zinc-900 dark:text-white">
                                                    {{ $reservation->user?->name ?: 'Nežinomas narys' }}
                                                </h3>
                                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                                    {{ $reservation->user?->membership_number ?: ($reservation->user?->email ?: '—') }}
                                                </p>
                                            </div>

                                            <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </div>

                                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/50">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                    Rezervuota
                                                </p>
                                                <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">
                                                    {{ $reservation->reserved_at?->format('Y-m-d H:i') ?: '-' }}
                                                </p>
                                            </div>

                                            <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/50">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                    Galioja iki
                                                </p>
                                                <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">
                                                    {{ $reservation->expires_at?->format('Y-m-d H:i') ?: 'Be termino' }}
                                                </p>
                                            </div>
                                        </div>

                                        @if ($reservation->notes)
                                            <div class="mt-4 rounded-xl bg-zinc-50 p-3 text-sm text-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-300">
                                                <span class="font-medium text-zinc-900 dark:text-white">Pastabos:</span>
                                                {{ $reservation->notes }}
                                            </div>
                                        @endif

                                        @if ($isActive && $authUser && in_array($authUser->role, ['admin', 'staff'], true))
                                            <div class="mt-4 flex justify-end">
                                                <form method="POST" action="{{ route('reservations.cancel', $reservation) }}">
                                                    @csrf
                                                    @method('PATCH')

                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700"
                                                    >
                                                        Atšaukti rezervaciją
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-6 py-5 dark:border-zinc-800">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                            Knygos kopijos
                        </h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            Visos matomos jūsų bibliotekos kopijos ir jų būsena.
                        </p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-6">
                @if($book->bookCopies->isEmpty())
                    <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">
                            Kopijų nėra
                        </h3>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            Šioje bibliotekoje šiuo metu nėra matomų šios knygos kopijų.
                        </p>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                        @foreach($book->bookCopies as $copy)
                            @php
                                $status = $copy->status ?: 'nežinoma';

                                $statusClasses = match ($status) {
                                    'available' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                                    'loaned' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                    'reserved' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
                                    'lost' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
                                    'damaged' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
                                    'maintenance' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300',
                                    default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                                };

                                $firstReservationForBook = $activeReservations->first();
                            @endphp

                            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <a href="{{ route('book-copies.show', $copy->id) }}">
                                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">
                                                    Kopija #{{ $copy->id }}
                                                </h3>
                                            </a>
                                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                                Inventoriaus kodas: {{ $copy->inventory_code ?: '-' }}
                                            </p>
                                        </div>

                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                            {{ $status }}
                                        </span>
                                    </div>
                                </div>

                                <div class="space-y-4 px-5 py-5">
                                    <div class="grid grid-cols-1 gap-3">
                                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/50">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                Būklė
                                            </p>
                                            <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">
                                                {{ $copy->condition_status ?? '-' }}
                                            </p>
                                        </div>

                                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/50">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                Filialas
                                            </p>
                                            <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">
                                                {{ $copy->branch?->name ?: '-' }}
                                            </p>
                                        </div>

                                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/50">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                Vieta
                                            </p>
                                            <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">
                                                @if($copy->location)
                                                    {{ $copy->location->name ?: '-' }}
                                                    /
                                                    {{ $copy->location->room ?: '-' }}
                                                    /
                                                    {{ $copy->location->shelf ?: '-' }}
                                                @else
                                                    -
                                                @endif
                                            </p>
                                        </div>

                                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/50">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                Aktyvi paskola
                                            </p>
                                            <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">
                                                {{ $copy->activeLoan ? 'taip' : 'ne' }}
                                            </p>

                                            @if($copy->activeLoan)
                                                <div class="mt-3 space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                                                    <p>
                                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">Vartotojas:</span>
                                                        {{ $copy->activeLoan->user?->name ?: '-' }}
                                                    </p>
                                                    <p>
                                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">El. paštas:</span>
                                                        {{ $copy->activeLoan->user?->email ?: '-' }}
                                                    </p>
                                                    <p>
                                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">Grąžinti iki:</span>
                                                        {{ $copy->activeLoan->due_at ? \Illuminate\Support\Carbon::parse($copy->activeLoan->due_at)->format('Y-m-d') : 'Be limito' }}
                                                    </p>
                                                    <p>
                                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">Vėluoja:</span>
                                                        <span class="{{ $copy->activeLoan->is_overdue ? 'text-red-600 font-semibold' : 'text-zinc-700 dark:text-zinc-300' }}">
                                                            {{ $copy->activeLoan->is_overdue ? 'Taip' : 'Ne' }}
                                                        </span>
                                                    </p>
                                                </div>
                                            @endif
                                        </div>

                                        @if ($firstReservationForBook)
                                            <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-900/40 dark:bg-blue-500/10">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">
                                                    Pirmas rezervacijoje
                                                </p>
                                                <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">
                                                    {{ $firstReservationForBook->user?->name ?: '-' }}
                                                </p>
                                                <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">
                                                    Staff gali išduoti ir kitam nariui, bet prioritetas priklauso šiam vartotojui.
                                                </p>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="pt-2">
                                        <button type="button"
                                            class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:cursor-not-allowed disabled:opacity-50"
                                            {{ $copy->activeLoan ? 'disabled' : '' }}>
                                            {{ $copy->activeLoan ? 'Šiuo metu neišduodama' : 'Išduoti' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts::app>
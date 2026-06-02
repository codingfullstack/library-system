<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-slate-950 antialiased dark:bg-zinc-950 dark:text-white">
        @include('partials.public-header')

        <main class="mx-auto w-full max-w-[1780px] px-8 py-10 lg:px-12 xl:px-16">
            <div class="mb-8">
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Katalogas</p>
                <h1 class="mt-2 text-4xl font-extrabold tracking-normal text-slate-950 dark:text-white">Knygu katalogas</h1>
                <p class="mt-3 max-w-2xl text-base leading-7 text-slate-600 dark:text-zinc-300">Naršykite viesose bibliotekose prieinamas knygas.</p>
            </div>

            <form method="GET" action="{{ route('books.index') }}" class="mb-6 grid gap-3 md:grid-cols-[minmax(240px,1fr)_180px_160px_auto]">
                <input name="search" value="{{ request('search') }}" placeholder="Ieskoti pagal pavadinima, autoriu ar ISBN" class="app-input">
                <select name="category_id" class="app-input">
                    <option value="">Kategorija</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="availability" class="app-input">
                    <option value="">Busena</option>
                    <option value="laisva" @selected(request('availability') === 'laisva')>Yra laisvu</option>
                    <option value="unavailable" @selected(request('availability') === 'unavailable')>Laisvu nera</option>
                </select>
                <button class="app-button-primary" type="submit">Filtruoti</button>
            </form>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse($books as $book)
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h2 class="text-xl font-bold text-slate-950 dark:text-white">
                            <a href="{{ route('books.show', $book) }}" class="hover:text-emerald-700 dark:hover:text-emerald-300">{{ $book->title }}</a>
                        </h2>
                        <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">{{ $book->authors->pluck('name')->join(', ') ?: 'Autorius nenurodytas' }}</p>
                        <p class="mt-4 line-clamp-3 text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ $book->description ?: 'Aprasymo nera.' }}</p>
                        <div class="mt-4 text-sm font-semibold text-emerald-700 dark:text-emerald-300">Laisvi egzemplioriai: {{ $book->available_copies_count }}</div>
                    </article>
                @empty
                    <x-ui.empty-state title="Knygu nerasta" description="Pabandykite pakeisti paieska arba filtrus." />
                @endforelse
            </div>

            <div class="mt-6">{{ $books->links() }}</div>
        </main>

        @include('partials.public-footer')
        @fluxScripts
    </body>
</html>

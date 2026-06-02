<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-slate-950 antialiased dark:bg-zinc-950 dark:text-white">
        @include('partials.public-header')

        <main class="mx-auto w-full max-w-[1780px] px-8 py-10 lg:px-12 xl:px-16">
            <a href="{{ route('books.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-600 dark:text-emerald-300">Atgal i kataloga</a>

            <div class="mt-6 grid gap-8 lg:grid-cols-[220px_minmax(0,1fr)]">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-100 dark:border-zinc-800 dark:bg-zinc-900">
                    @if($book->cover_image_url)
                        <img src="{{ $book->cover_image_url }}" alt="{{ $book->title }}" class="aspect-[3/4] w-full object-cover">
                    @else
                        <div class="flex aspect-[3/4] items-center justify-center text-4xl font-bold uppercase text-slate-400">
                            {{ str($book->title)->substr(0, 2)->upper() }}
                        </div>
                    @endif
                </div>

                <section>
                    <h1 class="text-4xl font-extrabold tracking-normal text-slate-950 dark:text-white">{{ $book->title }}</h1>
                    @if($book->subtitle)
                        <p class="mt-2 text-xl text-slate-600 dark:text-zinc-300">{{ $book->subtitle }}</p>
                    @endif

                    <dl class="mt-6 grid gap-3 md:grid-cols-2">
                        @foreach([
                            'Autoriai' => $book->authors->pluck('name')->join(', ') ?: '-',
                            'Kategorijos' => $book->categories->pluck('name')->join(', ') ?: '-',
                            'Leidykla' => $book->publisher?->name ?: '-',
                            'ISBN' => $book->isbn ?: '-',
                            'Metai' => $book->publication_year ?: '-',
                            'Kalba' => $book->language ?: '-',
                        ] as $label => $value)
                            <div class="rounded-lg bg-slate-50 p-4 ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ $label }}</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-950 dark:text-white">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h2 class="text-xl font-bold text-slate-950 dark:text-white">Aprasymas</h2>
                        <p class="mt-3 leading-7 text-slate-600 dark:text-zinc-300">{{ $book->description ?: 'Aprasymo nera.' }}</p>
                    </section>
                </section>
            </div>
        </main>

        @include('partials.public-footer')
        @fluxScripts
    </body>
</html>

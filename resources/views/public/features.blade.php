<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-slate-950 antialiased dark:bg-zinc-950 dark:text-white">
        @include('partials.public-header')
        <main class="mx-auto w-full max-w-[1780px] px-8 py-12 lg:px-12 xl:px-16">
            <h1 class="text-4xl font-extrabold tracking-normal text-slate-950 dark:text-white">Funkcijos</h1>
            <div class="mt-8 grid gap-4 md:grid-cols-3">
                @foreach(['Viesas knygu katalogas', 'Rezervaciju valdymas', 'Biblioteku administravimas'] as $feature)
                    <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h2 class="text-xl font-bold text-slate-950 dark:text-white">{{ $feature }}</h2>
                    </article>
                @endforeach
            </div>
        </main>
        @include('partials.public-footer')
        @fluxScripts
    </body>
</html>

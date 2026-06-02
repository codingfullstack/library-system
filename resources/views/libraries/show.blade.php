<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-slate-950 antialiased dark:bg-zinc-950 dark:text-white">
        @include('partials.public-header')

        <main class="mx-auto w-full max-w-[1780px] px-8 py-10 lg:px-12 xl:px-16">
            <div class="mb-8">
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Biblioteka</p>
                <h1 class="mt-2 text-4xl font-extrabold tracking-normal text-slate-950 dark:text-white">{{ $library->name }}</h1>
                <p class="mt-3 max-w-2xl text-base leading-7 text-slate-600 dark:text-zinc-300">
                    {{ collect([$library->address, $library->city])->filter()->join(', ') ?: $library->code }}
                </p>
            </div>

            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm font-semibold text-slate-500 dark:text-zinc-400">Kodas</div>
                    <div class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ $library->code }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm font-semibold text-slate-500 dark:text-zinc-400">Knygu egzemplioriai</div>
                    <div class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ number_format($library->book_copies_count) }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm font-semibold text-slate-500 dark:text-zinc-400">Nariai</div>
                    <div class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ number_format($library->memberships_count) }}</div>
                </div>
            </section>

            <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-2xl font-bold text-slate-950 dark:text-white">Kontaktai</h2>
                <dl class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-sm font-semibold text-slate-500 dark:text-zinc-400">El. pastas</dt>
                        <dd class="mt-1 text-slate-800 dark:text-zinc-200">{{ $library->email ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold text-slate-500 dark:text-zinc-400">Telefonas</dt>
                        <dd class="mt-1 text-slate-800 dark:text-zinc-200">{{ $library->phone ?: '-' }}</dd>
                    </div>
                </dl>
            </section>
        </main>

        @include('partials.public-footer')
        @fluxScripts
    </body>
</html>

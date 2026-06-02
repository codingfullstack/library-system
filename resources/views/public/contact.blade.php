<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-slate-950 antialiased dark:bg-zinc-950 dark:text-white">
        @include('partials.public-header')
        <main class="mx-auto w-full max-w-[1780px] px-8 py-12 lg:px-12 xl:px-16">
            <h1 class="text-4xl font-extrabold tracking-normal text-slate-950 dark:text-white">Kontaktai</h1>
            <p class="mt-4 max-w-3xl text-lg leading-8 text-slate-600 dark:text-zinc-300">
                Del bibliotekos sistemos, katalogo arba prisijungimo prie viesu biblioteku kreipkites i savo bibliotekos administracija.
            </p>
        </main>
        @include('partials.public-footer')
        @fluxScripts
    </body>
</html>

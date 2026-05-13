@php
    $homeUrl = route('home');

    $dashboardUrl = route('login');
    if (auth()->check()) {
        $dashboardUrl = auth()->user()->role === 'narys'
            ? route('account.dashboard')
            : route('dashboard');
    }

    $secondaryUrl = $secondaryUrl ?? null;
    if (($secondaryTarget ?? null) === 'dashboard') {
        $secondaryUrl = $dashboardUrl;
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head', ['title' => $code.' - '.$title])
</head>
<body class="min-h-full bg-zinc-50 font-sans text-slate-950 antialiased dark:bg-zinc-950 dark:text-white">
    <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid w-full max-w-[1280px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950 lg:grid-cols-[minmax(0,0.52fr)_minmax(0,0.48fr)]">
            <section class="relative hidden min-h-[650px] overflow-hidden bg-[#f5fbf8] px-12 py-10 lg:block dark:bg-[#07110f]">
                <div class="absolute inset-y-0 right-[-118px] w-[292px] rounded-r-[100%] bg-white dark:bg-zinc-950"></div>

                <a href="{{ $homeUrl }}" class="relative z-10 inline-flex items-center gap-3 text-slate-950 dark:text-white">
                    <flux:icon.book-open-text class="size-10 text-emerald-700 dark:text-emerald-300" />
                    <span class="leading-tight">
                        <span class="block text-lg font-extrabold tracking-normal">Bibliotekos</span>
                        <span class="block text-lg font-extrabold tracking-normal">sistema</span>
                    </span>
                </a>

                <div class="relative z-10 mt-28 max-w-[330px]">
                    <h1 class="text-[31px] font-extrabold leading-[1.14] tracking-normal text-slate-950 dark:text-white">
                        Jungia žinias,<br>
                        įkvepia idėjas
                    </h1>
                    <p class="mt-5 text-[15px] leading-7 text-slate-600 dark:text-zinc-300">
                        Bibliotekos sistema padeda lengvai valdyti knygas, narius ir visus bibliotekos procesus.
                    </p>
                </div>

                <img
                    src="{{ asset('images/auth-illustration.png') }}"
                    alt=""
                    class="pointer-events-none absolute bottom-0 left-10 z-10 h-auto w-[470px] select-none"
                >
            </section>

            <section class="flex min-h-[650px] items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
                <div class="w-full max-w-[430px] rounded-xl border border-slate-200 bg-white px-8 py-10 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:px-12 sm:py-12">
                    <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                        @if ($icon === 'document-x')
                            <span class="relative inline-flex">
                                <flux:icon.document-text class="size-9" />
                                <span class="absolute -bottom-1 -right-2 flex size-6 items-center justify-center rounded-full bg-emerald-700 text-white ring-4 ring-emerald-50 dark:bg-emerald-400 dark:text-emerald-950 dark:ring-zinc-900">
                                    <flux:icon.x-circle class="size-4" />
                                </span>
                            </span>
                        @elseif ($icon === 'lock')
                            <flux:icon.lock-closed class="size-8" />
                        @else
                            <flux:icon.exclamation-triangle class="size-8" />
                        @endif
                    </div>

                    <p class="mt-7 text-[64px] font-extrabold leading-none tracking-normal text-emerald-700 dark:text-emerald-300">
                        {{ $code }}
                    </p>
                    <h2 class="mt-4 text-[28px] font-extrabold leading-tight tracking-normal text-slate-950 dark:text-white">
                        {{ $title }}
                    </h2>
                    <p class="mx-auto mt-4 max-w-[300px] text-[15px] leading-7 text-slate-600 dark:text-zinc-300">
                        {{ $description }}
                    </p>

                    <div class="mt-8 space-y-4">
                        @if (($primaryAction ?? null) === 'back')
                            <button
                                type="button"
                                onclick="history.back()"
                                class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-emerald-700 px-5 text-base font-bold text-white shadow-lg shadow-emerald-900/15 transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:bg-emerald-500 dark:text-emerald-950 dark:hover:bg-emerald-400 dark:focus:ring-offset-zinc-900"
                            >
                                <flux:icon.arrow-left class="size-5" />
                                {{ $primaryLabel }}
                            </button>
                        @elseif (($primaryAction ?? null) === 'reload')
                            <button
                                type="button"
                                onclick="window.location.reload()"
                                class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-emerald-700 px-5 text-base font-bold text-white shadow-lg shadow-emerald-900/15 transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:bg-emerald-500 dark:text-emerald-950 dark:hover:bg-emerald-400 dark:focus:ring-offset-zinc-900"
                            >
                                <flux:icon.arrow-path class="size-5" />
                                {{ $primaryLabel }}
                            </button>
                        @else
                            <a
                                href="{{ $primaryUrl ?? $homeUrl }}"
                                class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-emerald-700 px-5 text-base font-bold text-white shadow-lg shadow-emerald-900/15 transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:bg-emerald-500 dark:text-emerald-950 dark:hover:bg-emerald-400 dark:focus:ring-offset-zinc-900"
                            >
                                <flux:icon.home class="size-5" />
                                {{ $primaryLabel }}
                            </a>
                        @endif

                        @if (($secondaryAction ?? null) === 'back')
                            <button
                                type="button"
                                onclick="history.back()"
                                class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-5 text-base font-bold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800 dark:focus:ring-offset-zinc-900"
                            >
                                <flux:icon.arrow-left class="size-5" />
                                {{ $secondaryLabel }}
                            </button>
                        @else
                            <a
                                href="{{ $secondaryUrl ?? $homeUrl }}"
                                class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-5 text-base font-bold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800 dark:focus:ring-offset-zinc-900"
                            >
                                @if (($secondaryIcon ?? null) === 'dashboard')
                                    <flux:icon.layout-grid class="size-5" />
                                @else
                                    <flux:icon.home class="size-5" />
                                @endif
                                {{ $secondaryLabel }}
                            </a>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </main>

    @fluxScripts
</body>
</html>








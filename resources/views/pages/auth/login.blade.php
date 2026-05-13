<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <title>Prisijungti</title>
    </head>
    <body class="min-h-screen bg-white text-slate-950 antialiased dark:bg-zinc-950 dark:text-white">
        <div class="min-h-screen bg-white dark:bg-zinc-950">
            <div class="mx-auto grid min-h-screen w-full max-w-[1780px] overflow-hidden bg-white lg:grid-cols-[minmax(0,0.5fr)_minmax(0,0.5fr)] dark:bg-zinc-950">
                <x-partials.auth-brand-panel
                    title="Sveiki sugrįžę!"
                    description="Prisijunkite prie savo paskyros ir tęskite darbą su bibliotekos sistema."
                    middle-title="Aptarnaukite skaitytojus"
                    middle-text="Valdykite išdavimus, grąžinimus ir rezervacijas vienoje vietoje."
                    bottom-text="Gaukite svarbią informaciją apie savo bibliotekos veiklą."
                />

                <main class="flex min-h-screen flex-col bg-white px-8 py-10 lg:px-12 xl:px-16 dark:bg-zinc-950">
                    <a href="{{ route('home') }}" class="mb-8 inline-flex items-center gap-3 self-start text-emerald-700 lg:hidden dark:text-emerald-300" wire:navigate>
                        <x-app-logo-icon class="size-10 fill-current" />
                        <span class="leading-tight">
                            <span class="block text-lg font-extrabold tracking-normal">Bibliotekos</span>
                            <span class="block text-base font-semibold text-slate-700 dark:text-zinc-300">sistema</span>
                        </span>
                    </a>

                    <div class="flex flex-1 items-center justify-center">
                        <section class="w-full max-w-[560px] rounded-xl border border-slate-200 bg-white px-10 py-12 shadow-[0_24px_70px_rgba(15,23,42,0.10)] sm:px-16 dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="text-center">
                                <x-app-logo-icon class="mx-auto size-12 fill-current text-emerald-700 dark:text-emerald-300" />
                                <h1 class="mt-7 text-3xl font-extrabold tracking-normal text-slate-950 dark:text-white">Prisijunkite prie paskyros</h1>
                                <p class="mt-3 text-base font-medium text-slate-500 dark:text-zinc-400">Įveskite savo el. pašto adresą ir slaptažodį</p>
                            </div>

                            <x-auth-session-status class="mt-8 text-center" :status="session('status')" />

                            <form method="POST" action="{{ route('login.store') }}" class="mt-9 space-y-6" x-data="{ showPassword: false }">
                                @csrf

                                <div>
                                    <label for="email" class="block text-sm font-bold text-slate-700 dark:text-zinc-200">El. pašto adresas</label>
                                    <div class="mt-2 flex h-14 items-center gap-3 rounded-lg border border-slate-300 bg-white px-4 shadow-sm transition focus-within:border-emerald-600 focus-within:ring-4 focus-within:ring-emerald-600/10 dark:border-zinc-700 dark:bg-zinc-950">
                                        <input id="email" name="email" value="{{ old('email') }}" type="email" required autofocus autocomplete="email" placeholder="jusu@elpastas.lt" class="min-w-0 flex-1 border-0 bg-transparent text-base text-slate-900 outline-none placeholder:text-slate-400 focus:ring-0 dark:text-white dark:placeholder:text-zinc-500">
                                        <flux:icon.envelope class="size-5 shrink-0 text-slate-400 dark:text-zinc-500" />
                                    </div>
                                    @error('email')
                                        <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <div class="flex items-center justify-between gap-4">
                                        <label for="password" class="block text-sm font-bold text-slate-700 dark:text-zinc-200">Slaptažodis</label>
                                        @if (Route::has('password.request'))
                                            <a href="{{ route('password.request') }}" class="text-sm font-bold text-emerald-700 hover:text-emerald-600 dark:text-emerald-300 dark:hover:text-emerald-200" wire:navigate>Pamiršote slaptažodį?</a>
                                        @endif
                                    </div>
                                    <div class="mt-2 flex h-14 items-center gap-3 rounded-lg border border-slate-300 bg-white px-4 shadow-sm transition focus-within:border-emerald-600 focus-within:ring-4 focus-within:ring-emerald-600/10 dark:border-zinc-700 dark:bg-zinc-950">
                                        <input id="password" name="password" x-bind:type="showPassword ? 'text' : 'password'" required autocomplete="current-password" placeholder="Įveskite slaptažodį" class="min-w-0 flex-1 border-0 bg-transparent text-base text-slate-900 outline-none placeholder:text-slate-400 focus:ring-0 dark:text-white dark:placeholder:text-zinc-500">
                                        <button type="button" class="inline-flex size-8 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:text-zinc-500 dark:hover:bg-zinc-800 dark:hover:text-zinc-200" x-on:click="showPassword = ! showPassword" aria-label="Rodyti arba slėpti slaptažodį">
                                            <flux:icon.eye class="size-5" />
                                        </button>
                                    </div>
                                    @error('password')
                                        <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <label class="inline-flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-zinc-300">
                                    <input name="remember" type="checkbox" @checked(old('remember')) class="size-5 rounded border-slate-300 text-emerald-700 shadow-sm focus:ring-emerald-600 dark:border-zinc-700 dark:bg-zinc-950">
                                    Prisiminti mane
                                </label>

                                <button type="submit" class="flex h-14 w-full items-center justify-center rounded-lg bg-emerald-700 text-base font-bold text-white shadow-lg shadow-emerald-700/20 transition hover:bg-emerald-600 focus:outline-none focus:ring-4 focus:ring-emerald-600/20" data-test="login-button">Prisijungti</button>
                            </form>

                            @if (Route::has('register'))
                                <p class="mt-8 text-center text-base text-slate-500 dark:text-zinc-400">
                                    Neturite paskyros?
                                    <a href="{{ route('register') }}" class="font-bold text-emerald-700 hover:text-emerald-600 dark:text-emerald-300 dark:hover:text-emerald-200" wire:navigate>Registruotis</a>
                                </p>
                            @endif
                        </section>
                    </div>

                    <footer class="mt-8 text-center text-sm text-slate-500 dark:text-zinc-400">
                        <p>© 2026 Bibliotekos sistema. Visos teisės saugomos.</p>
                        <nav class="mt-4 flex flex-wrap justify-center gap-x-5 gap-y-2 text-emerald-700 dark:text-emerald-300">
                            <a href="{{ route('help') }}" class="font-medium hover:text-emerald-600 dark:hover:text-emerald-200" wire:navigate>Privatumo politika</a>
                            <span class="text-slate-300 dark:text-zinc-700">|</span>
                            <a href="{{ route('help') }}" class="font-medium hover:text-emerald-600 dark:hover:text-emerald-200" wire:navigate>Naudojimo sąlygos</a>
                            <span class="text-slate-300 dark:text-zinc-700">|</span>
                            <a href="{{ route('help') }}" class="font-medium hover:text-emerald-600 dark:hover:text-emerald-200" wire:navigate>Kontaktai</a>
                        </nav>
                    </footer>
                </main>
            </div>
        </div>

        @fluxScripts
    </body>
</html>








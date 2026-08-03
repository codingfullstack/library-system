<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.public-head')
    </head>
    <body class="min-h-screen bg-white text-slate-950 antialiased dark:bg-zinc-950 dark:text-white">
        @include('partials.public-header')

        <main class="mx-auto w-full max-w-[1780px] px-8 py-10 lg:px-12 xl:px-16">
            <div class="mb-8">
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Katalogas</p>
                <h1 class="mt-2 text-4xl font-extrabold tracking-normal text-slate-950 dark:text-white">Viešosios bibliotekos</h1>
                <p class="mt-3 max-w-2xl text-base leading-7 text-slate-600 dark:text-zinc-300">Pasirinkite biblioteką, prie kurios norite prisijungti kaip skaitytojas.</p>
            </div>

            @if(session('success'))
                <x-ui.alert>{{ session('success') }}</x-ui.alert>
            @endif

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse($libraries as $library)
                    @php
                        $user = auth()->user();
                        $isMember = $user?->role === 'narys';
                        $membershipStatus = $isMember ? ($library->membership_status ?? 'none') : 'none';
                        $canJoin = $isMember && (bool) ($library->can_join ?? false);
                    @endphp

                    <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-start gap-4">
                            <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                <flux:icon.building-library class="size-6" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-lg font-bold text-zinc-950 dark:text-white">
                                    @auth
                                        <a href="{{ route('public.libraries.show', ['library' => $library->slug]) }}" class="transition hover:text-emerald-700 dark:hover:text-emerald-300">
                                            {{ $library->name }}
                                        </a>
                                    @else
                                        {{ $library->name }}
                                    @endauth
                                </h2>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ collect([$library->address, $library->city])->filter()->join(', ') ?: $library->code }}</p>
                            </div>
                        </div>

                        <div class="mt-5">
                            @auth
                                @if($isMember)
                                    @if($membershipStatus === 'active')
                                        <span class="block rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                            Jau prisijungta
                                        </span>
                                    @elseif($membershipStatus === 'inactive')
                                        <div class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                                            <p class="font-semibold">Narystė neaktyvi</p>
                                            <p class="mt-1">Jūsų narystė šioje bibliotekoje yra deaktyvuota. Dėl atkūrimo kreipkitės į bibliotekos administratorių.</p>
                                        </div>
                                    @elseif($canJoin)
                                        <form method="POST" action="{{ route('libraries.join', $library) }}">
                                            @csrf
                                            <button type="submit" class="app-button-primary w-full">Prisijungti prie bibliotekos</button>
                                        </form>
                                    @else
                                        <span class="block rounded-xl bg-zinc-50 px-4 py-3 text-sm font-semibold text-zinc-500 dark:bg-zinc-950 dark:text-zinc-400">
                                            Prisijungimas negalimas
                                        </span>
                                    @endif
                                @else
                                    <span class="block rounded-xl bg-zinc-50 px-4 py-3 text-sm font-semibold text-zinc-500 dark:bg-zinc-950 dark:text-zinc-400">Darbo paskyros prie viešųjų bibliotekų nejungiamos.</span>
                                @endif
                            @else
                                <a href="{{ route('login') }}" class="app-button-primary w-full">Prisijungti</a>
                            @endauth
                        </div>
                    </article>
                @empty
                    <x-ui.empty-state title="Viešųjų bibliotekų nėra" description="Šiuo metu nėra aktyvių viešųjų bibliotekų." />
                @endforelse
            </div>
        </main>

    </body>
</html>








@props([
    'title',
    'description',
    'middleTitle',
    'middleText',
    'bottomText',
])

<aside class="auth-brand-panel relative hidden min-h-screen overflow-hidden bg-[#f5fbf8] lg:block dark:bg-[#07110f]">
    <div class="auth-brand-curve pointer-events-none absolute inset-y-0 right-[-180px] z-20 w-[236px] rounded-r-[100%] bg-white dark:bg-zinc-950"></div>

    <a href="{{ route('home') }}" class="auth-brand-logo absolute left-10 top-8 z-30 inline-flex items-center gap-3 text-slate-950 dark:text-white" wire:navigate>
        <flux:icon.book-open-text class="size-10 text-emerald-700 dark:text-emerald-300" />
        <span class="leading-tight">
            <span class="block text-lg font-extrabold tracking-normal">Bibliotekos</span>
            <span class="block text-lg font-extrabold tracking-normal">sistema</span>
        </span>
    </a>

    <section class="auth-brand-content absolute left-[104px] top-[214px] z-30 w-[430px]">
        <h1 class="text-[36px] font-extrabold leading-[1.12] tracking-normal text-slate-950 dark:text-white">{{ $title }}</h1>
        <p class="mt-5 w-[360px] text-base leading-7 text-slate-600 dark:text-zinc-300">{{ $description }}</p>

        <div class="auth-brand-features mt-9 w-[390px] space-y-8">
            <div class="grid grid-cols-[56px_minmax(0,1fr)] gap-5">
                <span class="inline-flex size-12 items-center justify-center rounded-full bg-emerald-100/70 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                    <flux:icon.book-open-text class="size-6" />
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Tvarkykite katalogą</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-zinc-300">Lengvai pridėkite ir redaguokite knygas, kopijas ir autorius.</p>
                </div>
            </div>

            <div class="grid grid-cols-[56px_minmax(0,1fr)] gap-5">
                <span class="inline-flex size-12 items-center justify-center rounded-full bg-emerald-100/70 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                    <flux:icon.users class="size-6" />
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ $middleTitle }}</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ $middleText }}</p>
                </div>
            </div>

            <div class="grid grid-cols-[56px_minmax(0,1fr)] gap-5">
                <span class="inline-flex size-12 items-center justify-center rounded-full bg-emerald-100/70 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                    <flux:icon.arrow-trending-up class="size-6" />
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Stebėkite statistiką</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ $bottomText }}</p>
                </div>
            </div>
        </div>
    </section>

    <img
        src="{{ asset('images/auth-illustration.png') }}"
        alt=""
        class="auth-brand-image pointer-events-none absolute bottom-0 left-6 z-10 h-[306px] w-[646px] select-none"
    >
</aside>








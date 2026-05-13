@props([
    'eyebrow' => null,
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div>
        @if($eyebrow)
            <p class="text-sm font-semibold text-teal-700 dark:text-teal-300">{{ $eyebrow }}</p>
        @endif

        <h1 class="mt-1 text-3xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ $title }}</h1>

        @if($description)
            <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>








@props([
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-dashed border-zinc-300 bg-zinc-50 px-6 py-12 text-center dark:border-zinc-700 dark:bg-zinc-900/60']) }}>
    <h3 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ $title }}</h3>

    @if($description)
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $description }}</p>
    @endif
</div>








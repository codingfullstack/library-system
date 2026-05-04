@props([
    'type' => 'success',
])

@php
    $classes = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-500/10 dark:text-emerald-300',
        'error' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-500/10 dark:text-red-300',
        'info' => 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/50 dark:bg-sky-500/10 dark:text-sky-300',
    ][$type] ?? 'border-zinc-200 bg-zinc-50 text-zinc-800 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300';
@endphp

<div {{ $attributes->merge(['class' => 'mb-6 rounded-lg border px-4 py-3 text-sm '.$classes]) }}>
    {{ $slot }}
</div>

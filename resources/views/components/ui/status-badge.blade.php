@props([
    'status',
    'label' => null,
])

@php
    $classes = match ($status) {
        'rezervuota', 'aktyvi' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
        'įvykdyta', 'grąžinta', 'laisva' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
        'atšaukta' => 'bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
        'pasibaigusi', 'vėluoja', 'išduota' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
        'prarasta', 'sugadinta', 'nurašyta' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300',
        default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    };
@endphp

<span {{ $attributes->merge(['class' => 'app-status '.$classes]) }}>
    {{ $label ?? $status }}
</span>








@props([
    'icon' => 'info',
    'class' => 'size-5',
])

@switch($icon)
    @case('check_circle')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
            <circle cx="12" cy="12" r="9" />
        </svg>
        @break

    @case('warning')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 17h.01" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0z" />
        </svg>
        @break

    @case('error')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="12" cy="12" r="9" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 17h.01" />
        </svg>
        @break

    @case('book')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v14" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 18a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 18a2 2 0 0 0-2-2h-5a2 2 0 0 0-2 2V6a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2z" />
        </svg>
        @break

    @case('bookmark')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18l-6-4-6 4z" />
        </svg>
        @break

    @case('schedule')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="12" cy="12" r="9" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2" />
        </svg>
        @break

    @default
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="12" cy="12" r="9" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 11v5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8h.01" />
        </svg>
@endswitch

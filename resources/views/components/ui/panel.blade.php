@props([
    'title' => null,
    'description' => null,
    'bodyClass' => 'app-panel-body',
])

<section {{ $attributes->except('body-class')->merge(['class' => 'app-panel']) }}>
    @if($title || $description || isset($header))
        <div class="app-panel-header">
            @isset($header)
                {{ $header }}
            @else
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ $title }}</h2>
                @if($description)
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $description }}</p>
                @endif
            @endisset
        </div>
    @endif

    <div class="{{ $bodyClass }}">
        {{ $slot }}
    </div>
</section>








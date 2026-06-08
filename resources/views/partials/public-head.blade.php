<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

{!! seo($SEOData ?? $seo ?? app(\App\Services\SeoService::class)->make(title: $title ?? null, canonicalUrl: url()->current())) !!}

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@vite(['resources/css/public.css', 'resources/js/public.js'])

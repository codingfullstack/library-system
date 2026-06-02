<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

@php
    $seoData = $seo ?? app(\App\Services\SeoService::class)->make(
        title: $seoTitle ?? $title ?? null,
        description: $seoDescription ?? null,
        canonicalUrl: $canonicalUrl ?? url()->current(),
        type: $seoType ?? 'website',
    );

    $seoTitle = $seoData->title;
    $seoDescription = $seoData->description;
    $canonicalUrl = $seoData->canonicalUrl ?? url()->current();
    $seoType = $seoData->type;
@endphp

@auth
    <meta name="auth-user-id" content="{{ auth()->id() }}" />
    <meta name="notifications-recent-url" content="{{ route('notifications.recent') }}" />
    <meta name="notifications-unread-count-url" content="{{ route('notifications.unread-count') }}" />
    <meta name="notifications-mark-all-read-url" content="{{ route('notifications.mark-all-read') }}" />
    <meta name="notifications-mark-read-url-template" content="{{ url('/notifications/__ID__/read') }}" />
    <meta name="broadcasting-auth-url" content="{{ url('/broadcasting/auth') }}" />
@endauth

<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<link rel="canonical" href="{{ $canonicalUrl }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:type" content="{{ $seoType }}">

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance









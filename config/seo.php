<?php

declare(strict_types=1);

return [
    'title' => env('SEO_DEFAULT_TITLE', config('app.name', 'LibraryApp')),
    'description' => env(
        'SEO_DEFAULT_DESCRIPTION',
        'Bibliotekos sistema knygu paieskai, rezervacijoms ir biblioteku katalogui.'
    ),
    'sitemap_path' => public_path('sitemap.xml'),
    'news_model' => env('SEO_NEWS_MODEL'),
    'news_route' => env('SEO_NEWS_ROUTE', 'news.show'),
];

<?php

declare(strict_types=1);

use GuzzleHttp\RequestOptions;
use Spatie\Sitemap\Crawler\Profile;

return [
    'guzzle_options' => [
        RequestOptions::COOKIES => true,
        RequestOptions::CONNECT_TIMEOUT => 10,
        RequestOptions::TIMEOUT => 10,
        RequestOptions::ALLOW_REDIRECTS => false,
    ],

    'execute_javascript' => false,

    'chrome_binary_path' => null,

    'crawl_profile' => Profile::class,
];

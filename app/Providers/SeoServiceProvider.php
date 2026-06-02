<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\SeoService;
use Illuminate\Support\ServiceProvider;

final class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SeoService::class);
    }
}

<?php

namespace Database\Seeders\Support;

use RuntimeException;

trait GuardsDemoSeeding
{
    private function guardDemoSeedingIsAllowed(): void
    {
        if (! config('demo.enabled', false)) {
            throw new RuntimeException(static::class.' is demo-only and is disabled for the current environment.');
        }

        if (app()->environment('production') && ! env('DEMO_DATA_ENABLED', false)) {
            throw new RuntimeException(static::class.' is demo-only and cannot run in production unless DEMO_DATA_ENABLED=true.');
        }
    }
}

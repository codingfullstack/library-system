<?php

namespace Database\Seeders\Support;

use RuntimeException;

trait GuardsDemoSeeding
{
    private function guardDemoSeedingIsAllowed(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(static::class.' is demo-only and cannot run in production.');
        }
    }
}

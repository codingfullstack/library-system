<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $this->useIsolatedCompiledViewsPath();

        return parent::createApplication();
    }

    private function useIsolatedCompiledViewsPath(): void
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'testing') {
            return;
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'library-system'
            .DIRECTORY_SEPARATOR.'testing'
            .DIRECTORY_SEPARATOR.'views'
            .DIRECTORY_SEPARATOR.md5(dirname(__DIR__))
            .DIRECTORY_SEPARATOR.getmypid();

        if (! is_dir($path) && ! @mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new \RuntimeException("Unable to create isolated compiled views directory [{$path}].");
        }

        $_SERVER['VIEW_COMPILED_PATH'] = $path;
        $_ENV['VIEW_COMPILED_PATH'] = $path;
        putenv('VIEW_COMPILED_PATH='.$path);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}






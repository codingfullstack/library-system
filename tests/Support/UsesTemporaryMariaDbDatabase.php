<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;

trait UsesTemporaryMariaDbDatabase
{
    private ?string $temporaryDatabaseName = null;

    private ?string $originalDefaultConnection = null;

    private ?string $originalDatabaseName = null;

    protected function setUpTemporaryMariaDbDatabase(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('This integration test requires the mysql connection against MariaDB/MySQL.');
        }

        $this->originalDefaultConnection = config('database.default');
        $this->originalDatabaseName = config('database.connections.mysql.database');
        $this->temporaryDatabaseName = 'library_system_it_'.getmypid().'_'.bin2hex(random_bytes(4));

        $pdo = $this->serverPdo();
        $database = str_replace('`', '``', $this->temporaryDatabaseName);
        $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->temporaryDatabaseName,
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');

        $this->assertSafeTemporaryDatabaseForFreshMigration();

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDownTemporaryMariaDbDatabase(): void
    {
        DB::disconnect('mysql');

        if ($this->temporaryDatabaseName) {
            $database = str_replace('`', '``', $this->temporaryDatabaseName);
            $this->serverPdo()->exec("DROP DATABASE IF EXISTS `{$database}`");
        }

        if ($this->originalDefaultConnection) {
            config([
                'database.default' => $this->originalDefaultConnection,
                'database.connections.mysql.database' => $this->originalDatabaseName,
            ]);

            DB::purge('mysql');
        }
    }

    protected function temporaryDatabaseEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => (string) $this->temporaryDatabaseName,
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];
    }

    protected function dropReadyCompletenessCheck(): void
    {
        try {
            DB::statement('ALTER TABLE reservations DROP CONSTRAINT reservations_ready_completeness_check');
        } catch (\Throwable) {
            // MariaDB/MySQL version differences are not relevant when the constraint is already absent.
        }
    }

    private function serverPdo(): PDO
    {
        $connection = config('database.connections.mysql');
        $host = $connection['host'] ?? '127.0.0.1';
        $port = $connection['port'] ?? '3306';
        $username = $connection['username'] ?? 'root';
        $password = $connection['password'] ?? '';

        return new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    private function assertSafeTemporaryDatabaseForFreshMigration(): void
    {
        $database = (string) config('database.connections.mysql.database');

        if (! str_starts_with($database, 'library_system_it_')) {
            throw new \RuntimeException("Refusing to run migrate:fresh against non-temporary database [{$database}].");
        }
    }
}

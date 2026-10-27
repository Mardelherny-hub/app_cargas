<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';

        if ($app->configurationIsCached()) {
            throw new RuntimeException('Tests require uncached configuration.');
        }

        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            if (! $app->environment('testing')) {
                throw new RuntimeException('Tests require APP_ENV=testing.');
            }

            $name = $app['config']->get('database.default');
            self::assertSafeDatabase($app['config']->get("database.connections.{$name}", []));
        });

        $app->afterBootstrapping(RegisterProviders::class, function ($app) {
            $app['events']->listen(\Illuminate\Database\Events\MigrationStarted::class, function ($event) {
                $file = (new \ReflectionClass($event->migration))->getFileName();
                if ($event->method !== 'up'
                    || basename($file) !== '2026_08_16_232000_fix_client_tax_document_types.php') {
                    return;
                }

                foreach ([
                    ['alpha2_code' => 'CO', 'iso_code' => 'COL', 'name' => 'Colombia'],
                    ['alpha2_code' => 'BR', 'iso_code' => 'BRA', 'name' => 'Brasil'],
                ] as $country) {
                    \Illuminate\Support\Facades\DB::table('countries')->updateOrInsert(
                        ['alpha2_code' => $country['alpha2_code']],
                        $country
                    );
                }
            });

            $app['events']->listen(ConnectionEstablished::class, function ($event) {
                $check = static function (Connection $connection): void {
                    self::assertSafeDatabase(array_replace(
                        $connection->getConfig(),
                        ['database' => $connection->getDatabaseName()]
                    ));
                };

                $event->connection->beforeExecuting(
                    static function ($query, $bindings, Connection $connection) use ($check): void {
                        $check($connection);
                    }
                );
                $check($event->connection);
            });
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    private static function assertSafeDatabase(array $config): void
    {
        if (($config['driver'] ?? null) === 'sqlite'
            && ($config['database'] ?? null) === ':memory:'
            && empty($config['url'])) {
            return;
        }

        if (($config['driver'] ?? null) === 'mysql'
            && ($config['database'] ?? null) === 'app_cargas_test'
            && ($config['username'] ?? null) === 'app_cargas_test'
            && ($config['host'] ?? null) === '127.0.0.1'
            && (string) ($config['port'] ?? '') === '3306'
            && empty($config['unix_socket'])
            && empty($config['url'])
            && empty($config['read'])
            && empty($config['write'])) {
            return;
        }

        throw new RuntimeException(
            'Unsafe test database: only local app_cargas_test with its dedicated user or SQLite :memory: is allowed.'
        );
    }
}

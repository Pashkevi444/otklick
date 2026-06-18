<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Гарантия изоляции тестов от реальной БД: приложение в тестах ВСЕГДА работает
     * на эфемерной in-memory SQLite — что бы ни было в окружении (даже если в
     * шелле экспортирован `DB_CONNECTION=pgsql`). Конфиг переопределяется ДО того,
     * как `RefreshDatabase` тронет соединение, поэтому в реальный Postgres (прод
     * или локальный) при прогоне тестов записать ничего нельзя.
     */
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Тесты не зависят от собранных Vite-ассетов.
        $this->withoutVite();
    }
}

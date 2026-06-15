<?php

declare(strict_types=1);

namespace App\Tenancy;

use Closure;
use Illuminate\Database\DatabaseManager;

/**
 * Единая точка входа в тенант-контекст для запросов и очередей.
 *
 * Помимо in-memory {@see TenantContext} (его читают TenantScope и
 * BelongsToTenant) выставляет сессионную переменную PostgreSQL
 * app.current_tenant — по ней работает Row-Level Security, жёсткий рубеж
 * изоляции на уровне БД. На sqlite (тесты) SET пропускается; изоляцию там
 * держит глобальный scope.
 *
 * Канонический способ — {@see run()}: он гарантированно сбрасывает контекст и
 * сессионную переменную в finally, поэтому тенант не «протекает» между
 * запросами/задачами на резидентном рантайме (Octane) и пуле соединений.
 */
final readonly class TenantInitializer
{
    public function __construct(
        private TenantContext $context,
        private DatabaseManager $db,
    ) {}

    public function initialize(string $tenantId): void
    {
        $this->context->set($tenantId);
        $this->applyToDatabase($tenantId);
    }

    public function flush(): void
    {
        $this->context->forget();
        $this->applyToDatabase('');
    }

    /**
     * Выполняет колбэк в контексте тенанта, гарантированно сбрасывая контекст
     * после завершения (в т.ч. при исключении).
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(string $tenantId, Closure $callback): mixed
    {
        $this->initialize($tenantId);

        try {
            return $callback();
        } finally {
            $this->flush();
        }
    }

    /**
     * Выставляет app.current_tenant на текущем соединении (только PostgreSQL).
     * Пустая строка трактуется RLS-политикой как «тенант не задан».
     */
    private function applyToDatabase(string $tenantId): void
    {
        $connection = $this->db->connection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement("select set_config('app.current_tenant', ?, false)", [$tenantId]);
    }
}

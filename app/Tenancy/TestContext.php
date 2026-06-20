<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\SandboxRecord;

/**
 * Флаг «текущий запрос — тестовый прогон бота» в рамках одного запроса.
 *
 * Пока активен: строки, создаваемые основным пайплайном (диалог, клиент,
 * сообщения, A/B), помечаются в реестр {@see SandboxRecord}, а
 * глобальный {@see SandboxScope} НЕ прячет помеченные строки (пайплайн их видит
 * и обновляет). Внешние эффекты (запись в CRM) при активном флаге не делаются.
 *
 * Биндится как scoped — Octane сбрасывает инстанс между запросами, признак не
 * «протекает» между запросами в резидентном рантайме.
 */
final class TestContext
{
    private bool $active = false;

    public function enable(): void
    {
        $this->active = true;
    }

    public function disable(): void
    {
        $this->active = false;
    }

    public function active(): bool
    {
        return $this->active;
    }

    /**
     * Выполнить замыкание в режиме теста, гарантированно вернув флаг обратно.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function run(callable $callback): mixed
    {
        $previous = $this->active;
        $this->active = true;

        try {
            return $callback();
        } finally {
            $this->active = $previous;
        }
    }
}

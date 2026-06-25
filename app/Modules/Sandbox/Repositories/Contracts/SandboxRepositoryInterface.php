<?php

declare(strict_types=1);

namespace App\Modules\Sandbox\Repositories\Contracts;

use App\Modules\Channels\Models\Channel;
use App\Shared\Tenancy\TestContext;

/**
 * Доступ к данным «песочницы» тестирования бота. Все методы рассчитаны на
 * активный {@see TestContext} (или снимают scope сами) — работают
 * строго с тестовыми строками текущего тенанта.
 */
interface SandboxRepositoryInterface
{
    /** Найти-или-создать служебный тестовый канал тенанта (веб-тип, неактивный). */
    public function channel(): Channel;

    /** Удалить тестовые данные конкретного чата (диалоги + их клиентов). */
    public function resetChat(string $externalChatId): void;

    /**
     * Удалить все тестовые данные текущего тенанта (реестр + помеченные строки;
     * зависимые уходят каскадом). Возвращает число удалённых «корневых» строк.
     */
    public function purgeForCurrentTenant(): int;
}

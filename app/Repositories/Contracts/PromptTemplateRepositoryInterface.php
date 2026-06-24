<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface PromptTemplateRepositoryInterface
{
    /**
     * Тело («голова») промпта под нишу бизнеса: сначала запись с этим
     * business_type, иначе универсальный дефолт (business_type = null). Если нет
     * ни того ни другого — null (тогда PromptBuilder берёт встроенный дефолт).
     */
    public function behaviorFor(?string $businessType): ?string;
}

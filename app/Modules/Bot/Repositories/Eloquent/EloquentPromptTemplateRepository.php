<?php

declare(strict_types=1);

namespace App\Modules\Bot\Repositories\Eloquent;

use App\Modules\Bot\Models\PromptTemplate;
use App\Modules\Bot\Repositories\Contracts\PromptTemplateRepositoryInterface;

final class EloquentPromptTemplateRepository implements PromptTemplateRepositoryInterface
{
    public function behaviorFor(?string $businessType): ?string
    {
        $row = $businessType !== null && $businessType !== ''
            ? PromptTemplate::query()->where('business_type', $businessType)->first()
            : null;

        $row ??= PromptTemplate::query()->whereNull('business_type')->first();

        return $row?->body;
    }
}

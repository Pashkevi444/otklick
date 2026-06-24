<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PromptTemplate;
use App\Repositories\Contracts\PromptTemplateRepositoryInterface;

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

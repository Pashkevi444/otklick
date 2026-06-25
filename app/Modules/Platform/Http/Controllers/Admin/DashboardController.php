<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Bot\Models\PromptTemplate;
use App\Modules\Flows\Models\ScenarioTemplate;
use App\Modules\Knowledge\Models\KnowledgeTemplate;
use App\Shared\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Дашборд супер-админки — единая точка входа со всеми разделами площадки
 * (бизнесы, новости, ошибки, шаблоны, плашки, сайт) в виде плашек. Ссылка на
 * трекер ошибок приходит общим Inertia-пропом `errorTrackingUrl`.
 */
final class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'counts' => [
                'tenants' => Tenant::query()->count(),
                'scenarioTemplates' => ScenarioTemplate::query()->count(),
                'knowledgeTemplates' => KnowledgeTemplate::query()->count(),
                'promptTemplates' => PromptTemplate::query()->count(),
            ],
        ]);
    }
}

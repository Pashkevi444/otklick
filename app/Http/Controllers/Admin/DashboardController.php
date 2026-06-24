<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeTemplate;
use App\Models\PromptTemplate;
use App\Models\ScenarioTemplate;
use App\Models\Tenant;
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

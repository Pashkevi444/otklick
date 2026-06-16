<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\LeadAnalyticsPeriod;
use App\Http\Controllers\Controller;
use App\Services\LeadAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, LeadAnalyticsService $analytics): Response
    {
        $period = LeadAnalyticsPeriod::fromValue($request->query('period'));

        return Inertia::render('Cabinet/Dashboard', [
            'analytics' => $analytics->forPeriod($period)->toArray(),
        ]);
    }
}

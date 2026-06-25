<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Modules\Clients\Repositories\Contracts\ClientRepositoryInterface;
use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Shared\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly ClientRepositoryInterface $clients,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        // Счётчики показываем владельцу/сотруднику с доступом к лидам.
        $canSeeStats = $user->allows('conversations');

        $stats = null;
        if ($canSeeStats) {
            $stats = [
                ...$this->conversations->dashboardStats(),
                // Базу клиентов считаем только если она доступна по тарифу.
                'clients' => $user->tenant?->features()->clientBase ? $this->clients->countForCurrentTenant() : null,
            ];
        }

        return Inertia::render('Cabinet/Dashboard', [
            'stats' => $stats,
        ]);
    }
}

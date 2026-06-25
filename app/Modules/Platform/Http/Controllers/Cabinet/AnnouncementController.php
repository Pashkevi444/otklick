<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Cabinet;

use App\Modules\Platform\Services\AnnouncementService;
use App\Shared\Enums\AnnouncementType;
use App\Shared\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Лента анонсов площадки для бизнеса: новости и обновления (патчи) с пагинацией и
 * детальными страницами. Открытие помечает анонсы прочитанными (бейдж гаснет).
 */
final class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function news(Request $request): Response
    {
        return $this->feed($request, AnnouncementType::News);
    }

    public function showNews(Request $request, string $announcement): Response
    {
        return $this->show($request, AnnouncementType::News, $announcement);
    }

    private function feed(Request $request, AnnouncementType $type): Response
    {
        return Inertia::render('Cabinet/Announcements/Index', [
            'type' => $type->value,
            'title' => $type->label(),
            'page' => $this->announcements->cabinetPaginated($type, (string) $request->user()->tenant_id),
        ]);
    }

    private function show(Request $request, AnnouncementType $type, string $id): Response
    {
        $item = $this->announcements->findForBusiness($id, $type, (string) $request->user()->tenant_id);

        abort_if($item === null, HttpResponse::HTTP_NOT_FOUND);

        return Inertia::render('Cabinet/Announcements/Show', [
            'type' => $type->value,
            'title' => $type->label(),
            'item' => $item,
        ]);
    }
}

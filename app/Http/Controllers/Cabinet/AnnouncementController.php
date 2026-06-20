<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\AnnouncementType;
use App\Http\Controllers\Controller;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Лента анонсов площадки для бизнеса: новости и обновления (патчи). Открытие
 * раздела помечает показанные анонсы прочитанными (бейдж непрочитанного гаснет).
 */
final class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function news(Request $request): Response
    {
        return $this->feed($request, AnnouncementType::News);
    }

    public function updates(Request $request): Response
    {
        return $this->feed($request, AnnouncementType::Update);
    }

    private function feed(Request $request, AnnouncementType $type): Response
    {
        return Inertia::render('Cabinet/Announcements/Index', [
            'type' => $type->value,
            'title' => $type->label(),
            'items' => $this->announcements->cabinetFeed($type, (string) $request->user()->tenant_id),
        ]);
    }
}

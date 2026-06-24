<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-app уведомления текущего пользователя (колокольчик + бейджи плашек). Доступно
 * любому пользователю кабинета (маршрут `cabinet.bell.*` — не раздел, поэтому
 * `EnsureSectionAllowed` его не гейтит). Выдача уже отфильтрована по правам в
 * {@see UserNotificationService}. Поллится фронтом (и/или дёргается по WS-пингу).
 */
final class UserNotificationController extends Controller
{
    public function __construct(private readonly UserNotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->notifications->forUser($this->user($request)));
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->notifications->markAllRead($this->user($request));

        return response()->json(['ok' => true]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}

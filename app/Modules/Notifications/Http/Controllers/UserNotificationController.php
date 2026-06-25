<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Services\UserNotificationService;
use App\Shared\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

    /**
     * Журнал «Все уведомления» — полная история текущего пользователя с пагинацией
     * и фильтром по разделу. Просмотр списка ничего не гасит (пер-элемент: гаснет
     * при открытии сущности или по «прочитать всё»).
     */
    public function history(Request $request): Response
    {
        $section = in_array($request->query('section'), ['conversations', 'knowledge', 'clients'], true)
            ? (string) $request->query('section')
            : null;

        return Inertia::render('Cabinet/Notifications/History', [
            ...$this->notifications->historyForUser($this->user($request), $section),
            'filters' => ['section' => $section ?? ''],
            'sections' => [
                ['value' => '', 'label' => 'Все'],
                ['value' => 'conversations', 'label' => 'Диалоги и записи'],
                ['value' => 'knowledge', 'label' => 'База знаний'],
                ['value' => 'clients', 'label' => 'Клиенты'],
            ],
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}

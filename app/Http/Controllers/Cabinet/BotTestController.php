<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Services\BotSandbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Тестирование бота: живой чат с ботом по своим настройкам (контактная форма,
 * запись, воронки, база знаний) без записи лидов/клиентов и без реальной записи
 * в CRM. Доступно на всех тарифах; сотруднику — по праву `testing` («Команда»).
 *
 * У каждого тестирующего свой тестовый диалог (по id пользователя), чтобы прогоны
 * сотрудников не смешивались.
 */
final class BotTestController extends Controller
{
    public function __construct(private readonly BotSandbox $sandbox) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Cabinet/Testing/Index', [
            'history' => $this->sandbox->history($this->chatId($request)),
        ]);
    }

    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $reply = $this->sandbox->send(
            $request->user()->tenant,
            $this->chatId($request),
            (string) $validated['text'],
        );

        return response()->json($reply->toArray());
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->sandbox->reset($this->chatId($request));

        return redirect()->route('cabinet.testing.index');
    }

    /** Идентификатор тестового диалога — свой для каждого пользователя тенанта. */
    private function chatId(Request $request): string
    {
        return 'sandbox:'.$request->user()->id;
    }
}

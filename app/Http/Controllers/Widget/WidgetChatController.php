<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget;

use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Services\WebWidgetService;
use App\Tenancy\TenantInitializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Публичный API веб-виджета (чат на сайте бизнеса). Stateless, без сессий/CSRF.
 * Источник запроса проверяется по списку разрешённых доменов (origin allow-list),
 * ответы отдаются с CORS под конкретный домен. Тенант-контекст ставится из URL
 * (как у Telegram-вебхука), под RLS канал иначе не виден.
 */
final class WidgetChatController extends Controller
{
    public function __construct(
        private readonly TenantInitializer $tenancy,
        private readonly ChannelRepositoryInterface $channels,
        private readonly WebWidgetService $widget,
    ) {}

    /**
     * Отдаёт JS-рантайм виджета (встраивается одним <script> на сайт бизнеса).
     */
    public function script(): Response
    {
        $js = (string) file_get_contents(resource_path('widget/widget.js'));

        return response($js, Response::HTTP_OK, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function session(Request $request, string $tenant, string $channel): JsonResponse
    {
        return $this->tenancy->run($tenant, function () use ($request, $channel): JsonResponse {
            $model = $this->resolve($channel);
            $origin = $this->guardOrigin($request, $model);

            return $this->cors(response()->json([
                'token' => $this->widget->startSession($model),
                'greeting' => 'Здравствуйте! Я виртуальный администратор. Чем могу помочь?',
            ]), $origin);
        });
    }

    public function message(Request $request, string $tenant, string $channel): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'text' => ['required', 'string', 'max:2000'],
        ]);

        return $this->tenancy->run($tenant, function () use ($request, $channel, $validated): JsonResponse {
            $model = $this->resolve($channel);
            $origin = $this->guardOrigin($request, $model);

            $reply = $this->widget->reply($model, (string) $validated['token'], (string) $validated['text']);

            return $this->cors(response()->json([
                'reply' => $reply->text,
                'needsHuman' => $reply->escalate,
            ]), $origin);
        });
    }

    /**
     * Префлайт CORS (OPTIONS) — отвечаем до основной логики.
     */
    public function preflight(Request $request, string $tenant, string $channel): Response
    {
        $origin = $this->tenancy->run($tenant, function () use ($request, $channel): string {
            return $this->guardOrigin($request, $this->resolve($channel));
        });

        return $this->cors(response('', Response::HTTP_NO_CONTENT), $origin);
    }

    /**
     * Находит активный веб-канал тенанта с действующим доступом.
     */
    private function resolve(string $channelId): Channel
    {
        $channel = $this->channels->find($channelId);

        abort_if(
            $channel === null || $channel->type !== ChannelType::Web || ! $channel->is_active,
            Response::HTTP_NOT_FOUND,
        );

        abort_if(
            $channel->tenant === null || ! $channel->tenant->hasActiveAccess(),
            Response::HTTP_FORBIDDEN,
            'Виджет временно недоступен.',
        );

        return $channel;
    }

    /**
     * Проверяет Origin запроса по списку разрешённых доменов канала.
     * Пустой список — разрешено отовсюду. Возвращает origin для CORS-заголовка.
     */
    private function guardOrigin(Request $request, Channel $channel): string
    {
        $origin = (string) $request->headers->get('Origin', '');

        $allowed = $channel->settings['allowed_origins'] ?? [];

        if (is_array($allowed) && $allowed !== [] && $origin !== '' && ! in_array($origin, $allowed, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Домен не разрешён для виджета.');
        }

        return $origin !== '' ? $origin : '*';
    }

    /**
     * @template T of BaseResponse
     *
     * @param  T  $response
     * @return T
     */
    private function cors(BaseResponse $response, string $origin): BaseResponse
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}

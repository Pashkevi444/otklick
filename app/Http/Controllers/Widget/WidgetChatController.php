<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget;

use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Services\WebWidgetService;
use App\Support\KnowledgeImageStorage;
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
        private readonly KnowledgeImageStorage $images,
    ) {}

    /**
     * Отдаёт JS-рантайм виджета (встраивается одним <script> на сайт бизнеса).
     */
    public function script(): Response
    {
        $js = (string) file_get_contents(resource_path('widget/widget.js'));

        // Короткий кэш + обязательная ревалидация по ETag: при обновлении логики
        // виджета браузеры быстро подхватывают новую версию, а не держат
        // устаревший скрипт час.
        return response($js, Response::HTTP_OK, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300, must-revalidate',
            'ETag' => '"'.md5($js).'"',
        ]);
    }

    /**
     * Публичное оформление виджета (цвет акцента). Рантайм виджета запрашивает
     * его при загрузке, чтобы покрасить кнопку/шапку под бизнес ещё до сессии.
     */
    public function config(Request $request, string $tenant, string $channel): JsonResponse
    {
        return $this->tenancy->run($tenant, function () use ($request, $channel): JsonResponse {
            $model = $this->resolve($channel);
            $origin = $this->guardOrigin($request, $model);

            return $this->cors(response()->json([
                'color' => $model->settings['widget_color'] ?? null,
            ]), $origin);
        });
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

            ['reply' => $reply, 'lastId' => $lastId] = $this->widget->reply($model, (string) $validated['token'], (string) $validated['text'], $request->ip());

            return $this->cors(response()->json([
                'reply' => $reply->text,
                'needsHuman' => $reply->escalate,
                // Кликабельные подсказки (календарь/время/услуги мастера записи) —
                // как кнопки в мессенджерах; нажатие отправит подпись.
                'options' => $reply->keyboard?->labels() ?? [],
                // Фото примеров работ — отдельным полем (виджет рендерит как <img>).
                'images' => $reply->images,
                // Курсор для лайв-поллинга: с него виджет тянет ответы оператора.
                'lastId' => $lastId,
            ]), $origin);
        });
    }

    /**
     * Клиент прикрепил фото в виджете. Сохраняем картинку, фиксируем её в диалоге и
     * передаём администратору (бот изображения не распознаёт). Возвращаем URL фото
     * (виджет покажет) + подтверждение.
     */
    public function upload(Request $request, string $tenant, string $channel): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->tenancy->run($tenant, function () use ($request, $channel, $validated): JsonResponse {
            $model = $this->resolve($channel);
            $origin = $this->guardOrigin($request, $model);

            $stored = $this->images->store((string) $model->getAttribute('tenant_id'), [$request->file('image')], 'widget');

            [
                'reply' => $reply,
                'lastId' => $lastId,
                'images' => $images,
                'operatorActive' => $operatorActive,
            ] = $this->widget->receiveImage($model, (string) $validated['token'], $stored, (string) ($validated['caption'] ?? ''), $request->ip());

            return $this->cors(response()->json([
                'reply' => $reply->text,
                'needsHuman' => $reply->escalate,
                'images' => $images,
                'lastId' => $lastId,
                'operatorActive' => $operatorActive,
            ]), $origin);
        });
    }

    /**
     * Лайв-поллинг виджета: новые сообщения (ответы оператора) после `after` +
     * признак, что на связи оператор. Виджет опрашивает раз в ~3 сек.
     */
    public function poll(Request $request, string $tenant, string $channel): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'after' => ['nullable', 'string'],
        ]);

        return $this->tenancy->run($tenant, function () use ($request, $channel, $validated): JsonResponse {
            $model = $this->resolve($channel);
            $origin = $this->guardOrigin($request, $model);

            $data = $this->widget->poll($model, (string) $validated['token'], $validated['after'] ?? null);

            return $this->cors(response()->json($data), $origin);
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

        // Сравниваем без хвостового слэша и регистра: пользователь мог ввести
        // «https://site.ru/», а браузер шлёт «https://site.ru».
        $normalize = static fn (string $o): string => rtrim(mb_strtolower(trim($o)), '/');

        if (is_array($allowed) && $allowed !== [] && $origin !== '') {
            $allowedNormalized = array_map($normalize, array_map('strval', $allowed));

            if (! in_array($normalize($origin), $allowedNormalized, true)) {
                abort(Response::HTTP_FORBIDDEN, 'Домен не разрешён для виджета.');
            }
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
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}

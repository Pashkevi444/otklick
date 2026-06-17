<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\StoreChannelRequest;
use App\Models\Channel;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Services\ChannelService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Каналы тенанта в кабинете (Telegram, ВКонтакте). Данные автоматически
 * скоупятся текущим тенантом (контекст ставит BindTenantToRequest).
 */
final class ChannelController extends Controller
{
    /** Каналы-мессенджеры, которыми управляют на вкладке «Каналы». */
    private const array MANAGED = [ChannelType::Telegram, ChannelType::Vk];

    public function __construct(
        private readonly ChannelRepositoryInterface $channels,
        private readonly ChannelService $channelService,
    ) {}

    public function index(): Response
    {
        // Веб-виджетом управляют на вкладке «Виджет» — его тут не показываем.
        return Inertia::render('Cabinet/Channels/Index', [
            'channels' => $this->channels->forCurrentTenant()
                ->filter(fn (Channel $c): bool => in_array($c->type, self::MANAGED, true))
                ->map($this->present(...))
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreChannelRequest $request): RedirectResponse
    {
        $type = ChannelType::from((string) $request->string('type'));
        $tenantId = (string) $request->user()->tenant_id;
        $field = $type === ChannelType::Vk ? 'access_token' : 'bot_token';

        try {
            $this->connect($type, $tenantId, $request);
        } catch (RequestException $e) {
            Log::warning('Канал: внешний API отклонил подключение', [
                'tenant_id' => $tenantId,
                'type' => $type->value,
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]);

            return back()->withErrors([$field => $this->rejectedMessage($type)]);
        } catch (ConnectionException $e) {
            Log::warning('Канал: внешний API недоступен (таймаут)', [
                'tenant_id' => $tenantId,
                'type' => $type->value,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([$field => $this->timeoutMessage($type)]);
        } catch (Throwable $e) {
            Log::error('Канал: непредвиденная ошибка подключения', [
                'tenant_id' => $tenantId,
                'type' => $type->value,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([$field => $this->rejectedMessage($type)]);
        }

        return redirect()
            ->route('cabinet.channels.index')
            ->with('success', "{$type->label()} подключён.");
    }

    public function destroy(string $channel): RedirectResponse
    {
        $model = $this->channels->find($channel);

        abort_if($model === null, 404);

        $this->channels->delete($model);

        return redirect()
            ->route('cabinet.channels.index')
            ->with('success', 'Канал отключён.');
    }

    private function connect(ChannelType $type, string $tenantId, StoreChannelRequest $request): void
    {
        match ($type) {
            ChannelType::Vk => $this->channelService->connectVk(
                $tenantId,
                (string) $request->string('access_token'),
                (string) $request->string('group_id'),
            ),
            default => $this->channelService->connectTelegram(
                $tenantId,
                (string) $request->string('bot_token'),
                (string) config('services.telegram.webhook_base_url'),
            ),
        };
    }

    private function rejectedMessage(ChannelType $type): string
    {
        return $type === ChannelType::Vk
            ? 'ВКонтакте не подтвердил сообщество. Проверьте токен сообщества (с правами на сообщения) и его id.'
            : 'Telegram отклонил запрос. Проверьте токен бота и публичный HTTPS-адрес '.
                '(TELEGRAM_WEBHOOK_BASE_URL); localhost Telegram не принимает.';
    }

    private function timeoutMessage(ChannelType $type): string
    {
        $service = $type === ChannelType::Vk ? 'ВКонтакте (api.vk.com)' : 'Telegram (api.telegram.org)';

        return "Не удалось связаться с {$service} (таймаут сети). Попробуйте ещё раз через минуту.";
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Channel $channel): array
    {
        return [
            'id' => $channel->id,
            'type' => $channel->type->label(),
            'type_value' => $channel->type->value,
            'external_id' => $channel->external_id,
            'is_active' => $channel->is_active,
            'detail' => $this->detail($channel),
            'created_at' => $channel->created_at?->toDateString(),
        ];
    }

    /** Подпись под каналом: для VK — id сообщества, для Telegram — URL вебхука. */
    private function detail(Channel $channel): string
    {
        if ($channel->type === ChannelType::Vk) {
            return 'Сообщество #'.$channel->external_id;
        }

        $baseUrl = rtrim((string) config('services.telegram.webhook_base_url'), '/');

        return "{$baseUrl}/webhooks/telegram/{$channel->tenant_id}/{$channel->id}";
    }
}

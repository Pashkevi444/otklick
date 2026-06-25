<?php

declare(strict_types=1);

namespace App\Modules\Channels\Http\Controllers;

use App\Modules\Channels\Http\Requests\StoreChannelRequest;
use App\Modules\Channels\Models\Channel;
use App\Modules\Channels\Repositories\Contracts\ChannelRepositoryInterface;
use App\Modules\Channels\Services\ChannelService;
use App\Shared\Enums\ChannelType;
use App\Shared\Http\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Каналы тенанта в кабинете (Telegram, ВКонтакте, MAX). Данные автоматически
 * скоупятся текущим тенантом (контекст ставит BindTenantToRequest).
 */
final class ChannelController extends Controller
{
    /** Каналы-мессенджеры, которыми управляют на вкладке «Каналы». */
    private const array MANAGED = [ChannelType::Telegram, ChannelType::Vk, ChannelType::Max, ChannelType::WhatsApp];

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
        $field = match ($type) {
            ChannelType::Telegram => 'bot_token',
            ChannelType::WhatsApp => 'api_token',
            default => 'access_token',
        };

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
            ChannelType::Max => $this->channelService->connectMax(
                $tenantId,
                (string) $request->string('access_token'),
            ),
            ChannelType::WhatsApp => $this->channelService->connectWhatsApp(
                $tenantId,
                (string) $request->string('id_instance'),
                (string) $request->string('api_token'),
            ),
            default => $this->channelService->connectTelegram(
                $tenantId,
                (string) $request->string('bot_token'),
            ),
        };
    }

    private function rejectedMessage(ChannelType $type): string
    {
        return match ($type) {
            ChannelType::Vk => 'ВКонтакте не подтвердил сообщество. Проверьте токен сообщества (с правами на сообщения) и его id.',
            ChannelType::Max => 'MAX отклонил токен. Проверьте токен бота, выданный @MasterBot.',
            ChannelType::WhatsApp => 'Green API отклонил подключение или аккаунт не привязан. Проверьте idInstance/apiTokenInstance и что вы отсканировали QR (статус «authorized»).',
            default => 'Telegram отклонил токен. Проверьте токен бота, выданный @BotFather.',
        };
    }

    private function timeoutMessage(ChannelType $type): string
    {
        $service = match ($type) {
            ChannelType::Vk => 'ВКонтакте (api.vk.com)',
            ChannelType::Max => 'MAX (botapi.max.ru)',
            ChannelType::WhatsApp => 'Green API (api.green-api.com)',
            default => 'Telegram (api.telegram.org)',
        };

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

    /** Подпись под каналом: VK — id сообщества, MAX/Telegram/WhatsApp — способ связи. */
    private function detail(Channel $channel): string
    {
        return match ($channel->type) {
            ChannelType::Vk => 'Сообщество #'.$channel->external_id,
            ChannelType::Max => 'Бот MAX (long polling)',
            ChannelType::WhatsApp => 'WhatsApp · Green API #'.$channel->external_id,
            default => 'Бот Telegram (long polling)',
        };
    }
}

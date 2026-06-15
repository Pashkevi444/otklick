<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

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
 * Каналы тенанта в кабинете. Данные автоматически скоупятся текущим тенантом
 * (контекст ставит BindTenantToRequest).
 */
final class ChannelController extends Controller
{
    public function __construct(
        private readonly ChannelRepositoryInterface $channels,
        private readonly ChannelService $channelService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Cabinet/Channels/Index', [
            'channels' => $this->channels->forCurrentTenant()->map($this->present(...))->all(),
        ]);
    }

    public function store(StoreChannelRequest $request): RedirectResponse
    {
        try {
            $this->channelService->connectTelegram(
                (string) $request->user()->tenant_id,
                (string) $request->string('bot_token'),
                (string) config('services.telegram.webhook_base_url'),
            );
        } catch (RequestException $e) {
            Log::warning('Telegram: вебхук не зарегистрирован (ответ API с ошибкой)', [
                'tenant_id' => $request->user()?->tenant_id,
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]);

            return back()->withErrors([
                'bot_token' => 'Telegram отклонил запрос. Проверьте токен бота и публичный HTTPS-адрес '.
                    '(TELEGRAM_WEBHOOK_BASE_URL); localhost Telegram не принимает.',
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Telegram: не удалось связаться с api.telegram.org', [
                'tenant_id' => $request->user()?->tenant_id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'bot_token' => 'Не удалось связаться с Telegram (таймаут сети). Попробуйте ещё раз через минуту — '.
                    'возможна временная недоступность api.telegram.org.',
            ]);
        } catch (Throwable $e) {
            Log::error('Telegram: непредвиденная ошибка подключения канала', [
                'tenant_id' => $request->user()?->tenant_id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'bot_token' => 'Не удалось подключить канал из-за внутренней ошибки. Мы записали детали в лог — попробуйте позже.',
            ]);
        }

        return redirect()
            ->route('cabinet.channels.index')
            ->with('success', 'Telegram-канал подключён.');
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

    /**
     * @return array<string, mixed>
     */
    private function present(Channel $channel): array
    {
        $baseUrl = rtrim((string) config('services.telegram.webhook_base_url'), '/');

        return [
            'id' => $channel->id,
            'type' => $channel->type->label(),
            'external_id' => $channel->external_id,
            'is_active' => $channel->is_active,
            'webhook_url' => "{$baseUrl}/webhooks/telegram/{$channel->tenant_id}/{$channel->id}",
            'created_at' => $channel->created_at?->toDateString(),
        ];
    }
}

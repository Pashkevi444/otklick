<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\StoreChannelRequest;
use App\Models\Channel;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Services\ChannelService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

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
        } catch (RequestException) {
            return back()->withErrors([
                'bot_token' => 'Не удалось зарегистрировать вебхук в Telegram. Проверьте токен.',
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

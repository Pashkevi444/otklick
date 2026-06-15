<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\ChannelType;
use App\Http\Requests\Webhooks\TelegramWebhookRequest;
use App\Jobs\ProcessTelegramUpdate;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Tenancy\TenantInitializer;
use Illuminate\Http\Response;

/**
 * Приём вебхуков Telegram. Тонкий: резолвит канал в тенант-контексте из URL,
 * проверяет secret-заголовок, кладёт задачу в очередь и сразу отвечает 200
 * (ack < 100 мс). Разбор и ответ — в ProcessTelegramUpdate.
 *
 * tenant_id в URL — лишь маршрутный ориентир (непрозрачный UUID), нужный чтобы
 * выставить тенант-контекст ДО чтения канала (под RLS иначе канал не виден).
 * Реальная аутентификация источника — secret_token в заголовке.
 */
final class TelegramWebhookController
{
    public function __construct(
        private readonly TenantInitializer $tenancy,
        private readonly ChannelRepositoryInterface $channels,
    ) {}

    public function __invoke(TelegramWebhookRequest $request, string $tenant, string $channel): Response
    {
        $this->tenancy->initialize($tenant);

        try {
            $model = $this->channels->find($channel);

            // Канал не найден в этом тенанте (scope/RLS) или не Telegram/неактивен.
            abort_if(
                $model === null || $model->type !== ChannelType::Telegram || ! $model->is_active,
                Response::HTTP_NOT_FOUND,
            );

            abort_unless(
                hash_equals(
                    (string) $model->secretToken(),
                    (string) $request->header('X-Telegram-Bot-Api-Secret-Token'),
                ),
                Response::HTTP_FORBIDDEN,
            );
        } finally {
            $this->tenancy->flush();
        }

        ProcessTelegramUpdate::dispatch($tenant, $channel, $request->all());

        return response()->noContent(Response::HTTP_OK);
    }
}

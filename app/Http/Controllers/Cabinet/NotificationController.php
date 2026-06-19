<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\ChannelType;
use App\Enums\NotificationChannelType;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\NotificationRecipient;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Contracts\NotificationRecipientRepositoryInterface;
use App\Services\NotificationRecipientService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Кабинет: получатели уведомлений владельца (email/Telegram) с лимитами тарифа.
 */
final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationRecipientRepositoryInterface $recipients,
        private readonly NotificationRecipientService $service,
        private readonly ChannelRepositoryInterface $channels,
        private readonly TenantService $tenants,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        $features = $tenant->features();

        $hasTelegramBot = $this->channels->forCurrentTenant()
            ->contains(fn (Channel $c): bool => $c->type === ChannelType::Telegram && $c->is_active);

        return Inertia::render('Cabinet/Notifications', [
            'recipients' => $this->recipients->forCurrentTenant()->map(fn (NotificationRecipient $r): array => [
                'id' => $r->id,
                'type' => $r->type->value,
                'typeLabel' => $r->type->label(),
                'value' => $r->value,
                'label' => $r->label,
                'isActive' => $r->is_active,
                'verified' => $r->verified_at !== null,
            ])->all(),
            'limits' => [
                'email' => $features->maxNotifyEmail,
                'telegram' => $features->maxNotifyTelegram,
                'emailUsed' => $this->recipients->countByType(NotificationChannelType::Email),
                'telegramUsed' => $this->recipients->countByType(NotificationChannelType::Telegram),
            ],
            'hasTelegramBot' => $hasTelegramBot,
            // Недельный AI-дайджест («директор») — только при праве aiInsights.
            // По умолчанию включён; бизнес может выключить тумблером.
            'weeklyDigest' => [
                'available' => $features->has('aiInsights'),
                'enabled' => (bool) ($tenant->settings['weekly_digest'] ?? true),
            ],
        ]);
    }

    public function weeklyDigest(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        $this->tenants->setWeeklyDigest($request->user()->tenant, (bool) $data['enabled']);

        return back()->with('success', $data['enabled'] ? 'Недельный дайджест включён.' : 'Недельный дайджест выключен.');
    }

    public function storeEmail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $this->service->addEmail($request->user()->tenant, (string) $data['email'], $data['label'] ?? null);

        return back()->with('success', 'Получатель добавлен.');
    }

    public function connectTelegram(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $link = $this->service->startTelegramLink($request->user()->tenant, $data['label'] ?? null);

        return back()->with('telegram_link', $link);
    }

    public function destroy(string $recipient): RedirectResponse
    {
        $model = $this->recipients->findForCurrentTenant($recipient);
        abort_if($model === null, 404);

        $this->recipients->delete($model);

        return back()->with('success', 'Получатель удалён.');
    }

    public function toggle(string $recipient): RedirectResponse
    {
        $model = $this->recipients->findForCurrentTenant($recipient);
        abort_if($model === null, 404);

        $this->recipients->update($model, ['is_active' => ! $model->is_active]);

        return back();
    }
}

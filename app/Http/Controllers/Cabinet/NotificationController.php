<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\ChannelType;
use App\Enums\NotificationChannelType;
use App\Enums\OwnerEvent;
use App\Enums\RecipientRole;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\NotificationRecipient;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Contracts\NotificationRecipientRepositoryInterface;
use App\Services\NotificationRecipientService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
                'role' => $r->role->value,
                // events=[] означает «все типы» — для UI это все галки включены.
                'events' => $r->events === [] ? array_map(static fn (OwnerEvent $e): string => $e->value, OwnerEvent::cases()) : $r->events,
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
            'eventOptions' => array_map(static fn (OwnerEvent $e): array => ['value' => $e->value, 'label' => $e->title()], OwnerEvent::cases()),
            'roleOptions' => array_map(static fn (RecipientRole $r): array => ['value' => $r->value, 'label' => $r->label()], RecipientRole::cases()),
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
            'role' => $this->roleRule(),
        ]);

        $this->service->addEmail($request->user()->tenant, (string) $data['email'], $data['label'] ?? null, $this->role($request));

        return back()->with('success', 'Получатель добавлен.');
    }

    public function connectTelegram(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:80'],
            'role' => $this->roleRule(),
        ]);

        $link = $this->service->startTelegramLink($request->user()->tenant, $data['label'] ?? null, $this->role($request));

        return back()->with('telegram_link', $link);
    }

    /**
     * Настройка получателя: роль (директор/сотрудник) и подписка на типы событий.
     */
    public function updatePreferences(Request $request, string $recipient): RedirectResponse
    {
        $model = $this->recipients->findForCurrentTenant($recipient);
        abort_if($model === null, 404);

        $validated = $request->validate([
            'role' => $this->roleRule(),
            'events' => ['array'],
            'events.*' => [Rule::in(array_map(static fn (OwnerEvent $e): string => $e->value, OwnerEvent::cases()))],
        ]);

        $events = array_values(array_unique(array_map('strval', (array) ($validated['events'] ?? []))));

        // Выбраны все типы — храним [] («все», в т.ч. будущие типы событий).
        if (count($events) === count(OwnerEvent::cases())) {
            $events = [];
        }

        $this->service->updatePreferences($model, $this->role($request), $events);

        return back()->with('success', 'Настройки получателя сохранены.');
    }

    /**
     * @return list<mixed>
     */
    private function roleRule(): array
    {
        return ['nullable', Rule::in(array_map(static fn (RecipientRole $r): string => $r->value, RecipientRole::cases()))];
    }

    private function role(Request $request): RecipientRole
    {
        return RecipientRole::tryFrom((string) $request->input('role')) ?? RecipientRole::Director;
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

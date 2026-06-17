<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Crm\Data\CredentialField;
use App\DTO\ReminderSettings;
use App\Enums\CrmProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\StoreCrmConnectionRequest;
use App\Models\CrmConnection;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Services\CrmConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Таб «Интеграции» в кабинете тенанта. Полностью провайдер-агностичен: список
 * CRM, поля подключения и т.д. берутся из стратегий (CrmGateway), здесь нет
 * знания о конкретной CRM.
 */
final class IntegrationController extends Controller
{
    public function __construct(
        private readonly CrmConnectionRepositoryInterface $connections,
        private readonly CrmConnectionService $crm,
    ) {}

    public function index(): Response
    {
        $integrations = array_map(function (CrmProvider $provider): array {
            $connection = $this->connections->findByProviderForCurrentTenant($provider);
            $fields = $this->crm->credentialFields($provider);

            return [
                'provider' => $provider->value,
                'label' => $provider->label(),
                'fields' => array_map(fn (CredentialField $f): array => [
                    'key' => $f->key,
                    'label' => $f->label,
                    'secret' => $f->secret,
                    'hint' => $f->hint,
                ], $fields),
                'connection' => $connection === null ? null : $this->present($connection, $fields),
            ];
        }, CrmProvider::cases());

        return Inertia::render('Cabinet/Integrations/Index', [
            'integrations' => $integrations,
        ]);
    }

    public function store(StoreCrmConnectionRequest $request, string $provider): RedirectResponse
    {
        $providerEnum = CrmProvider::from($provider);

        $credentials = [];
        foreach ($this->crm->credentialFields($providerEnum) as $field) {
            $credentials[$field->key] = (string) $request->input("credentials.{$field->key}");
        }

        $this->crm->connect((string) $request->user()->tenant_id, $providerEnum, $credentials);

        return redirect()
            ->route('cabinet.integrations.index')
            ->with('success', "{$providerEnum->label()} подключён.");
    }

    public function verify(string $connection): RedirectResponse
    {
        $ok = $this->crm->verify($this->findOrFail($connection));

        return redirect()
            ->route('cabinet.integrations.index')
            ->with(
                $ok ? 'success' : 'error',
                $ok ? 'Связь работает.' : 'Не удалось подключиться. Проверьте данные.',
            );
    }

    public function destroy(string $connection): RedirectResponse
    {
        $this->connections->delete($this->findOrFail($connection));

        return redirect()
            ->route('cabinet.integrations.index')
            ->with('success', 'Интеграция отключена.');
    }

    /**
     * Сохраняет настройки напоминаний клиенту о записи (в рамках этой CRM).
     */
    public function reminders(Request $request, string $connection): RedirectResponse
    {
        // Напоминания — отдельная возможность тарифа (поверх CRM-интеграции).
        abort_unless($request->user()->tenant->features()->reminders, 403, 'Напоминания недоступны на вашем тарифе.');

        $conn = $this->findOrFail($connection);

        $validated = $request->validate([
            'enabled' => ['boolean'],
            'offsets_hours' => ['array', 'max:5'],
            'offsets_hours.*' => ['numeric', 'min:0.25', 'max:168'],
        ]);

        $offsetsMinutes = array_map(
            static fn ($hours): int => (int) round(((float) $hours) * 60),
            $validated['offsets_hours'] ?? [],
        );

        $settings = $conn->settings;
        $settings['reminders'] = ReminderSettings::fromArray([
            'enabled' => (bool) ($validated['enabled'] ?? false),
            'offsets' => $offsetsMinutes,
        ])->toArray();

        $this->connections->updateSettings($conn, $settings);

        return redirect()
            ->route('cabinet.integrations.index')
            ->with('success', 'Напоминания сохранены.');
    }

    private function findOrFail(string $id): CrmConnection
    {
        $connection = $this->connections->find($id);

        abort_if($connection === null, 404);

        return $connection;
    }

    /**
     * @param  list<CredentialField>  $fields
     * @return array<string, mixed>
     */
    private function present(CrmConnection $connection, array $fields): array
    {
        // Показываем только несекретные поля (например, company_id), без токенов.
        $summary = [];
        foreach ($fields as $field) {
            if (! $field->secret) {
                $summary[$field->label] = $connection->credential($field->key);
            }
        }

        $reminders = ReminderSettings::fromArray($connection->settings['reminders'] ?? []);

        return [
            'id' => $connection->id,
            'is_active' => $connection->is_active,
            'connected_at' => $connection->created_at?->toDateString(),
            'summary' => $summary,
            'reminders' => [
                'enabled' => $reminders->enabled,
                // Часы — удобнее для формы; в минуты переводим при сохранении.
                'offsets_hours' => array_map(static fn (int $m): float => round($m / 60, 2), $reminders->offsetsMinutes),
            ],
        ];
    }
}

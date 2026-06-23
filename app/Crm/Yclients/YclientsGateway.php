<?php

declare(strict_types=1);

namespace App\Crm\Yclients;

use App\Crm\Contracts\CrmGateway;
use App\Crm\Data\BookingRequest;
use App\Crm\Data\BookingResult;
use App\Crm\Data\CredentialField;
use App\Crm\Data\CrmCompany;
use App\Crm\Data\CrmService;
use App\Crm\Data\CrmStaff;
use App\Crm\Data\SlotQuery;
use App\Crm\Data\TimeSlot;
use App\Enums\CrmProvider;
use App\Models\CrmConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Стратегия YClients (API онлайн-записи). Пути следуют booking-API YClients
 * (book_services / book_staff / book_times / book_record); валидируются на
 * реальных кредах при подключении живого аккаунта.
 *
 * Партнёрский токен приложения — из конфига, пользовательский токен и company_id
 * — из подключения тенанта.
 */
final readonly class YclientsGateway implements CrmGateway
{
    public function __construct(
        private string $apiUrl,
        private ?string $partnerToken,
    ) {}

    public function provider(): CrmProvider
    {
        return CrmProvider::Yclients;
    }

    public function credentialFields(): array
    {
        return [
            new CredentialField(
                'company_id',
                'ID филиала (company_id)',
                hint: 'В YClients: раздел «Обзор» → «Сводка» в левом меню.',
            ),
            new CredentialField(
                'api_token',
                'API-токен',
                secret: true,
                hint: 'В YClients: раздел «Интеграции» → «Аккаунт разработчика» (для создания связки).',
            ),
        ];
    }

    public function verifyConnection(CrmConnection $connection): bool
    {
        try {
            $status = $this->request($connection)->get("{$this->apiUrl}/company/{$this->companyId($connection)}")->status();
            Log::info('crm.yclients.verify', ['company_id' => $this->companyId($connection), 'status' => $status]);

            return $status >= 200 && $status < 300;
        } catch (Throwable $e) {
            Log::warning('crm.yclients.verify_failed', ['company_id' => $this->companyId($connection), 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function company(CrmConnection $connection): ?CrmCompany
    {
        try {
            $data = $this->request($connection)
                ->get("{$this->apiUrl}/company/{$this->companyId($connection)}")
                ->throw()
                ->json('data', []);

            Log::info('crm.yclients.company', ['company_id' => $this->companyId($connection), 'ok' => $data !== []]);

            if (! is_array($data) || $data === []) {
                return null;
            }

            return new CrmCompany(
                title: (string) ($data['title'] ?? $data['public_title'] ?? 'Филиал'),
                address: isset($data['address']) ? (string) $data['address'] : null,
                phone: isset($data['phone']) ? (string) $data['phone'] : null,
            );
        } catch (Throwable $e) {
            report($e);
            Log::warning('crm.yclients.company_failed', ['company_id' => $this->companyId($connection), 'error' => $e->getMessage()]);

            return null;
        }
    }

    public function services(CrmConnection $connection): array
    {
        $services = $this->request($connection)
            ->get("{$this->apiUrl}/book_services/{$this->companyId($connection)}")
            ->throw()
            ->json('data.services', []);

        Log::info('crm.yclients.services', ['company_id' => $this->companyId($connection), 'count' => count($services)]);

        return array_map(fn (array $s): CrmService => new CrmService(
            id: (string) $s['id'],
            title: (string) ($s['title'] ?? ''),
            price: isset($s['price_min']) ? (int) $s['price_min'] : null,
            durationMinutes: isset($s['seance_length']) ? (int) round(((int) $s['seance_length']) / 60) : null,
        ), $services);
    }

    public function staff(CrmConnection $connection): array
    {
        $staff = $this->request($connection)
            ->get("{$this->apiUrl}/book_staff/{$this->companyId($connection)}")
            ->throw()
            ->json('data', []);

        Log::info('crm.yclients.staff', ['company_id' => $this->companyId($connection), 'count' => count($staff)]);

        return array_map(fn (array $s): CrmStaff => new CrmStaff(
            id: (string) $s['id'],
            name: (string) ($s['name'] ?? ''),
            specialization: $s['specialization'] ?? null,
        ), $staff);
    }

    public function availableSlots(CrmConnection $connection, SlotQuery $query): array
    {
        $slots = $this->request($connection)
            ->get("{$this->apiUrl}/book_times/{$this->companyId($connection)}/{$query->staffId}/{$query->date}")
            ->throw()
            ->json('data', []);

        Log::info('crm.yclients.slots', [
            'company_id' => $this->companyId($connection),
            'staff_id' => $query->staffId,
            'date' => $query->date,
            'count' => count($slots),
        ]);

        return array_values(array_map(
            fn (array $slot): TimeSlot => new TimeSlot(start: (string) ($slot['datetime'] ?? $slot['time'] ?? '')),
            $slots,
        ));
    }

    public function createBooking(CrmConnection $connection, BookingRequest $request): BookingResult
    {
        $companyId = $this->companyId($connection);

        Log::info('crm.yclients.create_request', [
            'company_id' => $companyId,
            'service_id' => $request->serviceId,
            'staff_id' => $request->staffId,
            'datetime' => $request->start,
        ]);

        try {
            $response = $this->request($connection)->post("{$this->apiUrl}/book_record/{$companyId}", [
                'phone' => $request->clientPhone,
                'fullname' => $request->clientName,
                // YClients требует параметр email обязательно (даже пустым).
                'email' => $request->clientEmail ?? '',
                'comment' => $request->comment ?? '',
                'appointments' => [[
                    'id' => 1,
                    'services' => [(int) $request->serviceId],
                    'staff_id' => (int) $request->staffId,
                    'datetime' => $request->start,
                ]],
            ]);

            // Логируем статус и тело (без заголовков/токенов) — для разбора отказов CRM.
            Log::info('crm.yclients.create_response', [
                'company_id' => $companyId,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            $response->throw();

            $externalId = $response->json('data.0.record_id') ?? $response->json('data.0.id');

            return BookingResult::ok((string) $externalId);
        } catch (Throwable $e) {
            report($e);
            Log::error('crm.yclients.create_failed', ['company_id' => $companyId, 'error' => $e->getMessage()]);

            return BookingResult::failed('Не удалось создать запись в YClients.');
        }
    }

    public function cancelBooking(CrmConnection $connection, string $externalId): BookingResult
    {
        $companyId = $this->companyId($connection);

        Log::info('crm.yclients.cancel_request', ['company_id' => $companyId, 'record_id' => $externalId]);

        try {
            $response = $this->request($connection)->delete("{$this->apiUrl}/record/{$companyId}/{$externalId}");

            Log::info('crm.yclients.cancel_response', [
                'company_id' => $companyId,
                'record_id' => $externalId,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            $response->throw();

            return BookingResult::ok($externalId);
        } catch (Throwable $e) {
            report($e);
            Log::error('crm.yclients.cancel_failed', ['company_id' => $companyId, 'record_id' => $externalId, 'error' => $e->getMessage()]);

            return BookingResult::failed('Не удалось отменить запись в YClients.');
        }
    }

    private function request(CrmConnection $connection): PendingRequest
    {
        return Http::withHeaders([
            'Accept' => 'application/vnd.api.v2+json',
            'Authorization' => $this->authHeader($this->apiToken($connection)),
        ])->asJson();
    }

    private function companyId(CrmConnection $connection): ?string
    {
        return $connection->credential('company_id');
    }

    private function apiToken(CrmConnection $connection): ?string
    {
        return $connection->credential('api_token');
    }

    /**
     * Формат YClients: «Bearer <partner_token>, User <user_token>».
     */
    private function authHeader(?string $userToken): string
    {
        if ($this->partnerToken !== null && $this->partnerToken !== '') {
            return 'Bearer '.$this->partnerToken.($userToken !== null && $userToken !== '' ? ", User {$userToken}" : '');
        }

        return 'Bearer '.(string) $userToken;
    }
}

<?php

declare(strict_types=1);

namespace App\Crm\Yclients;

use App\Crm\Contracts\CrmGateway;
use App\Crm\Data\BookingRequest;
use App\Crm\Data\BookingResult;
use App\Crm\Data\CredentialField;
use App\Crm\Data\CrmService;
use App\Crm\Data\CrmStaff;
use App\Crm\Data\SlotQuery;
use App\Crm\Data\TimeSlot;
use App\Enums\CrmProvider;
use App\Models\CrmConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
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
            new CredentialField('company_id', 'ID филиала (company_id)'),
            new CredentialField('api_token', 'API-токен', secret: true),
        ];
    }

    public function verifyConnection(CrmConnection $connection): bool
    {
        try {
            return $this->request($connection)->get("{$this->apiUrl}/company/{$this->companyId($connection)}")->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function services(CrmConnection $connection): array
    {
        $services = $this->request($connection)
            ->get("{$this->apiUrl}/book_services/{$this->companyId($connection)}")
            ->throw()
            ->json('data.services', []);

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

        return array_values(array_map(
            fn (array $slot): TimeSlot => new TimeSlot(start: (string) ($slot['datetime'] ?? $slot['time'] ?? '')),
            $slots,
        ));
    }

    public function createBooking(CrmConnection $connection, BookingRequest $request): BookingResult
    {
        try {
            $response = $this->request($connection)->post("{$this->apiUrl}/book_record/{$this->companyId($connection)}", [
                'phone' => $request->clientPhone,
                'fullname' => $request->clientName,
                'comment' => $request->comment ?? '',
                'appointments' => [[
                    'id' => 1,
                    'services' => [(int) $request->serviceId],
                    'staff_id' => (int) $request->staffId,
                    'datetime' => $request->start,
                ]],
            ])->throw();

            $externalId = $response->json('data.0.record_id') ?? $response->json('data.0.id');

            return BookingResult::ok((string) $externalId);
        } catch (Throwable $e) {
            report($e);

            return BookingResult::failed('Не удалось создать запись в YClients.');
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

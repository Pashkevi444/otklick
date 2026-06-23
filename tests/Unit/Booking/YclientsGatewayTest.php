<?php

declare(strict_types=1);

namespace Tests\Unit\Booking;

use App\Booking\Data\BookingRequest;
use App\Booking\Data\SlotQuery;
use App\Booking\Yclients\YclientsGateway;
use App\Enums\CrmProvider;
use App\Models\CrmConnection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class YclientsGatewayTest extends TestCase
{
    private const string API = 'https://yc.test/api/v1';

    private function gateway(): YclientsGateway
    {
        return new YclientsGateway(self::API, 'partner-tok');
    }

    private function connection(): CrmConnection
    {
        return new CrmConnection([
            'provider' => CrmProvider::Yclients,
            'credentials' => ['company_id' => '42', 'api_token' => 'user-tok'],
        ]);
    }

    public function test_provider_is_yclients(): void
    {
        $this->assertSame(CrmProvider::Yclients, $this->gateway()->provider());
    }

    public function test_services_are_parsed(): void
    {
        Http::fake([self::API.'/book_services/42' => Http::response([
            'data' => ['services' => [
                ['id' => 1, 'title' => 'Стрижка', 'price_min' => 500, 'seance_length' => 3600],
            ]],
        ])]);

        $services = $this->gateway()->services($this->connection());

        $this->assertCount(1, $services);
        $this->assertSame('1', $services[0]->id);
        $this->assertSame('Стрижка', $services[0]->title);
        $this->assertSame(500, $services[0]->price);
        $this->assertSame(60, $services[0]->durationMinutes);

        Http::assertSent(fn ($r): bool => $r->hasHeader('Authorization', 'Bearer partner-tok, User user-tok'));
    }

    public function test_staff_are_parsed(): void
    {
        Http::fake([self::API.'/book_staff/42' => Http::response([
            'data' => [['id' => 7, 'name' => 'Иван', 'specialization' => 'Барбер']],
        ])]);

        $staff = $this->gateway()->staff($this->connection());

        $this->assertSame('7', $staff[0]->id);
        $this->assertSame('Иван', $staff[0]->name);
        $this->assertSame('Барбер', $staff[0]->specialization);
    }

    public function test_available_slots_are_parsed(): void
    {
        Http::fake([self::API.'/book_times/42/7/2026-06-20' => Http::response([
            'data' => [['time' => '10:00', 'datetime' => '2026-06-20T10:00:00+03:00']],
        ])]);

        $slots = $this->gateway()->availableSlots($this->connection(), new SlotQuery('7', '2026-06-20'));

        $this->assertCount(1, $slots);
        $this->assertSame('2026-06-20T10:00:00+03:00', $slots[0]->start);
    }

    public function test_create_booking_returns_external_id(): void
    {
        Http::fake([self::API.'/book_record/42' => Http::response(['data' => [['record_id' => 999]]])]);

        $result = $this->gateway()->createBooking($this->connection(), new BookingRequest(
            serviceId: '1', staffId: '7', start: '2026-06-20T10:00:00+03:00',
            clientName: 'Пётр', clientPhone: '+79001234567', comment: 'через бота',
        ));

        $this->assertTrue($result->success);
        $this->assertSame('999', $result->externalId);

        Http::assertSent(fn ($r): bool => $r['phone'] === '+79001234567'
            && $r['appointments'][0]['services'] === [1]
            && $r['appointments'][0]['staff_id'] === 7);
    }

    public function test_create_booking_handles_failure(): void
    {
        Http::fake([self::API.'/book_record/42' => Http::response([], 422)]);

        $result = $this->gateway()->createBooking($this->connection(), new BookingRequest(
            serviceId: '1', staffId: '7', start: '2026-06-20T10:00:00+03:00',
            clientName: 'Пётр', clientPhone: '+79001234567',
        ));

        $this->assertFalse($result->success);
    }

    public function test_verify_connection_succeeds(): void
    {
        Http::fake([self::API.'/company/42' => Http::response([], 200)]);

        $this->assertTrue($this->gateway()->verifyConnection($this->connection()));
    }

    public function test_verify_connection_fails_on_error_status(): void
    {
        Http::fake([self::API.'/company/42' => Http::response([], 403)]);

        $this->assertFalse($this->gateway()->verifyConnection($this->connection()));
    }
}

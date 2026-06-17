<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\ChannelType;
use App\Jobs\SendAppointmentReminder;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\CrmConnection;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class AppointmentRemindersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<int>  $offsets  минуты
     */
    private function tenantWithReminders(array $offsets): Tenant
    {
        $tenant = Tenant::factory()->max()->create();
        CrmConnection::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['reminders' => ['enabled' => true, 'offsets' => $offsets]],
        ]);

        return $tenant;
    }

    private function bookedConversation(Tenant $tenant, \DateTimeInterface $bookedFor): Conversation
    {
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id, 'type' => ChannelType::Telegram->value]);

        return Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'external_chat_id' => '555',
            'crm_record_id' => 'rec-1',
            'booked_for' => $bookedFor,
            'contact_name' => 'Иван',
        ]);
    }

    public function test_queues_reminder_when_due_and_marks_sent(): void
    {
        Queue::fake();

        $tenant = $this->tenantWithReminders([60]);                 // за 1 час
        $conv = $this->bookedConversation($tenant, now()->addMinutes(30)); // визит через 30 мин → пора

        Artisan::call('appointments:send-reminders');

        Queue::assertPushed(SendAppointmentReminder::class, 1);
        $this->assertContains(60, $conv->fresh()->reminders_sent);

        // Повторный запуск не задваивает.
        Artisan::call('appointments:send-reminders');
        Queue::assertPushed(SendAppointmentReminder::class, 1);
    }

    public function test_does_not_queue_when_not_yet_due(): void
    {
        Queue::fake();

        $tenant = $this->tenantWithReminders([60]);              // за 1 час
        $this->bookedConversation($tenant, now()->addHours(10)); // визит через 10 ч → рано

        Artisan::call('appointments:send-reminders');

        Queue::assertNotPushed(SendAppointmentReminder::class);
    }

    public function test_business_can_save_reminder_settings(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $connection = CrmConnection::factory()->create(['tenant_id' => $tenant->id]);
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->put("/cabinet/integrations/{$connection->id}/reminders", [
            'enabled' => true,
            'offsets_hours' => [24, 2],
        ])->assertRedirect(route('cabinet.integrations.index'));

        $reminders = $connection->fresh()->settings['reminders'];
        $this->assertTrue($reminders['enabled']);
        $this->assertSame([1440, 120], $reminders['offsets']); // часы → минуты, по убыванию
    }
}

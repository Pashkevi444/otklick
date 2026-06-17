<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DTO\ReminderSettings;
use App\Enums\ChannelType;
use App\Jobs\SendAppointmentReminder;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Tenancy\TenantInitializer;
use Illuminate\Console\Command;

/**
 * Ставит в очередь напоминания клиентам о записи. Идёт по всем тенантам с
 * активной CRM-интеграцией и включёнными напоминаниями; для каждой предстоящей
 * записи, у которой наступило время напоминания и оно ещё не отправлено,
 * «столбит» его (чтобы не задвоить на следующем тике) и ставит задачу отправки.
 * Запускается планировщиком (см. routes/console.php).
 */
final class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Ставит в очередь напоминания клиентам о записи (в рамках CRM-интеграции).';

    public function handle(
        TenantInitializer $tenancy,
        ConversationRepositoryInterface $conversations,
        CrmConnectionRepositoryInterface $connections,
    ): int {
        $dispatched = 0;

        Tenant::query()->pluck('id')->each(function (string $tenantId) use ($tenancy, $conversations, $connections, &$dispatched): void {
            $dispatched += $tenancy->run($tenantId, function () use ($tenantId, $conversations, $connections): int {
                // Напоминания — возможность тарифа (Макс/Индивидуальный или оверрайд СУ).
                $tenant = Tenant::query()->find($tenantId);
                if ($tenant === null || ! $tenant->features()->reminders) {
                    return 0;
                }

                $connection = $connections->activeForCurrentTenant();
                if ($connection === null) {
                    return 0;
                }

                $settings = ReminderSettings::fromArray($connection->settings['reminders'] ?? []);
                if (! $settings->isActive()) {
                    return 0;
                }

                $now = now();
                $horizon = $now->copy()->addMinutes(max($settings->offsetsMinutes));
                $count = 0;

                foreach ($conversations->upcomingBookedForCurrentTenant($now, $horizon) as $conversation) {
                    $channel = $conversation->channel;
                    // Напоминание можно доставить только в push-канал (Telegram).
                    if ($channel === null || $channel->type !== ChannelType::Telegram) {
                        continue;
                    }

                    foreach ($settings->offsetsMinutes as $offset) {
                        $sent = $conversation->reminders_sent ?? [];
                        if (in_array($offset, $sent, true)) {
                            continue;
                        }
                        // Ещё не наступило время этого напоминания.
                        if ($now->lt($conversation->booked_for->copy()->subMinutes($offset))) {
                            continue;
                        }

                        $conversations->markReminderSent($conversation, $offset);
                        SendAppointmentReminder::dispatch($tenantId, $conversation->id, $this->text($conversation));
                        $count++;
                    }
                }

                return $count;
            });
        });

        $this->info("Поставлено напоминаний в очередь: {$dispatched}.");

        return self::SUCCESS;
    }

    private function text(Conversation $conversation): string
    {
        $when = $conversation->booked_for?->format('d.m').' в '.$conversation->booked_for?->format('H:i');
        $name = $conversation->contact_name !== null && ! in_array($conversation->contact_name, ['Гость', 'Гость сайта'], true)
            ? ', '.$conversation->contact_name
            : '';

        return "Напоминаем{$name} о вашей записи {$when}. Будем ждать вас! Если планы изменились — напишите нам.";
    }
}

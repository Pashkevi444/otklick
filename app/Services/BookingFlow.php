<?php

declare(strict_types=1);

namespace App\Services;

use App\Crm\Contracts\CrmGateway;
use App\Crm\CrmGatewayResolver;
use App\Crm\Data\BookingRequest;
use App\Crm\Data\CrmService;
use App\Crm\Data\CrmStaff;
use App\Crm\Data\SlotQuery;
use App\Crm\Data\TimeSlot;
use App\DTO\BotReply;
use App\DTO\BusinessProfile;
use App\Enums\BookingStep;
use App\Models\Conversation;
use App\Models\CrmConnection;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Support\PhoneExtractor;
use App\Support\RussianDateParser;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Пошаговая запись клиента в CRM («живой» детерминированный сценарий):
 * услуга → мастер (или «любой») → день → конкретное время → телефон → запись.
 *
 * Каталог и слоты берутся ТОЛЬКО из CRM (источник истины), поэтому бот не может
 * предложить несуществующее время: выбранный слот — это строка datetime,
 * полученная от CRM, которую мы без изменений возвращаем при создании записи.
 * Состояние шага хранится в conversations.booking_state.
 *
 * Смену статуса диалога (запись/эскалация) выполняет вызывающий слой по флагам
 * BotReply — здесь только ведём сценарий и чистим состояние.
 *
 * Не final/readonly намеренно — мокается в юнит-тесте BotResponder.
 */
class BookingFlow
{
    /** staff_id «любой свободный» в терминах CRM. */
    private const string ANY_STAFF = '0';

    public function __construct(
        private readonly CrmConnectionRepositoryInterface $connections,
        private readonly CrmGatewayResolver $gateways,
        private readonly ConversationRepositoryInterface $conversations,
    ) {}

    /** Доступна ли автозапись тенанту (есть активное CRM-подключение). */
    public function isAvailable(): bool
    {
        return $this->connections->activeForCurrentTenant() !== null;
    }

    /**
     * Начинает сценарий записи. Возвращает null, если автозапись недоступна
     * (нет подключения) — тогда вызывающий слой откатывается на обычное
     * поведение/эскалацию.
     */
    public function start(Tenant $tenant, Conversation $conversation): ?BotReply
    {
        $connection = $this->connections->activeForCurrentTenant();

        if ($connection === null) {
            return null;
        }

        $gateway = $this->gateways->for($connection->provider);

        try {
            $services = $gateway->services($connection);
        } catch (Throwable $e) {
            report($e);

            return $this->abort($tenant, $conversation);
        }

        if ($services === []) {
            return $this->abort($tenant, $conversation);
        }

        if (count($services) === 1) {
            $state = [
                'step' => BookingStep::Service->value,
                'service_id' => $services[0]->id,
                'service_title' => $services[0]->title,
            ];

            return $this->enterStaff($conversation, $state, $connection, $gateway);
        }

        $state = ['step' => BookingStep::Service->value, 'options' => $this->serviceOptions($services)];
        $this->conversations->setBookingState($conversation, $state);

        return $this->ask("Отлично, запишу вас! 💈\n".$this->menu('Какую услугу вы хотите?', $state['options']));
    }

    /**
     * Обрабатывает очередное сообщение клиента в активном сценарии записи.
     */
    public function advance(Tenant $tenant, Conversation $conversation, string $text): BotReply
    {
        $state = $conversation->booking_state;

        if (! is_array($state) || $state === []) {
            return $this->abort($tenant, $conversation);
        }

        $connection = $this->connections->activeForCurrentTenant();

        if ($connection === null) {
            return $this->abort($tenant, $conversation);
        }

        $gateway = $this->gateways->for($connection->provider);

        try {
            return match (BookingStep::from((string) $state['step'])) {
                BookingStep::Service => $this->onService($conversation, $state, $text, $connection, $gateway),
                BookingStep::Staff => $this->onStaff($conversation, $state, $text, $connection, $gateway),
                BookingStep::Date => $this->onDate($conversation, $state, $text, $connection, $gateway),
                BookingStep::Slot => $this->onSlot($tenant, $conversation, $state, $text, $connection, $gateway),
                BookingStep::Contact => $this->onContact($tenant, $conversation, $state, $text, $connection, $gateway),
            };
        } catch (Throwable $e) {
            report($e);

            return $this->abort($tenant, $conversation);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function onService(Conversation $conversation, array $state, string $text, CrmConnection $connection, CrmGateway $gateway): BotReply
    {
        $choice = $this->match($state['options'] ?? [], $text);

        if ($choice === null) {
            return $this->ask($this->menu('Не понял, выберите услугу номером:', $state['options'] ?? []));
        }

        $state['service_id'] = $choice['id'];
        $state['service_title'] = $choice['title'];

        return $this->enterStaff($conversation, $state, $connection, $gateway);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function onStaff(Conversation $conversation, array $state, string $text, CrmConnection $connection, CrmGateway $gateway): BotReply
    {
        $choice = $this->match($state['options'] ?? [], $text);

        if ($choice === null) {
            return $this->ask($this->menu('Не понял, выберите мастера номером:', $state['options'] ?? []));
        }

        $state['staff_id'] = $choice['id'];
        $state['staff_name'] = $choice['title'];

        return $this->enterDate($conversation, $state);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function onDate(Conversation $conversation, array $state, string $text, CrmConnection $connection, CrmGateway $gateway): BotReply
    {
        $date = RussianDateParser::parse($text, Carbon::now());

        if ($date === null) {
            return $this->ask('Не понял дату. Напишите, пожалуйста, например «завтра» или «18.06».');
        }

        $state['date'] = $date;

        return $this->enterSlot($conversation, $state, $connection, $gateway);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function onSlot(Tenant $tenant, Conversation $conversation, array $state, string $text, CrmConnection $connection, CrmGateway $gateway): BotReply
    {
        $choice = $this->match($state['options'] ?? [], $text);

        if ($choice === null) {
            return $this->ask($this->menu('Не понял, выберите время номером:', $state['options'] ?? []));
        }

        $state['slot'] = $choice['id'];

        if ($conversation->contact_phone === null || $conversation->contact_phone === '') {
            $state['step'] = BookingStep::Contact->value;
            unset($state['options']);
            $this->conversations->setBookingState($conversation, $state);

            return $this->ask('Отлично! Оставьте, пожалуйста, номер телефона для записи — например, +7 999 123-45-67.');
        }

        return $this->book($tenant, $conversation, $state, $connection, $gateway);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function onContact(Tenant $tenant, Conversation $conversation, array $state, string $text, CrmConnection $connection, CrmGateway $gateway): BotReply
    {
        $phone = PhoneExtractor::fromText($text);

        if ($phone === null) {
            return $this->ask('Не вижу номера телефона. Напишите его, пожалуйста — например, +7 999 123-45-67.');
        }

        $this->conversations->setContactPhone($conversation, $phone);

        return $this->book($tenant, $conversation, $state, $connection, $gateway);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function enterStaff(Conversation $conversation, array $state, CrmConnection $connection, CrmGateway $gateway): BotReply
    {
        $staff = $gateway->staff($connection);

        if ($staff === []) {
            // Мастеров нет в каталоге — записываем «к любому».
            $state['staff_id'] = self::ANY_STAFF;
            $state['staff_name'] = 'Любой свободный';

            return $this->enterDate($conversation, $state);
        }

        $state['step'] = BookingStep::Staff->value;
        $state['options'] = $this->staffOptions($staff);
        $this->conversations->setBookingState($conversation, $state);

        return $this->ask($this->menu('К какому мастеру вас записать?', $state['options']));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function enterDate(Conversation $conversation, array $state): BotReply
    {
        $state['step'] = BookingStep::Date->value;
        unset($state['options']);
        $this->conversations->setBookingState($conversation, $state);

        return $this->ask('На какой день вас записать? Напишите, например, «завтра» или «18.06».');
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function enterSlot(Conversation $conversation, array $state, CrmConnection $connection, CrmGateway $gateway): BotReply
    {
        $slots = $gateway->availableSlots($connection, new SlotQuery(
            staffId: (string) $state['staff_id'],
            date: (string) $state['date'],
            serviceId: $state['service_id'] ?? null,
        ));

        if ($slots === []) {
            $state['step'] = BookingStep::Date->value;
            unset($state['options']);
            $this->conversations->setBookingState($conversation, $state);

            return $this->ask('На '.$this->humanDate((string) $state['date']).' нет свободного времени. Назовите, пожалуйста, другой день.');
        }

        $state['step'] = BookingStep::Slot->value;
        $state['options'] = $this->slotOptions($slots);
        $this->conversations->setBookingState($conversation, $state);

        return $this->ask($this->menu('Свободное время на '.$this->humanDate((string) $state['date']).':', $state['options']));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function book(Tenant $tenant, Conversation $conversation, array $state, CrmConnection $connection, CrmGateway $gateway): BotReply
    {
        $result = $gateway->createBooking($connection, new BookingRequest(
            serviceId: (string) $state['service_id'],
            staffId: (string) $state['staff_id'],
            start: (string) $state['slot'],
            clientName: $conversation->contact_name ?? 'Клиент',
            clientPhone: (string) $conversation->contact_phone,
        ));

        $this->conversations->setBookingState($conversation, null);

        // Запись не удалась: честно сообщаем, даём телефон бизнеса и эскалируем —
        // администратор увидит обращение и оформит вручную.
        if (! $result->success) {
            return new BotReply(
                $this->withPhone('К сожалению, не получилось оформить запись автоматически. 😔 Я уже передал заявку администратору — он скоро свяжется с вами и поможет записаться.', $tenant),
                escalate: true,
            );
        }

        return new BotReply($this->confirmation($state), escalate: false, booked: true);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function confirmation(array $state): string
    {
        $time = $this->humanTime((string) $state['slot']);
        $date = $this->humanDate((string) $state['date']);
        $master = ($state['staff_id'] ?? self::ANY_STAFF) !== self::ANY_STAFF && ! empty($state['staff_name'])
            ? " к мастеру {$state['staff_name']}"
            : '';

        return "✅ Готово, записал вас! {$state['service_title']}{$master}, {$date} в {$time}. Будем ждать вас! Если планы изменятся — напишите нам.";
    }

    /**
     * Прерывает сценарий: чистит состояние, даёт телефон бизнеса и эскалирует на
     * администратора.
     */
    private function abort(Tenant $tenant, Conversation $conversation): BotReply
    {
        if (is_array($conversation->booking_state)) {
            $this->conversations->setBookingState($conversation, null);
        }

        return new BotReply(
            $this->withPhone('Секунду — передаю ваш запрос администратору, он поможет с записью.', $tenant),
            escalate: true,
        );
    }

    /**
     * Добавляет к сообщению телефон бизнеса для прямой связи, если он указан.
     */
    private function withPhone(string $text, Tenant $tenant): string
    {
        $phone = BusinessProfile::fromArray($tenant->settings['profile'] ?? [])->phone;

        return $phone !== null && $phone !== ''
            ? $text." Если удобнее — позвоните нам: {$phone}."
            : $text;
    }

    private function ask(string $text): BotReply
    {
        return new BotReply($text, escalate: false);
    }

    /**
     * Сопоставляет ответ клиента пункту меню: по номеру либо по совпадению
     * названия. Возвращает выбранный пункт или null.
     *
     * @param  list<array{id: string, title: string}>  $options
     * @return array{id: string, title: string}|null
     */
    private function match(array $options, string $text): ?array
    {
        $t = trim(mb_strtolower($text));

        if ($t === '' || $options === []) {
            return null;
        }

        if (preg_match('/^\d+$/', $t) === 1) {
            $i = (int) $t;

            return $i >= 1 && $i <= count($options) ? $options[$i - 1] : null;
        }

        foreach ($options as $option) {
            $title = mb_strtolower($option['title']);
            if ($title !== '' && (str_contains($title, $t) || str_contains($t, $title))) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id: string, title: string}>  $options
     */
    private function menu(string $header, array $options): string
    {
        $lines = [$header];

        foreach ($options as $i => $option) {
            $lines[] = ($i + 1).') '.$option['title'];
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<CrmService>  $services
     * @return list<array{id: string, title: string}>
     */
    private function serviceOptions(array $services): array
    {
        return array_map(fn (CrmService $s): array => ['id' => $s->id, 'title' => $s->title], $services);
    }

    /**
     * @param  list<CrmStaff>  $staff
     * @return list<array{id: string, title: string}>
     */
    private function staffOptions(array $staff): array
    {
        $options = [['id' => self::ANY_STAFF, 'title' => 'Любой свободный']];

        foreach ($staff as $member) {
            $options[] = ['id' => $member->id, 'title' => $member->name];
        }

        return $options;
    }

    /**
     * @param  list<TimeSlot>  $slots
     * @return list<array{id: string, title: string}>
     */
    private function slotOptions(array $slots): array
    {
        return array_map(fn (TimeSlot $slot): array => [
            'id' => $slot->start,
            'title' => $this->humanTime($slot->start),
        ], $slots);
    }

    private function humanTime(string $iso): string
    {
        try {
            return Carbon::parse($iso)->format('H:i');
        } catch (Throwable) {
            return $iso;
        }
    }

    private function humanDate(string $date): string
    {
        try {
            return Carbon::parse($date)->format('d.m');
        } catch (Throwable) {
            return $date;
        }
    }
}

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
use App\Llm\Contracts\LlmClient;
use App\Models\Conversation;
use App\Models\CrmConnection;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Support\PhoneExtractor;
use App\Support\RussianDateParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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
        private readonly LlmClient $llm,
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
            $this->log('start.no_connection', $conversation);

            return null;
        }

        $gateway = $this->gateways->for($connection->provider);

        try {
            $services = $gateway->services($connection);
            $this->log('start', $conversation, ['provider' => $connection->provider->value, 'services' => count($services)]);
        } catch (Throwable $e) {
            report($e);
            $this->log('start.services_failed', $conversation, ['error' => $e->getMessage()]);

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
        $step = BookingStep::from((string) $state['step']);
        $this->log('advance', $conversation, ['step' => $step->value, 'text' => $text]);

        try {
            return match ($step) {
                BookingStep::Service => $this->onService($conversation, $state, $text, $connection, $gateway),
                BookingStep::Staff => $this->onStaff($conversation, $state, $text, $connection, $gateway),
                BookingStep::Date => $this->onDate($conversation, $state, $text, $connection, $gateway),
                BookingStep::Slot => $this->onSlot($tenant, $conversation, $state, $text, $connection, $gateway),
                BookingStep::Contact => $this->onContact($tenant, $conversation, $state, $text, $connection, $gateway),
            };
        } catch (Throwable $e) {
            report($e);
            $this->log('advance.failed', $conversation, ['step' => $step->value, 'error' => $e->getMessage()]);

            return $this->abort($tenant, $conversation);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function onService(Conversation $conversation, array $state, string $text, CrmConnection $connection, CrmGateway $gateway): BotReply
    {
        $choice = $this->resolveChoice($state['options'] ?? [], $text);

        if ($choice === null) {
            return $this->ask($this->menu('Уточните, пожалуйста, какую услугу — можно номером:', $state['options'] ?? []));
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
        $choice = $this->resolveChoice($state['options'] ?? [], $text);

        if ($choice === null) {
            return $this->ask($this->menu('Уточните, пожалуйста, к какому мастеру — можно номером:', $state['options'] ?? []));
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
        // Сначала детерминированный разбор, затем — ИИ для свободных формулировок
        // («на следующей неделе в среду», «через пару дней»).
        $date = RussianDateParser::parse($text, Carbon::now()) ?? $this->parseDateWithLlm($text, Carbon::now());

        if ($date === null) {
            return $this->ask('Подскажите, пожалуйста, день записи — например, «завтра», «в субботу» или «18.06».');
        }

        $state['date'] = $date;

        return $this->enterSlot($conversation, $state, $connection, $gateway);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function onSlot(Tenant $tenant, Conversation $conversation, array $state, string $text, CrmConnection $connection, CrmGateway $gateway): BotReply
    {
        $choice = $this->resolveChoice($state['options'] ?? [], $text);

        if ($choice === null) {
            return $this->ask($this->menu('Уточните, пожалуйста, время — можно номером:', $state['options'] ?? []));
        }

        $state['slot'] = $choice['id'];

        // Перед записью обязательно имя и телефон.
        if (! $this->hasName($conversation) || ! $this->hasPhone($conversation)) {
            $state['step'] = BookingStep::Contact->value;
            unset($state['options']);
            $this->conversations->setBookingState($conversation, $state);

            return $this->ask($this->contactPrompt($conversation));
        }

        return $this->book($tenant, $conversation, $state, $connection, $gateway);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function onContact(Tenant $tenant, Conversation $conversation, array $state, string $text, CrmConnection $connection, CrmGateway $gateway): BotReply
    {
        // Имя/телефон мог уже распознать ContactCapture (ИИ) в вызывающем слое;
        // здесь — детерминированный фолбэк, чтобы запись точно получила оба поля.
        if (! $this->hasPhone($conversation)) {
            $phone = PhoneExtractor::fromText($text);
            if ($phone !== null) {
                $this->conversations->setContactPhone($conversation, $phone);
            }
        }

        if (! $this->hasName($conversation)) {
            $name = $this->extractName($text);
            if ($name !== null) {
                $this->conversations->setContactName($conversation, $name);
            }
        }

        // Чего-то всё ещё не хватает — переспрашиваем, но не зацикливаемся:
        // после нескольких попыток передаём администратору.
        if (! $this->hasName($conversation) || ! $this->hasPhone($conversation)) {
            $attempts = (int) ($state['contact_attempts'] ?? 0) + 1;

            if ($attempts >= 3) {
                return $this->abort($tenant, $conversation);
            }

            $state['contact_attempts'] = $attempts;
            $this->conversations->setBookingState($conversation, $state);

            return $this->ask($this->contactPrompt($conversation));
        }

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
        $this->log('slots', $conversation, ['date' => $state['date'], 'staff_id' => $state['staff_id'], 'count' => count($slots)]);

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
        $this->log('create_request', $conversation, [
            'service_id' => $state['service_id'] ?? null,
            'staff_id' => $state['staff_id'] ?? null,
            'slot' => $state['slot'] ?? null,
        ]);

        $result = $gateway->createBooking($connection, new BookingRequest(
            serviceId: (string) $state['service_id'],
            staffId: (string) $state['staff_id'],
            start: (string) $state['slot'],
            clientName: $conversation->contact_name ?? 'Клиент',
            clientPhone: (string) $conversation->contact_phone,
        ));

        $this->conversations->setBookingState($conversation, null);
        $this->log('create_result', $conversation, [
            'success' => $result->success,
            'external_id' => $result->externalId,
            'message' => $result->message,
        ]);

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

    /** Есть ли у клиента указанное имя (а не плейсхолдер «Гость»). */
    private function hasName(Conversation $conversation): bool
    {
        $name = $conversation->contact_name;

        return $name !== null && $name !== '' && ! in_array($name, ['Гость', 'Гость сайта'], true);
    }

    private function hasPhone(Conversation $conversation): bool
    {
        return $conversation->contact_phone !== null && $conversation->contact_phone !== '';
    }

    /** Запрос недостающих контактов (имя и/или телефон) для записи. */
    private function contactPrompt(Conversation $conversation): string
    {
        $needName = ! $this->hasName($conversation);
        $needPhone = ! $this->hasPhone($conversation);

        if ($needName && $needPhone) {
            return 'Чтобы записать вас, подскажите, пожалуйста, как вас зовут и оставьте номер телефона для связи (например, Павел, +7 999 123-45-67).';
        }

        if ($needName) {
            return 'Как вас зовут? Имя нужно для записи.';
        }

        return 'Оставьте, пожалуйста, номер телефона для записи — например, +7 999 123-45-67.';
    }

    /**
     * Детерминированный фолбэк извлечения имени из ответа на вопрос «Как вас
     * зовут?»: убираем телефон и вводные слова, берём оставшееся имя.
     */
    private function extractName(string $text): ?string
    {
        // Убираем телефоноподобные последовательности.
        $t = (string) preg_replace('/[\d\-+()]{5,}/u', ' ', $text);
        // Убираем вводные («меня зовут», «зовут», «это», «я»).
        $t = (string) preg_replace('/\b(меня\s+зовут|зовут|это|я)\b/iu', ' ', $t);
        $t = trim((string) preg_replace('/[^\p{L}\s-]+/u', ' ', $t));

        if (preg_match('/\p{L}[\p{L}-]+(?:\s+\p{L}[\p{L}-]+)?/u', $t, $m) !== 1) {
            return null;
        }

        return mb_convert_case(trim($m[0]), MB_CASE_TITLE, 'UTF-8');
    }

    private function ask(string $text): BotReply
    {
        return new BotReply($text, escalate: false);
    }

    /**
     * Распознаёт выбор клиента: сначала детерминированно (номер/совпадение
     * названия), затем — ИИ для свободных формулировок («давайте к савелию»,
     * «можно фейд»). Возвращает выбранный пункт или null.
     *
     * @param  list<array{id: string, title: string}>  $options
     * @return array{id: string, title: string}|null
     */
    private function resolveChoice(array $options, string $text): ?array
    {
        return $this->match($options, $text) ?? $this->matchWithLlm($options, $text);
    }

    /**
     * ИИ-резолвер: просим модель выбрать номер пункта по свободному ответу.
     * Возвращает пункт или null (в т.ч. при сбое LLM или «ничего не подходит»).
     *
     * @param  list<array{id: string, title: string}>  $options
     * @return array{id: string, title: string}|null
     */
    private function matchWithLlm(array $options, string $text): ?array
    {
        if ($options === []) {
            return null;
        }

        $system = 'Клиент выбирает один пункт из списка. Он может написать с опечаткой, сокращением или своими словами '
            .'(«к савелею», «мужскую», «фейт»), не дословно как в списке. Определи по смыслу, какой пункт он имел в виду, '
            ."и верни ТОЛЬКО номер этого пункта одной цифрой. Если уверенно сопоставить нельзя — верни 0.\nСписок:\n"
            .$this->menu('', $options);

        try {
            $answer = $this->llm->generate($system, [['role' => 'user', 'content' => $text]]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (preg_match('/\d+/', $answer, $m) === 1) {
            $i = (int) $m[0];

            if ($i >= 1 && $i <= count($options)) {
                return $options[$i - 1];
            }
        }

        return null;
    }

    /**
     * ИИ-фолбэк разбора даты, когда детерминированный парсер не справился.
     * Возвращает дату Y-m-d или null.
     */
    private function parseDateWithLlm(string $text, Carbon $today): ?string
    {
        $system = sprintf(
            'Ты определяешь дату записи. Для отсчёта: сегодня %s, завтра %s, послезавтра %s. '
            .'Клиент пишет желаемый день в свободной форме и может допустить опечатку, сокращение или разговорную форму. '
            .'Пойми по смыслу, какой реальный день он имел в виду, и верни ТОЛЬКО эту дату в формате YYYY-MM-DD, без слов. '
            .'Если из-за опечатки или неоднозначности день понять невозможно — верни NONE (тогда бот переспросит).',
            $today->format('Y-m-d'),
            $today->copy()->addDay()->format('Y-m-d'),
            $today->copy()->addDays(2)->format('Y-m-d'),
        );

        try {
            $answer = $this->llm->generate($system, [['role' => 'user', 'content' => $text]]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $answer, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return null;
    }

    /**
     * Структурный лог события записи (для разбора проблем по конкретному клиенту).
     *
     * @param  array<string, mixed>  $context
     */
    private function log(string $event, Conversation $conversation, array $context = []): void
    {
        Log::info("booking.{$event}", [
            'conversation_id' => $conversation->id ?? null,
            ...$context,
        ]);
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

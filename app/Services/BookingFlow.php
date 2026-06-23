<?php

declare(strict_types=1);

namespace App\Services;

use App\Booking\BookingGatewayResolver;
use App\Booking\Contracts\BookingGateway;
use App\Booking\Data\BookingRequest;
use App\Booking\Data\BookingResult;
use App\Booking\Data\CrmService;
use App\Booking\Data\CrmStaff;
use App\Booking\Data\SlotQuery;
use App\Booking\Data\TimeSlot;
use App\DTO\BotReply;
use App\DTO\BusinessProfile;
use App\DTO\ReplyKeyboard;
use App\Enums\BookingStep;
use App\Llm\Contracts\LlmClient;
use App\Models\Conversation;
use App\Models\CrmConnection;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Support\PhoneExtractor;
use App\Support\RussianDateParser;
use App\Tenancy\TestContext;
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

    /** Сколько свободных окон показывать списком (больше — просим назвать время). */
    private const int SLOTS_TO_LIST = 6;

    /** Горизонт кликабельного календаря (дней вперёд, считая сегодня). */
    private const int DATE_BUTTONS = 14;

    /** Короткие подписи дней недели для кнопок календаря (ISO: Пн=1…Вс=7). */
    private const array WEEKDAY_ABBR = [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];

    /** Мета-намерения клиента, перебивающие обычный сценарий записи. */
    private const string INTENT_CANCEL = 'cancel';

    private const string INTENT_RESCHEDULE = 'reschedule';

    public function __construct(
        private readonly CrmConnectionRepositoryInterface $connections,
        private readonly BookingGatewayResolver $gateways,
        private readonly ConversationRepositoryInterface $conversations,
        private readonly LlmClient $llm,
        private readonly ClientService $clients,
    ) {}

    /**
     * Тестовый прогон бота (песочница): реальные обращения в CRM (создание/отмена
     * записи) не делаем — имитируем успех, чтобы бизнес прошёл сценарий целиком,
     * не плодя настоящих записей в YClients.
     */
    private function inSandbox(): bool
    {
        return app(TestContext::class)->active();
    }

    /** Доступна ли автозапись тенанту (есть активное CRM-подключение). */
    public function isAvailable(): bool
    {
        return $this->connections->activeForCurrentTenant() !== null;
    }

    /**
     * Начинает сценарий записи. Возвращает null, если автозапись недоступна
     * (нет подключения) — тогда вызывающий слой откатывается на обычное
     * поведение/эскалацию.
     *
     * $supersedesRecordId — id ранее оформленной записи, которую этот сценарий
     * заменяет (перенос/перезапись): её отменяем в CRM только после успешного
     * создания новой, чтобы клиент не остался без слота, если передумает.
     */
    public function start(Tenant $tenant, Conversation $conversation, ?string $supersedesRecordId = null): ?BotReply
    {
        // Запись на услугу — возможность CRM-интеграции (сейчас YClients). Нет права
        // на CRM (тариф/оверрайд СУ) → запись недоступна, диалог уходит на человека.
        // Так интеграция «резко отключаема» правом, а не только наличием подключения.
        if (! $tenant->features()->crm) {
            $this->log('start.no_crm_feature', $conversation);

            return null;
        }

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
                'service_prices' => $this->servicePrices($services),
                'supersedes_record_id' => $supersedesRecordId,
                // Телефон уже известен на старте — клиент вернувшийся, его номер
                // перед записью подтвердим (вдруг сменился).
                'confirm_phone' => $this->isReturning($conversation),
            ];

            return $this->enterStaff($conversation, $state, $connection, $gateway);
        }

        $state = [
            'step' => BookingStep::Service->value,
            'options' => $this->serviceOptions($services),
            'service_prices' => $this->servicePrices($services),
            'supersedes_record_id' => $supersedesRecordId,
            'confirm_phone' => $this->isReturning($conversation),
        ];
        $this->conversations->setBookingState($conversation, $state);

        return $this->ask("Отлично, запишу вас! 💈\n".$this->menu('Какую услугу вы хотите?', $state['options']), $this->optionsKeyboard($state['options'], 2));
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

        // Право на CRM могли отозвать посреди сценария — дальше не записываем,
        // передаём администратору (запись «резко отключаема» правом и в середине).
        if (! $tenant->features()->crm) {
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
                BookingStep::ConfirmContact => $this->onConfirmContact($tenant, $conversation, $state, $text, $connection, $gateway),
            };
        } catch (Throwable $e) {
            report($e);
            $this->log('advance.failed', $conversation, ['step' => $step->value, 'error' => $e->getMessage()]);

            return $this->abort($tenant, $conversation);
        }
    }

    /**
     * Перехватывает мета-намерение клиента «отменить» / «перенести (перезаписать)»
     * запись. Работает И во время активного мастера записи, И вне его, поэтому
     * клиент никогда не «застревает» на текущем шаге, как было раньше (на «отмени
     * запись» бот талдычил «подскажите день»).
     *
     * Возвращает готовый ответ, если намерение распознано и обработано; иначе
     * null — сообщение обрабатывается обычным потоком (мастер записи / бот по БЗ).
     */
    public function interceptIntent(Tenant $tenant, Conversation $conversation, string $text): ?BotReply
    {
        $intent = $this->detectIntent($text);

        if ($intent === null) {
            return null;
        }

        $activeFlow = is_array($conversation->booking_state) && $conversation->booking_state !== [];
        $booked = $this->conversations->lastWithCrmRecordForChat(
            (string) $conversation->channel_id,
            (string) $conversation->external_chat_id,
        );
        $existingRecordId = $booked instanceof Conversation ? $booked->crm_record_id : null;
        $this->log('intent', $conversation, ['intent' => $intent, 'has_booking' => $existingRecordId !== null, 'active_flow' => $activeFlow]);

        if ($intent === self::INTENT_CANCEL) {
            // Бросаем недооформленный мастер записи (если был).
            if ($activeFlow) {
                $this->conversations->setBookingState($conversation, null);
            }

            // Отменять нечего: если шёл мастер — вежливо выходим; иначе пусть
            // отвечает обычный бот (вдруг «отмена» вообще не про запись).
            if ($existingRecordId === null) {
                return $activeFlow
                    ? $this->ask('Хорошо, не записываю. Если передумаете — просто напишите, и я подберу время. 🙂')
                    : null;
            }

            // Отменяем в CRM СРАЗУ и честно: подтверждаем только при успехе. Если
            // CRM-отмена не прошла (напр. нет partner-token YClients) — НЕ врём
            // клиенту «отменил», а передаём администратору.
            if ($this->cancelLastBooking($conversation)) {
                return new BotReply(
                    'Готово, отменил вашу запись. Если захотите записаться снова — просто напишите, я помогу. 🙌',
                    escalate: false,
                    cancelled: true,
                );
            }

            return new BotReply(
                $this->withPhone('Не получилось отменить запись автоматически. 😔 Передаю администратору — он отменит её и свяжется с вами.', $tenant),
                escalate: true,
            );
        }

        // INTENT_RESCHEDULE — только ВНЕ активного мастера записи. Внутри мастера
        // «перенеси на 14» / «перенесите на пятницу» — это ответ на текущий шаг,
        // а не перезапуск всего сценария; пусть его разберёт advance().
        if ($activeFlow) {
            return null;
        }

        // Начинаем запись заново; прежнюю (если есть) заменим — отменим в CRM
        // только после успешного создания новой.
        $reply = $this->start($tenant, $conversation, $existingRecordId);

        if ($reply === null) {
            // Автозапись недоступна — передаём администратору.
            return $this->abort($tenant, $conversation);
        }

        $lead = $existingRecordId !== null
            ? 'Конечно, перенесём запись — подберём новое время. '
            : 'Хорошо, запишемся заново. ';

        return new BotReply($lead.$reply->text, escalate: $reply->escalate, booked: $reply->booked);
    }

    /**
     * Меню для вернувшегося клиента, у которого УЖЕ есть предстоящая запись:
     * перечисляем её и предлагаем перенести / отменить / записаться ещё раз
     * (кликабельными кнопками). Возвращает null, если активных записей нет —
     * тогда заводим обычную новую запись.
     */
    public function bookingChoiceMenu(Conversation $conversation): ?BotReply
    {
        $active = $this->conversations->activeBookingsForChat(
            (string) $conversation->channel_id,
            (string) $conversation->external_chat_id,
        );

        if ($active->isEmpty()) {
            return null;
        }

        $lines = $active->map(fn (Conversation $c): string => '• '.$this->describeBooking($c))->all();
        $word = $active->count() === 1 ? 'запись' : 'записи';

        return new BotReply(
            "У вас уже есть {$word}:\n".implode("\n", $lines).
            "\n\nХотите перенести, отменить или записаться ещё раз?",
            escalate: false,
            keyboard: new ReplyKeyboard([['Перенести запись', 'Отменить запись'], ['Новая запись']]),
        );
    }

    /** Человекочитаемое описание записи: услуга + дата и время визита. */
    private function describeBooking(Conversation $conversation): string
    {
        $service = $conversation->booked_service_title ?? 'Запись';
        $when = $conversation->booked_for !== null
            ? $conversation->booked_for->format('d.m').' в '.$conversation->booked_for->format('H:i')
            : '';

        return $when !== '' ? "{$service} — {$when}" : $service;
    }

    /**
     * Распознаёт мета-намерение по тексту (детерминированно, без LLM — дёшево и
     * без задержки на каждое сообщение). Возвращает INTENT_CANCEL, INTENT_RESCHEDULE
     * или null. Отмена приоритетнее переноса при явном отказе («не хочу
     * записываться»), иначе срабатывает перенос.
     */
    private function detectIntent(string $text): ?string
    {
        $t = mb_strtolower(trim($text));

        if ($t === '') {
            return null;
        }

        $has = static function (string $haystack, string ...$needles): bool {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return true;
                }
            }

            return false;
        };

        // Явная отмена (или отказ от записи) — раньше переноса. «передумал» сюда
        // НЕ относим: «передумал, сделай новую запись» — это перенос (ниже).
        if ($has($t, 'отмени', 'отмена', 'отмену', 'отменит', 'отменя', 'аннулир')
            || ($has($t, 'не надо', 'не хоч', 'не буд', 'отказ') && $has($t, 'запис', 'запиш', 'брон'))) {
            return self::INTENT_CANCEL;
        }

        // Перенос / перезапись существующей записи.
        if ($has($t, 'перенес', 'перенест', 'перезапиш', 'перезаписа', 'переписа на')
            || (str_contains($t, 'передума') && $has($t, 'запиш', 'запис', 'нов', 'друг'))
            || ($has($t, 'поменя', 'измен', 'сдвин', 'на друг') && $has($t, 'запис', 'врем', 'дат', 'день', 'час'))) {
            return self::INTENT_RESCHEDULE;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function onService(Conversation $conversation, array $state, string $text, CrmConnection $connection, BookingGateway $gateway): BotReply
    {
        $choice = $this->resolveChoice($state['options'] ?? [], $text);

        if ($choice === null) {
            return $this->ask($this->menu('Уточните, пожалуйста, какую услугу — можно номером:', $state['options'] ?? []), $this->optionsKeyboard($state['options'] ?? [], 2));
        }

        $state['service_id'] = $choice['id'];
        $state['service_title'] = $choice['title'];

        return $this->enterStaff($conversation, $state, $connection, $gateway);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function onStaff(Conversation $conversation, array $state, string $text, CrmConnection $connection, BookingGateway $gateway): BotReply
    {
        $choice = $this->resolveChoice($state['options'] ?? [], $text);

        if ($choice === null) {
            return $this->ask($this->menu('Уточните, пожалуйста, к какому мастеру — можно номером:', $state['options'] ?? []), $this->optionsKeyboard($state['options'] ?? [], 2));
        }

        $state['staff_id'] = $choice['id'];
        $state['staff_name'] = $choice['title'];

        return $this->enterDate($conversation, $state);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function onDate(Conversation $conversation, array $state, string $text, CrmConnection $connection, BookingGateway $gateway): BotReply
    {
        // «в 15» / «к 18» — это ВРЕМЯ, а не день. Не отдаём в ИИ (он мог вернуть
        // 15-е число) — просим сначала выбрать день.
        if (preg_match('/^\s*(в|к)\s+\d{1,2}([:.]\d{2})?\s*$/u', mb_strtolower($text)) === 1
            && RussianDateParser::parse($text, Carbon::now()) === null) {
            return $this->ask('Это похоже на время. 🙂 Сначала выберите ДЕНЬ записи на клавиатуре, потом подберём время.', $this->dateKeyboard());
        }

        // Сначала детерминированный разбор, затем — ИИ для свободных формулировок
        // («на следующей неделе в среду», «через пару дней»).
        $date = RussianDateParser::parse($text, Carbon::now()) ?? $this->parseDateWithLlm($text, Carbon::now());

        if ($date === null) {
            return $this->ask('Подскажите, пожалуйста, день записи — выберите на клавиатуре или напишите «18.06».', $this->dateKeyboard());
        }

        // Прошедший день: в CRM за расписанием не сходишь (вернёт 422), да и
        // записать в прошлое нельзя — просим будущую дату, а не падаем/эскалируем.
        if (Carbon::parse($date)->startOfDay()->lt(Carbon::now()->startOfDay())) {
            return $this->ask('Это уже прошедший день. 🙂 Выберите, пожалуйста, будущую дату на клавиатуре.', $this->dateKeyboard());
        }

        $state['date'] = $date;

        return $this->enterSlot($conversation, $state, $connection, $gateway);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function onSlot(Tenant $tenant, Conversation $conversation, array $state, string $text, CrmConnection $connection, BookingGateway $gateway): BotReply
    {
        // На шаге времени голое число — это ЧАС («14» = 14:00), а не номер пункта.
        // Иначе бронировали не то время (прод-баг). Если час не нашёлся среди окон —
        // откатываемся к обычному разбору (номер из короткого списка/название).
        $options = $state['options'] ?? [];
        $choice = $this->matchSlotByTime($options, $text) ?? $this->resolveChoice($options, $text);

        if ($choice === null) {
            // Время суток («после обеда», «утром», «вечером») — сужаем окна до
            // этого периода и просим выбрать конкретное, а не «не нашёл».
            $daypart = $this->filterSlotsByDaypart($options, $text);
            if ($daypart !== []) {
                $shown = array_slice($daypart, 0, 8);

                return $this->ask($this->menu('Свободное время в этот период — выберите:', $shown), $this->optionsKeyboard($shown, 3));
            }

            // Названное время не подошло — показываем компактный список ближайших окон.
            $preview = array_slice($options, 0, self::SLOTS_TO_LIST);

            return $this->ask($this->menu('Не нашёл такого свободного времени. Вот ближайшие окна (нажмите время или напишите):', $preview), $this->optionsKeyboard($preview, 3));
        }

        $state['slot'] = $choice['id'];

        // Перед записью обязательно имя и телефон.
        if (! $this->hasName($conversation) || ! $this->hasPhone($conversation)) {
            $state['step'] = BookingStep::Contact->value;
            unset($state['options']);
            $this->conversations->setBookingState($conversation, $state);

            return $this->ask($this->contactPrompt($conversation));
        }

        // Вернувшийся клиент: телефон уже был известен на старте — подтверждаем
        // его (вдруг сменился), прежде чем записать.
        if (! empty($state['confirm_phone'])) {
            $state['step'] = BookingStep::ConfirmContact->value;
            unset($state['options']);
            $this->conversations->setBookingState($conversation, $state);

            return $this->ask(
                "Записываю вас, {$conversation->displayName()}. 📞 Ваш телефон всё ещё {$conversation->displayPhone()}? Если поменялся — пришлите новый номер, иначе нажмите «Да».",
                new ReplyKeyboard([['Да, всё верно']]),
            );
        }

        return $this->book($tenant, $conversation, $state, $connection, $gateway);
    }

    /**
     * Подтверждение телефона у вернувшегося клиента: прислал новый номер —
     * обновляем и записываем; подтвердил («да») — записываем на текущий; сказал,
     * что номер не тот, но нового не дал — переспрашиваем (не бронируем на старый).
     *
     * @param  array<string, mixed>  $state
     */
    private function onConfirmContact(Tenant $tenant, Conversation $conversation, array $state, string $text, CrmConnection $connection, BookingGateway $gateway): BotReply
    {
        $phone = PhoneExtractor::fromText($text);

        if ($phone !== null) {
            if ($phone !== $conversation->displayPhone()) {
                $this->clients->recordPhone($conversation, $phone);
                $this->log('phone_updated', $conversation);
            }

            return $this->book($tenant, $conversation, $state, $connection, $gateway);
        }

        // Номера в ответе нет. Явное подтверждение — записываем на текущий.
        $affirmative = preg_match('/(^|\W)(да|ага|угу|верно|правильн|всё\s+верно|тот\s+же|актуал|подтвержд|ок|норм)(\W|$)/u', mb_strtolower($text)) === 1;

        // Один раз переспрашиваем новый номер; повторно — не зацикливаемся.
        if (! $affirmative && empty($state['confirm_reasked'])) {
            $state['confirm_reasked'] = true;
            $this->conversations->setBookingState($conversation, $state);

            return $this->ask('Подскажите, пожалуйста, ваш актуальный номер телефона — например, +7 999 123-45-67.');
        }

        return $this->book($tenant, $conversation, $state, $connection, $gateway);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function onContact(Tenant $tenant, Conversation $conversation, array $state, string $text, CrmConnection $connection, BookingGateway $gateway): BotReply
    {
        // Имя/телефон мог уже распознать ContactCapture (ИИ) в вызывающем слое;
        // здесь — детерминированный фолбэк, чтобы запись точно получила оба поля.
        if (! $this->hasPhone($conversation)) {
            $phone = PhoneExtractor::fromText($text);
            if ($phone !== null) {
                $this->clients->recordPhone($conversation, $phone);
            }
        }

        if (! $this->hasName($conversation)) {
            $name = $this->extractName($text);
            if ($name !== null) {
                $this->clients->recordName($conversation, $name);
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
    private function enterStaff(Conversation $conversation, array $state, CrmConnection $connection, BookingGateway $gateway): BotReply
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

        return $this->ask($this->menu('К какому мастеру вас записать?', $state['options']), $this->optionsKeyboard($state['options'], 2));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function enterDate(Conversation $conversation, array $state): BotReply
    {
        $state['step'] = BookingStep::Date->value;
        unset($state['options']);
        $this->conversations->setBookingState($conversation, $state);

        return $this->ask('На какой день вас записать? Выберите день на клавиатуре или напишите, например «18.06».', $this->dateKeyboard());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function enterSlot(Conversation $conversation, array $state, CrmConnection $connection, BookingGateway $gateway): BotReply
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

            return $this->ask('На '.$this->humanDate((string) $state['date']).' нет свободного времени. Выберите, пожалуйста, другой день.', $this->dateKeyboard());
        }

        $state['step'] = BookingStep::Slot->value;
        $state['options'] = $this->slotOptions($slots);
        $this->conversations->setBookingState($conversation, $state);

        $date = $this->humanDate((string) $state['date']);
        // Время — кликабельными кнопками (4 в ряд); их же можно набрать текстом.
        $keyboard = $this->optionsKeyboard($state['options'], 4);

        // Мало окон — показываем списком в тексте; много — не спамим перечнем, а
        // даём диапазон в тексте, при этом все окна доступны кнопками.
        if (count($state['options']) <= self::SLOTS_TO_LIST) {
            return $this->ask($this->menu("Свободное время на {$date} — выберите:", $state['options']), $keyboard);
        }

        $first = $state['options'][0]['title'];
        $last = $state['options'][count($state['options']) - 1]['title'];

        return $this->ask("На {$date} свободно с {$first} до {$last}. Выберите удобное время на клавиатуре или напишите, например «{$first}».", $keyboard);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function book(Tenant $tenant, Conversation $conversation, array $state, CrmConnection $connection, BookingGateway $gateway): BotReply
    {
        $this->log('create_request', $conversation, [
            'service_id' => $state['service_id'] ?? null,
            'staff_id' => $state['staff_id'] ?? null,
            'slot' => $state['slot'] ?? null,
        ]);

        $result = $this->inSandbox()
            ? BookingResult::ok('sandbox')
            : $gateway->createBooking($connection, new BookingRequest(
                serviceId: (string) $state['service_id'],
                staffId: (string) $state['staff_id'],
                start: (string) $state['slot'],
                clientName: $conversation->displayName() ?? 'Клиент',
                clientPhone: (string) $conversation->displayPhone(),
            ));

        $this->conversations->setBookingState($conversation, null);
        $this->log('create_result', $conversation, [
            'success' => $result->success,
            'external_id' => $result->externalId,
            'message' => $result->message,
        ]);

        // Запись не удалась: честно сообщаем, даём телефон бизнеса и эскалируем —
        // администратор увидит обращение и оформит вручную. Прежнюю запись (при
        // переносе) НЕ трогаем — клиент остаётся со старым слотом, а не без слота.
        if (! $result->success) {
            return new BotReply(
                $this->withPhone('К сожалению, не получилось оформить запись автоматически. 😔 Я уже передал заявку администратору — он скоро свяжется с вами и поможет записаться.', $tenant),
                escalate: true,
            );
        }

        // Перенос: новая запись создана — теперь отменяем прежнюю в CRM. Делаем
        // это ДО сохранения нового id, чтобы поиск «последней записи чата» нашёл
        // именно старую (а не только что созданную).
        if (! empty($state['supersedes_record_id'])) {
            $this->cancelSuperseded($conversation, $connection, $gateway, (string) $state['supersedes_record_id']);
        }

        // Сохраняем id записи в CRM — пригодится, если клиент попросит её отменить.
        if ($result->externalId !== null) {
            $this->conversations->setCrmRecordId($conversation, $result->externalId);
        }

        // Снимок ценности для «Отчёта ценности»: в какую CRM ушла запись + услуга
        // и её цена на момент записи (выручка считается точно по записям).
        $this->conversations->recordBookingValue(
            $conversation,
            (string) $connection->id,
            isset($state['service_id']) ? (string) $state['service_id'] : null,
            isset($state['service_title']) ? (string) $state['service_title'] : null,
            $this->bookedPrice($state),
        );

        // Время визита — для напоминаний клиенту (слот пришёл из CRM как есть).
        try {
            $this->conversations->setBookedFor($conversation, Carbon::parse((string) $state['slot']));
        } catch (Throwable) {
            // некорректный слот — без напоминаний, запись всё равно оформлена
        }

        return new BotReply($this->confirmation($state), escalate: false, booked: true);
    }

    /**
     * Отменяет в CRM последнюю запись этого чата (по просьбе клиента, сигнал
     * [[CANCELLED]]). Закрытие диалога делает вызывающий слой; здесь — только CRM.
     */
    /**
     * Отменяет в CRM последнюю запись чата. Возвращает true, если отменять нечего
     * или отмена прошла успешно; false — если CRM-отмена сорвалась (тогда
     * вызывающий слой не должен подтверждать клиенту отмену).
     */
    public function cancelLastBooking(Conversation $conversation): bool
    {
        $connection = $this->connections->activeForCurrentTenant();

        if ($connection === null) {
            return false;
        }

        $booked = $this->conversations->lastWithCrmRecordForChat(
            (string) $conversation->channel_id,
            (string) $conversation->external_chat_id,
        );

        if (! $booked instanceof Conversation || $booked->crm_record_id === null) {
            return true; // отменять нечего (записи нет или уже снята)
        }

        $recordId = $booked->crm_record_id;
        $result = $this->inSandbox()
            ? BookingResult::ok($recordId)
            : $this->gateways->for($connection->provider)->cancelBooking($connection, $recordId);

        Log::info('booking.cancel_result', [
            'conversation_id' => $booked->id ?? null,
            'record_id' => $recordId,
            'success' => $result->success,
        ]);

        // Успешно отменили — снимаем id, чтобы не пытаться отменить повторно.
        if ($result->success) {
            $this->conversations->setCrmRecordId($booked, null);

            return true;
        }

        return false;
    }

    /**
     * Отменяет в CRM прежнюю запись чата при переносе (после успешного создания
     * новой) и снимает её id с того диалога, где она висела. Сбой отмены не
     * валит запись — новая уже создана; просто логируем (старая «зависнет», её
     * увидит администратор).
     */
    private function cancelSuperseded(Conversation $conversation, CrmConnection $connection, BookingGateway $gateway, string $recordId): void
    {
        try {
            $result = $this->inSandbox()
                ? BookingResult::ok($recordId)
                : $gateway->cancelBooking($connection, $recordId);
            $this->log('supersede_cancel', $conversation, ['record_id' => $recordId, 'success' => $result->success]);
        } catch (Throwable $e) {
            report($e);
            $this->log('supersede_cancel.failed', $conversation, ['record_id' => $recordId, 'error' => $e->getMessage()]);

            return;
        }

        if (! $result->success) {
            return;
        }

        // Снимаем id с прежнего диалога этого чата (его запись теперь отменена).
        $old = $this->conversations->lastWithCrmRecordForChat(
            (string) $conversation->channel_id,
            (string) $conversation->external_chat_id,
        );

        if ($old instanceof Conversation && $old->crm_record_id === $recordId) {
            $this->conversations->setCrmRecordId($old, null);
        }
    }

    /**
     * Отменяет в CRM запись КОНКРЕТНОГО лида — по его собственной привязке
     * (`crm_connection_id` + `crm_record_id`), а не по «активной» CRM. Так
     * корректно работает и при нескольких CRM у бизнеса. Без записи — ничего не
     * делает. При успехе снимает `crm_record_id` (повторно не отменяем).
     */
    public function cancelBookingForConversation(Conversation $conversation): void
    {
        if ($conversation->crm_record_id === null || $conversation->crm_connection_id === null) {
            return;
        }

        $connection = $this->connections->find($conversation->crm_connection_id);
        if ($connection === null) {
            return;
        }

        $result = $this->inSandbox()
            ? BookingResult::ok($conversation->crm_record_id)
            : $this->gateways->for($connection->provider)->cancelBooking($connection, $conversation->crm_record_id);

        Log::info('booking.cancel_for_conversation', [
            'conversation_id' => $conversation->id ?? null,
            'crm_connection_id' => $conversation->crm_connection_id,
            'record_id' => $conversation->crm_record_id,
            'success' => $result->success,
        ]);

        if ($result->success) {
            $this->conversations->setCrmRecordId($conversation, null);
        }
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
        $name = $conversation->displayName();

        return $name !== null && $name !== '' && ! in_array($name, ['Гость', 'Гость сайта'], true);
    }

    private function hasPhone(Conversation $conversation): bool
    {
        $phone = $conversation->displayPhone();

        return $phone !== null && $phone !== '';
    }

    /**
     * Вернувшийся узнанный клиент: телефон уже известен на старте И диалог
     * привязан к карточке клиента (перенесена из прошлого диалога чата). Тогда
     * номер перед записью подтверждаем; у нового клиента (телефон даёт по ходу)
     * подтверждать нечего.
     */
    private function isReturning(Conversation $conversation): bool
    {
        return $this->hasPhone($conversation) && $conversation->client_id !== null;
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

    private function ask(string $text, ?ReplyKeyboard $keyboard = null): BotReply
    {
        return new BotReply($text, escalate: false, keyboard: $keyboard);
    }

    /**
     * Клавиатура-подсказка из пунктов меню (услуги/мастера/слоты): подпись кнопки
     * = название пункта, его распознаёт `resolveChoice`.
     *
     * @param  list<array{id: string, title: string}>  $options
     */
    private function optionsKeyboard(array $options, int $perRow): ReplyKeyboard
    {
        return ReplyKeyboard::grid(array_map(fn (array $o): string => $o['title'], $options), $perRow);
    }

    /**
     * Кликабельный календарь: кнопки на ближайшие дни. Подпись «Пн 23.06» —
     * `RussianDateParser` берёт из неё «23.06» (день недели игнорируется).
     */
    private function dateKeyboard(): ReplyKeyboard
    {
        $today = Carbon::now()->startOfDay();

        $labels = [];
        for ($i = 0; $i < self::DATE_BUTTONS; $i++) {
            $day = $today->copy()->addDays($i);
            $labels[] = self::WEEKDAY_ABBR[$day->dayOfWeekIso].' '.$day->format('d.m');
        }

        return ReplyKeyboard::grid($labels, 3);
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

        // Точное совпадение названия — приоритет (чтобы «Стрижка» не уехала в
        // «Стрижка машинкой»).
        foreach ($options as $option) {
            if (mb_strtolower($option['title']) === $t) {
                return $option;
            }
        }

        // Иначе подстрочное совпадение, но только если оно ЕДИНСТВЕННОЕ; на
        // нескольких — отдаём на разбор LLM/переспрос, а не берём первое попавшееся.
        $hits = [];
        foreach ($options as $option) {
            $title = mb_strtolower($option['title']);
            if ($title !== '' && (str_contains($title, $t) || str_contains($t, $title))) {
                $hits[] = $option;
            }
        }

        return count($hits) === 1 ? $hits[0] : null;
    }

    /**
     * Окна в названном клиентом «времени суток» («после обеда», «утром», «вечером»,
     * «днём»). Возвращает подходящие окна (для показа) или [] — фраза не про
     * время суток.
     *
     * @param  list<array{id: string, title: string}>  $options
     * @return list<array{id: string, title: string}>
     */
    private function filterSlotsByDaypart(array $options, string $text): array
    {
        $t = mb_strtolower($text);

        [$lo, $hi] = match (true) {
            str_contains($t, 'после обед') => [13, 18],
            str_contains($t, 'до обед') || str_contains($t, 'утр') || str_contains($t, 'пораньше') => [0, 12],
            str_contains($t, 'вечер') || str_contains($t, 'попозже') || str_contains($t, 'поздн') => [17, 24],
            str_contains($t, 'днём') || str_contains($t, 'днем') || str_contains($t, 'обед') || str_contains($t, 'полдень') => [12, 17],
            default => [-1, -1],
        };

        if ($lo < 0) {
            return [];
        }

        return array_values(array_filter(
            $options,
            fn (array $o): bool => (int) mb_substr($o['title'], 0, 2) >= $lo && (int) mb_substr($o['title'], 0, 2) < $hi,
        ));
    }

    /**
     * Сопоставляет «час» из ответа клиента с реальным окном: «14» → первое окно в
     * 14:00, «14:30» → ровно 14:30. Возвращает окно или null (такого часа нет).
     *
     * @param  list<array{id: string, title: string}>  $options
     * @return array{id: string, title: string}|null
     */
    private function matchSlotByTime(array $options, string $text): ?array
    {
        if (preg_match('/^\s*(\d{1,2})(?:[:.\s](\d{2}))?\s*$/u', $text, $m) !== 1) {
            return null;
        }

        $hour = (int) $m[1];
        if ($hour > 23) {
            return null;
        }

        $exact = isset($m[2]) ? sprintf('%02d:%02d', $hour, (int) $m[2]) : null;
        $hourPrefix = sprintf('%02d:', $hour);

        foreach ($options as $option) {
            $title = $option['title']; // «HH:MM»
            if ($exact !== null ? $title === $exact : str_starts_with($title, $hourPrefix)) {
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
     * Цены услуг (рубли) по id — снимок для «Отчёта ценности». null, если CRM
     * цену не отдала.
     *
     * @param  list<CrmService>  $services
     * @return array<string, int|null>
     */
    private function servicePrices(array $services): array
    {
        $prices = [];

        foreach ($services as $service) {
            $prices[$service->id] = $service->price;
        }

        return $prices;
    }

    /**
     * Цена выбранной услуги из снимка в состоянии записи. null — услуга без цены
     * в CRM или цена не была захвачена.
     *
     * @param  array<string, mixed>  $state
     */
    private function bookedPrice(array $state): ?int
    {
        $serviceId = isset($state['service_id']) ? (string) $state['service_id'] : null;
        $prices = is_array($state['service_prices'] ?? null) ? $state['service_prices'] : [];
        $price = $serviceId !== null ? ($prices[$serviceId] ?? null) : null;

        return is_numeric($price) ? (int) $price : null;
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

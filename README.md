# Отклик — AI-администратор для локального бизнеса

Мультитенантный SaaS: перехватывает входящие обращения локального бизнеса
(WhatsApp, Telegram, веб-виджет → далее Avito/VK/телефония), отвечает по базе
знаний бизнеса (RAG + LLM) и записывает клиента в его CRM. Цель — не терять ни
одного обращения, особенно вне рабочих часов и в пик.

Бизнес- и тех-планы, материалы по продажам и инструкция по запуску — в `docs/`.

## Статус

**Фаза 0 (Каркас) — готова.** Развёрнут мультитенантный скелет, изоляция
тенантов (scope + RLS), Docker-окружение, CI-гейты, стартовая Inertia-страница.

**Фаза 1 (Telegram-канал + эхо) — готова.** Приём вебхуков Telegram с
верификацией secret-токена, асинхронная обработка в Horizon, эхо-ответ по базе
сущностей канал/диалог/сообщение. Сессионная переменная `app.current_tenant`
(основа RLS) выставляется через `TenantInitializer`.

**Фаза 2 (веб-интерфейс) — готова.** Аутентификация (session, паттерн Breeze),
панель супер-админа (заведение бизнесов и их владельцев) и кабинет тенанта:
подключение Telegram формой, профиль бизнеса, база знаний. Тенант-контекст в
вебе ставит `BindTenantToRequest` по залогиненному пользователю.

**Фаза 3 (диалоговый слой, MVP) — готова.** Бот отвечает по опубликованной базе
знаний бизнеса (профиль + записи в промпт). Если вопрос непонятен или ответа нет
в базе, бот не зовёт сразу человека, а задаёт уточняющий вопрос (сентинел
`[[CLARIFY]]`); счётчик `conversations.clarification_attempts` считает подряд
идущие уточнения и после 3-го сбрасывает диалог в `needs_human` с вежливым
фолбеком. Явная эскалация (`[[ESCALATE]]` — клиент зовёт человека, жалоба,
условия `escalation_note`) уводит на администратора сразу. Когда запись оформлена,
бот возвращает `[[BOOKED]]` — диалог закрывается (`closed`); следующее обращение
клиента в закрытый диалог начинает новый (на чат канала допускается лишь один
незакрытый диалог — частичный unique-индекс). Провайдер LLM — за интерфейсом `LlmClient`;
по умолчанию локальный `FakeLlmClient` (без ключей, всё тестируется). Реальные
GigaChat/YandexGPT подключаются за конфигом `LLM_DRIVER`. Векторный RAG (pgvector) —
следующий шаг при росте базы знаний.

## Стек

| Слой | Технология |
|---|---|
| Рантайм / фреймворк | PHP 8.3+ · Laravel 13 |
| App-сервер | Laravel Octane + RoadRunner |
| Очереди | Redis + Laravel Horizon |
| БД | PostgreSQL 16 + pgvector (RAG) |
| Кэш / сессии / очереди | Redis 7 |
| Фронтенд | Inertia.js + Vue 3 + TypeScript + Tailwind (Vite) |
| LLM (Фаза 3) | YandexGPT / GigaChat (данные в РФ, 152-ФЗ) |

## Архитектура

Поток зависимостей строго в одну сторону (подробнее — в `CLAUDE.md`):

```
HTTP / Console / Job  →  Service (бизнес-логика)  →  Repository (доступ к БД)  →  Model
```

- **Controller** (`app/Http/Controllers`) — тонкий: валидация, вызов сервиса, ответ.
- **Service** (`app/Services`) — только бизнес-логика; БД трогает через репозитории.
- **Repository** (`app/Repositories`) — единственный слой доступа к БД. Контракт
  в `Contracts/`, реализация в `Eloquent/`. Биндинг — `RepositoryServiceProvider`.
- **Model** (`app/Models`) — Eloquent (схема, связи, касты).
- **DTO** (`app/DTO`) — `readonly` объекты переноса данных между слоями.
- **Enum** (`app/Enums`) — backed string enum с `label()` для UI.
- **Event** (`app/Events`) — побочные эффекты через домен-события.

### Мультитенантность

Изоляция данных тенантов — критичный инвариант, многослойный:

- `tenant_id` во всех тенант-таблицах;
- трейт `App\Models\Concerns\BelongsToTenant` + глобальный `TenantScope`
  (автофильтр по текущему тенанту из `App\Tenancy\TenantContext`);
- `TenantContext` биндится как **scoped** — Octane сбрасывает его между
  запросами (нет утечки тенанта в резидентном рантайме);
- PostgreSQL **Row-Level Security** — жёсткий рубеж на уровне БД (миграция
  применяется только на `pgsql`; на sqlite в тестах пропускается). Покрыты
  `users` и messaging-таблицы (`channels` / `conversations` / `messages`);
- сессионную переменную `app.current_tenant`, по которой работает RLS,
  выставляет `App\Tenancy\TenantInitializer` (в запросах и в задачах Horizon),
  гарантированно сбрасывая её после обработки; в вебе её ставит middleware
  `App\Http\Middleware\BindTenantToRequest` по залогиненному пользователю.

**Постура RLS по таблицам.** `users` — таблица идентичности/бутстрапа auth
(её читают по email/id до резолва тенанта; супер-админ не привязан к тенанту),
поэтому её RLS-полиси **разрешающая при невыставленном `app.current_tenant`**
(совпадает с семантикой `TenantScope`: нет контекста → нет фильтра; есть
контекст → жёсткая изоляция). Бизнес-таблицы (`channels` / `conversations` /
`messages` / `knowledge_entries`) — **строгий** RLS: к ним всегда обращаются с
заданным контекстом.

Контракт тенант-моделей — `App\Tenancy\Contracts\TenantOwned`.

## Ключевые сущности (Фаза 0)

| Сущность | Назначение |
|---|---|
| `App\Models\Tenant` | Клиент-бизнес (UUID PK). Реестр тенантов, сам не скоупится. |
| `App\Enums\TenantPlan` | Тариф: `trial` / `starter` / `pro` (метод `label()`). |
| `App\Services\TenantService` | Регистрация тенанта: уникальный slug, план, событие. |
| `App\Repositories\Contracts\TenantRepositoryInterface` | Доступ к данным тенантов. |
| `App\Events\TenantRegistered` | Домен-событие регистрации тенанта. |

## Каналы и сообщения (Фаза 1)

| Сущность | Назначение |
|---|---|
| `App\Models\Channel` | Канал тенанта. Креды бота (`bot_token`, `secret_token`) шифруются (`encrypted:array`). |
| `App\Models\Conversation` | Диалог с клиентом в рамках канала (один `external_chat_id` — один диалог). |
| `App\Models\Message` | Сообщение диалога; уникальность `(conversation_id, direction, external_message_id)` — идемпотентность ретраев. |
| `App\Enums\ChannelType` | `telegram` / `whatsapp` / `web`. |
| `App\Enums\MessageDirection` | `inbound` / `outbound`. |
| `App\Enums\MessageStatus` | `received` / `sent` / `failed`. |
| `App\Enums\ConversationStatus` | `open` / `needs_human` / `closed`. Закрыть/вернуть диалог можно вручную из кабинета (`PUT /cabinet/conversations/{id}/status`). |
| `App\Services\ChannelService` | Подключение Telegram-бота к тенанту: создание канала + `deleteWebhook` (бот работает через long polling — вебхуки в РФ не доставляются). |
| `App\Console\Commands\PollTelegramUpdates` (`telegram:poll`) | Long polling Telegram: сервер сам тянет апдейты (getUpdates по IPv6) и кладёт их в ту же очередь, что и вебхук. Отдельный контейнер `telegram` в проде. Нужно в РФ, где входящий путь Telegram→IPv4 заблокирован. |
| `App\Services\IncomingMessageService` | Обработка входящего: фиксация диалога/сообщения, захват контактов (`ContactCapture`), ответ через `ReplyComposer`, отправка; при эскалации — статус `needs_human`. Уведомление операторам при эскалации в Telegram не дублируется (его шлёт `TelegramRelayService`). |
| `App\Services\TelegramRelayService` | Живой мост «оператор ↔ клиент» через бот бизнеса при эскалации. Пока диалог в статусе `needs_human`, ИИ молчит: сообщения клиента пересылаются всем telegram-получателям (операторам), а они отвечают Telegram-«Ответить» на пересланное сообщение. Маппинг «пересланное сообщение → диалог» — в кэше (Redis, TTL 7 дней), без отдельной таблицы. Команды: `/close` (закрыть диалог, дальше отвечает бот) и `/bot` (вернуть диалог боту). Оркеструется в `ProcessTelegramUpdate` (отправители-операторы перехватываются до бизнес-логики через `isOperator`). |
| `App\Services\ContactCapture` | Достаёт из входящего телефон (`PhoneExtractor`, регулярка) и имя (`NameDetector`, нейросеть — только если бот спрашивал имя) и сохраняет их по диалогу. Имя берётся из того, как клиент представился сам, а не из аккаунта мессенджера. |
| Поле `conversations.contact_ref` | Внешняя привязка контакта для деталей диалога: мессенджеры — ссылка на аккаунт (Telegram: `https://t.me/<username>`, если задан), веб-виджет — IP посетителя. Показывается в карточке диалога (`Cabinet/Conversations/Show`). |
| `App\Services\NameDetector` | Определяет имя клиента LLM-классификацией ответа на вопрос «Как вас зовут?» (люди пишут просто «Павел», без «меня зовут»). |
| `App\Llm\Contracts\LlmClient` | Порт LLM (реализации: `FakeLlmClient`, `YandexGptClient` — OpenAI-совместимый эндпоинт Yandex Cloud AI; выбор по `LLM_DRIVER`). |
| `App\Services\PromptBuilder` | Системный промпт из профиля бизнеса + опубликованной базы знаний. Сентинелы (распознаются в любом месте ответа и вырезаются из текста): `[[ESCALATE]]` (эскалация), `[[CLARIFY]]` (уточняющий вопрос), `[[BOOKED]]` (запись оформлена → закрыть), `[[CANCELLED]]` (клиент отменил запись → закрыть), `[[BOOK]]` (клиент хочет записаться → передать пошаговому мастеру записи). `[[BOOK]]` включается в промпт только при подключённой CRM-автозаписи (`bookingEnabled`); иначе работает «ручной» `[[BOOKED]]`. |
| `App\Services\ReplyComposer` | Сборка ответа: промпт + история диалога → LLM. `[[ESCALATE]]` → сразу на администратора; `[[CLARIFY]]` → уточняющий вопрос, до 3 раз подряд (`clarification_attempts`), затем эскалация; `[[BOOKED]]` → `markBooked` (`closed` + `booked_at`); `[[CANCELLED]]` → `markCancelled` (`closed` + `cancelled_at`); `[[BOOK]]` → `BotReply.startBooking` (мастер записи); обычный ответ сбрасывает счётчик. |
| `App\Services\BotResponder` | Выбирает, кто отвечает клиенту: если у диалога активна запись (`conversations.booking_state`) — ведёт `BookingFlow`; иначе отвечает `ReplyComposer`, и при `startBooking` + доступной автозаписи запускает мастер записи. Точка входа для Telegram (`IncomingMessageService`) и веб-виджета (`WebWidgetService`). |
| `App\Services\BookingFlow` | Пошаговая запись клиента в CRM: услуга → мастер (или «любой», `staff_id=0`) → день → конкретное время → контакты (**обязательно имя и телефон**) → `createBooking`. Имя/телефон ловит `ContactCapture` (ИИ) в вызывающем слое + детерминированный фолбэк (`extractName`/`PhoneExtractor`); если за несколько попыток не собрали — эскалация. Каталог и слоты берутся ТОЛЬКО из CRM (источник истины); выбранный слот — строка `datetime` от CRM, возвращается без изменений (бот не выдумывает время). Выбор клиента распознаётся сначала детерминированно (номер/совпадение названия), затем через LLM (`resolveChoice`/`matchWithLlm` — свободные формулировки «давайте к савелию»); дата — `RussianDateParser` + LLM-фолбэк. Состояние шага — `conversations.booking_state`. Понятные статусы: успех с деталями, неудача → текст + телефон бизнеса (`withPhone`) + эскалация. Все шаги/запросы/исходы логируются (`Log::info('booking.*')`). Фолбэки: нет подключения → `start()` возвращает `null`; нет услуг/слотов/сбой API → эскалация. Смену статуса (`booked`/`needs_human`) делает вызывающий слой по флагам `BotReply`. |
| `App\Support\RussianDateParser` | Детерминированный разбор даты из текста: «сегодня/завтра/послезавтра», дни недели (ближайший от сегодня), `dd.mm[.yyyy]`/`dd/mm`, «dd <месяц>», голое число дня; прошедшие даты переносятся вперёд. Возвращает `Y-m-d` или `null`. |
| Поле `conversations.booking_state` | JSON-состояние активной пошаговой записи (`BookingFlow`): текущий шаг и накопленный выбор. `null` — активной записи нет, диалог ведёт обычный бот. |
| `App\Enums\ConversationOutcome` | Универсальный итог по лиду (под любой бизнес): `booked` (Успешный лид), `cancelled` (Отменён клиентом), `lost` (Потерянный лид), `needs_human`, `open` (В работе), `spam` (Спам/нерелевантный). `Conversation::outcome()` выводит автоматически, но админ может выставить любой вручную (`outcome_override`, приоритетнее) — `PUT /cabinet/conversations/{id}/status` с полем `outcome`; статус диалога синхронизируется (закрытые итоги → `closed`). Колонка «Итог» в журнале + выпадающий список в карточке диалога. |
| `App\Console\Commands\CloseStaleConversations` (`conversations:close-stale`) | Закрывает открытые диалоги без активности дольше 30 мин и без записи (потерянные лиды), по всем тенантам. Гоняется планировщиком (`routes/console.php`, каждые 5 мин) — отдельный контейнер `scheduler` (`schedule:work`) в проде. |
| `App\Services\LeadAnalyticsService` | Аналитика по лидам за период (`LeadAnalyticsPeriod`: 7/30/90 дней / всё время): KPI с динамикой к прошлому периоду (новые лиды, конверсия в запись, сбор контактов, вовлечённость, эскалации, ср. уточнений), ряды для графиков (по дням/часам/дням недели), разбивки по каналам/статусам, воронка и «пробелы» (`Gap` — чего и где не хватает, базовый детерминированный разбор). Считает поверх выборки из `LeadAnalyticsRepository`; DTO в `App\DTO\Analytics`. |
| `App\Services\LeadInsightsService` | ИИ-разбор «чего и где не хватает»: метрики периода → `LlmClient` → список наблюдений с рекомендациями. Кэшируется по тенанту+периоду (НЕ считается на каждой загрузке): обновляется по устареванию (>12 ч) фоновой задачей `RefreshLeadInsights` (Horizon) или по кнопке. Если LLM недоступна/вернула не JSON — фолбек на правила (`Gap`). |
| `App\Repositories\…\LeadAnalyticsRepository` | Тонкий слой выборки для аналитики (лиды за окно с числом входящих, активные каналы, свежие лиды); всё скоупится тенантом (RLS). |
| `App\Http\Controllers\Cabinet\AnalyticsController` | Страница аналитики `GET /cabinet/analytics` (`?period=7d\|30d\|90d\|all`); обновление ИИ-разбора `POST /cabinet/analytics/insights/refresh`; выгрузки CSV (UTF-8 BOM) `GET /cabinet/analytics/export/{leads\|daily}`. Дашборд (`DashboardController`) остаётся хабом-навигацией. |
| Поле `conversations.booked_at` | Момент оформления записи (сигнал `[[BOOKED]]`) — для метрики конверсии лидов в запись. |
| `App\Models\CrmConnection` | Подключение тенанта к CRM (провайдер + зашифрованные креды); строгий RLS. |
| `App\Enums\CrmProvider` | CRM-провайдер (`yclients`; расширяется). |
| `App\Crm\Contracts\CrmGateway` | Стратегия CRM (verify + услуги/мастера/слоты/создание записи; DTO в `App\Crm\Data`). Реестр стратегий `CrmGatewayResolver` по тегу `crm.gateways` — новый CRM = новый адаптер. Реализация: `App\Crm\Yclients\YclientsGateway`. |
| `App\Services\CrmConnectionService` | Подключение/проверка/отключение CRM + делегирование booking-операций (таб «Интеграции»). |
| `App\Channels\Contracts\MessengerGateway` | Порт отправки в мессенджер (реализация `App\Channels\Telegram\TelegramGateway`). |
| `App\Jobs\ProcessTelegramUpdate` | Асинхронный разбор апдейта в тенант-контексте (Horizon). |
| `App\Tenancy\TenantInitializer` | Единая точка входа в тенант-контекст (in-memory + `app.current_tenant` для RLS). |
| `App\Repositories\Contracts\{Channel,Conversation,Message}RepositoryInterface` | Доступ к данным каналов/диалогов/сообщений. |

### Подключение Telegram-бота

```bash
php artisan channel:connect-telegram <tenant-uuid> <bot-token>
```

Создаёт канал тенанта с зашифрованным токеном и регистрирует вебхук Telegram на
`<TELEGRAM_WEBHOOK_BASE_URL>/webhooks/telegram/{tenant}/{channel}` с уникальным
`secret_token` (Telegram присылает его в заголовке `X-Telegram-Bot-Api-Secret-Token`).
В вебе эту операцию заменит форма в кабинете тенанта.

## Веб-интерфейс, роли и доступ (Фаза 2)

| Сущность | Назначение |
|---|---|
| `App\Enums\UserRole` | `super_admin` / `owner` / `member` (метод `label()`). |
| `App\Models\KnowledgeEntry` | Запись базы знаний (title/content/is_published + `links`/`images` jsonb); строгий RLS. |
| `App\Support\KnowledgeImageStorage` | Хранение картинок-«примеров работ» на public-диске под путём тенанта. |
| `App\Models\SiteSetting` | Контент публичного лендинга, контакты и юр. реквизиты (`legal_name`/`inn`/`ogrnip`); редактируется супер-админом в `/admin/site`. |
| `App\Enums\TenantPlan` | Тарифы `trial`/`standard`/`max` (Пробный/Стандарт/Макс). `tier()` (пробный = уровень «Стандарт») и `features()` → `App\DTO\PlanFeatures`. |
| `App\DTO\PlanFeatures` | Возможности тарифа: `maxOperators`, `crm`, `analytics`, `broadcasts`, `clientBase`, `allChannels`, `webWidget`, `maxNotifyEmail`, `maxNotifyTelegram`. `merge()` накладывает индивидуальные оверрайды. Источник матрицы гейтинга. |
| `Tenant::features()` + `settings['overrides']` | Эффективные возможности бизнеса = тариф + индивидуальные оверрайды супер-админа (по договорённости). СУ задаёт права/лимиты конкретному бизнесу: `PUT/DELETE /admin/tenants/{tenant}/overrides`. Inertia и `EnsurePlanFeature` используют именно эффективные фичи. |
| `App\Http\Middleware\EnsurePlanFeature` (alias `plan`) | Гейт маршрута по возможности тарифа: `->middleware('plan:crm')`. CRM-интеграции — только «Макс». |
| Уведомления владельцу | Получатели на бизнесе (`notification_recipients`, RLS): email + Telegram, лимиты из тарифа (Стандарт 1 email / 4 telegram; Макс — больше/по договорённости). Кабинет: `/cabinet/notifications` (`NotificationController` + `NotificationRecipientService` — проверка лимита). Telegram подключается по диплинку `t.me/<bot>?start=notify_<token>` (`TelegramLinkService` ловит `/start` в `ProcessTelegramUpdate`). Доставка — стратегии `Notifier` (`EmailNotifier`/`TelegramNotifier`, реестр `NotifierResolver` по тегу `notifiers`), рассылка в фоне (`SendOwnerNotification` → `NotificationService`). События: новый лид, нужен оператор, запись, отмена. |
| `App\Http\Controllers\Cabinet\SubscriptionController` | Страница `/cabinet/subscription`: текущий тариф, срок доступа, доступные/закрытые возможности. |
| `App\Services\WebWidgetService` | Диалог веб-виджета: сессия посетителя на подписанном токене (AES на `APP_KEY`, привязка к каналу+диалогу), ответ через `ReplyComposer`. |
| `App\Http\Controllers\Widget\WidgetChatController` | Публичный API виджета (JS + session/message), CORS + origin allow-list + тенант-контекст из URL. |
| `App\Http\Controllers\Cabinet\WidgetController` | Управление виджетом в кабинете: подключение `ChannelType::Web`, код для вставки, разрешённые домены. |
| `App\Http\Middleware\BindTenantToRequest` | Ставит тенант-контекст по `Auth::user()->tenant_id`, сбрасывает в `terminate()`. |
| `App\Http\Middleware\EnsureSuperAdmin` (alias `super-admin`) | Доступ к `/admin/*` только супер-админу. |
| `App\Http\Middleware\EnsureTenantUser` (alias `tenant`) | Доступ к `/cabinet/*` только пользователю тенанта. |
| `App\Http\Controllers\Auth\AuthenticatedSessionController` | Вход/выход (session). |
| `App\Http\Controllers\Auth\PasswordResetController` | Восстановление пароля по коду из письма. |
| `App\Services\PasswordResetService` | Генерация/проверка одноразового кода (TTL 6 мин, в `password_reset_tokens` хранится только хеш; существование email не раскрывается). |
| `App\Mail\PasswordResetCodeMail` | Письмо с 6-значным кодом восстановления (через очередь). |
| `App\Support\HomeRedirect` | Домашний маршрут по роли: супер-админ → `admin.tenants.index`, тенант → `cabinet.dashboard`. Используется и после входа, и в guest-middleware (уже авторизованный на `/login` — напр. при активной remember-сессии — уходит в свою панель, а не на лендинг). |
| `App\Http\Controllers\Admin\TenantController` | Супер-админ: список тенантов, создание бизнеса+владельца, детали. |
| `App\Http\Controllers\Cabinet\{Channel,BusinessProfile,KnowledgeEntry}Controller` | Кабинет тенанта. |
| `App\Services\UserService` | Создание владельца в тенант-контексте; список пользователей тенанта. |
| `App\Services\KnowledgeBaseService` | CRUD базы знаний (эмбеддинги/RAG — Фаза 3). |
| `App\Repositories\Contracts\{User,KnowledgeEntry}RepositoryInterface` | Доступ к данным пользователей/базы знаний. |

Бутстрап первого супер-админа:

```bash
php artisan admin:create-super-admin "Имя" admin@example.com <пароль>
```

Публичной регистрации нет — тенантов заводит супер-админ. Подключение Telegram в
кабинете заменяет консольную команду `channel:connect-telegram`.

База знаний поддерживает структурированный контент: текст, ссылки (`label`+`url`)
и картинки-«примеры работ» (загрузка на disk `public`). Для отдачи картинок нужен
символьный линк: `php artisan storage:link` (на проде хранилище — RU object storage,
152-ФЗ).

## Маршруты

| Метод | URL | Контроллер | Назначение |
|---|---|---|---|
| GET | `/` · `/contacts` | `Site\HomeController` | Публичный лендинг + контакты (маркетинг-домен). |
| GET | `/up` | — | Health-check Laravel. |
| GET/POST | `/login` · POST `/logout` | `Auth\AuthenticatedSessionController` | Вход/выход (session). |
| GET/POST | `/forgot-password` · `/reset-password` | `Auth\PasswordResetController` | Восстановление пароля по коду из письма (код 6 мин, `throttle:6,1`). |
| GET/POST/GET | `/admin/tenants[/{tenant}]` | `Admin\TenantController` | Реестр тенантов, создание, детали (auth + `super-admin`). |
| PUT | `/admin/tenants/{tenant}/owner-password` | `Admin\TenantController` | Супер-админ задаёт пароль владельцу бизнеса (auth + `super-admin`). |
| — | `/cabinet/widget` | `Cabinet\WidgetController` | Веб-виджет: подключение, код для вставки, разрешённые домены (auth + `tenant`). |
| GET | `/widget/v1/widget.js` | `Widget\WidgetChatController` | JS-рантайм виджета (публичный). |
| POST | `/widget/v1/{tenant}/{channel}/{session\|message}` | `Widget\WidgetChatController` | Публичный API чата: stateless, CORS, origin allow-list, подписанный токен сессии, throttle. |
| — | `/cabinet`, `/cabinet/overview`, `/cabinet/channels`, `/cabinet/profile`, `/cabinet/knowledge`, `/cabinet/subscription` | `Cabinet\*` | Кабинет тенанта (auth + `tenant`). `/cabinet/overview` — карточка бизнеса (`BusinessOverviewController`): аватар, описание, контакты, тариф; это «домашняя» по клику на логотип, на проде же — корень бизнес-домена `/`. Профиль (`BusinessProfileController`) поддерживает аватар (public-диск), описание и сайт. |
| — | `/cabinet/integrations` | `Cabinet\IntegrationController` | CRM-интеграции (auth + `tenant` + `plan:crm` — тариф «Макс»). |
| GET | `/account` | `Account\AccountController` | Настройки аккаунта (хаб: пароль / почта). В шапке — иконка-шестерёнка. 2FA — позже. |
| GET/PUT | `/account/password` | `Account\PasswordController` | Смена своего пароля (auth). |
| GET/POST | `/account/email`, `/account/email/confirm` | `Account\EmailController` | Смена e-mail с подтверждением: код (6 цифр, 15 мин, хеш в `email_change_codes`) уходит на НОВЫЙ адрес через `EmailChangeService`; почта меняется только после ввода кода. Запрос требует текущий пароль и свободный адрес. |
| GET/POST/DELETE | `/account/two-factor`, `…/confirm` | `Account\TwoFactorController` | Двухфакторка (TOTP, `TwoFactorService` на `pragmarx/google2fa`): включение требует пароль → QR + резервные коды → подтверждение кодом; секрет/коды хранятся зашифрованными в `users`. При входе (`AuthenticatedSessionController` + `Auth\TwoFactorChallengeController`, GET/POST `/two-factor-challenge`) после пароля запрашивается код приложения или резервный код. Доступно всем (вкл. супер-админа). |
| POST | `/webhooks/telegram/{tenant}/{channel}` | `Webhooks\TelegramWebhookController` | Приём вебхука Telegram (stateless, без CSRF; верификация secret-токена; ack 200 → очередь). |

## Окружение (ключевые переменные)

| Переменная | Назначение | В Docker |
|---|---|---|
| `DB_CONNECTION` | Драйвер БД | `pgsql` |
| `DB_HOST` / `DB_PORT` | Хост/порт Postgres | `pgsql` / `5432` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Доступ к БД | `otklick` / `otklick` / `secret` |
| `REDIS_HOST` / `REDIS_PORT` | Redis | `redis` / `6379` |
| `CACHE_STORE` / `QUEUE_CONNECTION` / `SESSION_DRIVER` | Используют Redis | `redis` |
| `OCTANE_SERVER` | App-сервер Octane | `roadrunner` |
| `RUN_MIGRATIONS` | Гонять ли миграции при старте контейнера | `true` (app), `false` (horizon) |
| `TELEGRAM_API_URL` | Базовый URL Telegram Bot API | `https://api.telegram.org` |
| `TELEGRAM_WEBHOOK_BASE_URL` | Публичный HTTPS-адрес для `setWebhook` | `${APP_URL}` |
| `LLM_DRIVER` | Провайдер LLM: `fake` / `yandexgpt` (gigachat — TODO) | `fake` |
| `YANDEX_API_KEY` / `YANDEX_FOLDER_ID` | Ключ и folder каталога Yandex Cloud (для `yandexgpt`) | — |
| `YANDEX_GPT_MODEL` | Модель YandexGPT | `yandexgpt-lite` |
| `YCLIENTS_API_URL` | Базовый URL YClients API | `https://api.yclients.com/api/v1` |
| `YCLIENTS_PARTNER_TOKEN` | Партнёрский токен приложения YClients | — |

Тесты используют отдельный профиль из `phpunit.xml` (sqlite `:memory:`).

На проде приложение работает за обратным прокси (Caddy терминирует TLS, проксирует
по HTTP). В `bootstrap/app.php` включены доверенные прокси (`trustProxies(at: '*')`
с `X-Forwarded-*`), иначе Laravel генерировал бы ссылки на ассеты по `http://`
(mixed-content на HTTPS-странице).

## Запуск

Полная пошаговая инструкция — **`docs/КАК_ЗАПУСТИТЬ_И_ТЕСТИРОВАТЬ.md`**. Кратко:

```bash
# Весь стек в Docker (приложение на http://localhost:8000)
docker compose up -d --build

# Локально (быстрые тесты на sqlite)
composer test
```

## Гейты качества (CI)

```bash
./vendor/bin/pint --test                               # стиль кода
php -d memory_limit=512M ./vendor/bin/phpstan analyse  # статанализ (level 6, Larastan)
composer test                                          # Unit + Integration + Feature
```

Все три обязаны быть зелёными перед коммитом.

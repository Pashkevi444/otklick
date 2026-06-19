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
- **Адаптеры внешних систем** (`app/Channels`, `app/Crm`, `app/Llm`,
  `app/Notifications`) — порты-и-адаптеры: единственный слой, ходящий во внешние
  API (мессенджеры/CRM/LLM/почта). Это аналог `Repository` (тот ходит в БД),
  поэтому лежат **рядом** с ним, а не внутри `Services`. У каждого: `Contracts/`
  (порт) + реализации провайдеров + `*Resolver` (выбор стратегии по тегу).
  Бизнес-логики в них нет.
- **DTO** — `readonly` объекты переноса данных. Кросс-слойные (между
  Controller/Job/Service/Repository) — в `app/DTO`. Собственный data-контракт
  порта-адаптера живёт рядом с портом в его `Data/` (напр. `app/Crm/Data`) —
  словарь порта не отрывается от самого порта.
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
| `App\Models\Channel` | Канал тенанта. Креды шифруются (`encrypted:array`): Telegram — `bot_token`/`secret_token`, ВКонтакте — `access_token`/`group_id`. Доступ к произвольному креду — `credential(key)`. |
| `App\Models\Conversation` | Диалог с клиентом в рамках канала (один `external_chat_id` — один диалог). |
| `App\Models\Message` | Сообщение диалога; уникальность `(conversation_id, direction, external_message_id)` — идемпотентность ретраев. |
| `App\Enums\ChannelType` | `telegram` / `vk` / `max` / `whatsapp` / `web`. `pollable()` — каналы, которые сервер опрашивает long polling'ом (Telegram, VK, MAX). |
| `App\Channels\ChannelGatewayResolver` | Реестр стратегий каналов (тег `channel.gateways`): отдаёт `ChannelGateway` по `ChannelType`. Новый канал = новый шлюз в теге, резолвер не трогаем. |
| `App\Enums\MessageDirection` | `inbound` / `outbound`. |
| `App\Enums\MessageStatus` | `received` / `sent` / `failed`. |
| `App\Enums\ConversationStatus` | `open` / `needs_human` / `closed`. Закрыть/вернуть диалог можно вручную из кабинета (`PUT /cabinet/conversations/{id}/status`). |
| `App\Services\ChannelService` | Подключение каналов к тенанту: `connectTelegram` (создание канала + `deleteWebhook`, бот через long polling — вебхуки в РФ не доставляются), `connectVk` (создание канала + валидация сообщества через `groups.getById` внутри транзакции, бот через Bots Long Poll), `connectMax` (создание канала + валидация токена через `GET /me` внутри транзакции, бот через long polling), `connectWeb`/`setWidgetOrigins` (веб-виджет). |
| `App\Console\Commands\PollTelegramUpdates` (`telegram:poll`) | Long polling Telegram: сервер сам тянет апдейты (getUpdates по IPv6) и кладёт их в ту же очередь, что и вебхук. Отдельный контейнер `telegram` в проде. Нужно в РФ, где входящий путь Telegram→IPv4 заблокирован. |
| `App\Console\Commands\PollVkUpdates` (`vk:poll`) | Bots Long Poll ВКонтакте: двухшаговый протокол — `groups.getLongPollServer`, затем опрос сервера (`a_check`). Состояние `{server,key,ts}` в кэше; `failed`-коды VK (1 — обновить ts; 2/3 — переинициализировать сервер). Диспатчит `ProcessVkUpdate`. Отдельный контейнер `vk` в проде. |
| `App\Console\Commands\PollMaxUpdates` (`max:poll`) | Long polling MAX (botapi.max.ru): `GET /updates` с маркером (токен — в заголовке `Authorization`). Позиция чтения `marker` в кэше. Диспатчит `ProcessMaxUpdate`. Отдельный контейнер `max` в проде. |
| `App\Console\Commands\PollWhatsAppUpdates` (`whatsapp:poll`) + `App\Channels\WhatsApp\WhatsAppGateway` | **WhatsApp через провайдера Green API** (привязка реального аккаунта по QR; креды `id_instance`+`api_token`). Long polling `receiveNotification` → диспатчит `ProcessWhatsAppUpdate` → подтверждает `deleteNotification` (всегда, иначе очередь забьётся). Позиция — на стороне Green API, локально не храним. Подключение (`ChannelService::connectWhatsApp`) валидирует `getStateInstance=authorized`. Отправка `sendMessage` (кнопки — текстом, интерактивных у WhatsApp нет). Голос (`audioMessage` downloadUrl) — через `ReceivesVoice`. Отдельный контейнер `whatsapp` в проде. Конфиг `services.whatsapp.api_url` (`GREENAPI_API_URL`). |
| `App\Services\IncomingMessageService` | Обработка входящего (канало-агностична): фиксация диалога/сообщения, захват контактов (`ContactCapture`), ответ через `ReplyComposer`, отправка **через шлюз канала сообщения** (`ChannelGatewayResolver->for($channel->type)` — Telegram/VK/…); при эскалации — статус `needs_human`. Уведомление операторам при эскалации в Telegram не дублируется (его шлёт `TelegramRelayService`). **Надёжная доставка:** если синхронная отправка сорвалась, реплай НЕ теряется — фиксируется как `queued` и добивается фоновым `DeliverBotReply` (ретраи с бэкоффом); исчерпались — диалог уходит на человека. |
| `App\Jobs\DeliverBotReply` | Фоновая повторная доставка ответа бота, когда отправка в канал сорвалась (таймаут/недоступность). `tries=5`, нарастающий `backoff` (10/30/60/120 с); при успехе помечает сообщение `sent`, при исчерпании (`failed()`) — `failed` + диалог `needs_human`, чтобы зависший лид увидел оператор. Статус `MessageStatus::Queued` — «в очереди на отправку». |
| `App\Services\TelegramRelayService` | Живой мост «оператор ↔ клиент» через бот бизнеса при эскалации. Пока диалог в статусе `needs_human`, ИИ молчит: сообщения клиента пересылаются всем telegram-получателям (операторам), а они отвечают Telegram-«Ответить» на пересланное сообщение. Маппинг «пересланное сообщение → диалог» — в кэше (Redis, TTL 7 дней), без отдельной таблицы. Команды: `/close` (закрыть диалог, дальше отвечает бот) и `/bot` (вернуть диалог боту). Оркеструется в `ProcessTelegramUpdate` (отправители-операторы перехватываются до бизнес-логики через `isOperator`). |
| `App\Services\ContactCapture` | Достаёт из входящего телефон (`PhoneExtractor` — СТРОГО РФ: +7/8 и 10 цифр, иначе null) и имя (`NameDetector`, нейросеть — только если бот спрашивал имя) и сохраняет их по диалогу. Имя берётся из того, как клиент представился сам, а не из аккаунта мессенджера. Появился телефон → дергает `ClientService` (карточка клиента). |
| `App\Models\Client` (`clients`, RLS) + `ClientService` | **База клиентов**: единая карточка (имя, телефон, email, ник Telegram, первый канал, заметки, краткое LLM-резюме). Идентичность по телефону в пределах тенанта; `ClientService::linkConversation` находит/создаёт клиента, проставляет `conversations.client_id` и дозаполняет пустые поля (заполненные не затирает). Запас полей — на случай, если бизнес «переобучит» бота вытаскивать больше данных. **Источник правды для отображения лида:** грид/карточка «Лиды» (`ConversationController`) показывают имя/телефон ИЗ карточки клиента (по `client_id`), с фолбэком на захваченные по диалогу поля, пока клиента нет — правка имени в карточке отражается на лидах, поиск ищет и по клиенту. Поля `conversations.contact_*` остаются буфером захвата (диалог существует до появления телефона/клиента; бот пишет в них при записи). Канал — свойство самого лида (`channel_id`), не клиента. |
| `App\Services\ClientSummaryService` + `App\Jobs\RefreshClientSummary` | Краткое резюме клиента по переписке (LLM): что хотел, чем интересовался, статус. Пересобирается автоматически по записи (`IncomingMessageService` → job) и вручную (кнопка в карточке). При сбое LLM прежнее резюме не затирается. |
| `App\Http\Controllers\Cabinet\ClientController` | База клиентов в кабинете: грид с поиском/фильтром по каналу/сортировкой (`GET /cabinet/clients`), карточка с диалогами и резюме (`show`/`update`/`summary`/`destroy`). Гейт `plan:clientBase` (Макс/Индивидуальный). Раздел `clients` (дашборд-карточка + `CabinetSection`). |
| `App\Models\Broadcast` (`broadcasts`, RLS) + `App\Services\BroadcastService` + `App\Http\Controllers\Cabinet\BroadcastController` | **Рассылки по базе клиентов**: одно сообщение → выбранные каналы (Telegram/ВКонтакте/MAX + email), вручную или по расписанию с периодичностью (`BroadcastRecurrence`: разово/день/неделя/месяц). Аудитория — клиенты без отписки (`clients.marketing_opt_out`), вся база или выбранные (`broadcasts.client_ids`); достижимость по их диалогам (channel + external_chat_id) + email — кому канал недоступен, тому не шлём. Доставка провайдер-агностична через `ChannelGatewayResolver` (+ `BroadcastMail`); счётчики `sent/failed` агрегатом + пер-получательский журнал `broadcast_deliveries` (RLS: кому/канал/статус/ошибка) → отчёт на `GET /cabinet/broadcasts/{id}`. Запуск (`POST /cabinet/broadcasts` mode=now\|schedule, `…/run`, `…/cancel`, `DELETE …`) ставит `App\Jobs\SendBroadcast` в очередь (tries=1 — без дублей). Гейт `plan:broadcasts` (Макс/Индивидуальный или оверрайд СУ) + раздел `broadcasts` для сотрудников (`CabinetSection`/`MemberPermission`). 152-ФЗ: правовое основание рассылки — на бизнесе; отписка уважается. |
| `App\Console\Commands\RunDueBroadcasts` (`broadcasts:run-due`) | Раз в 5 минут запускает «созревшие» запланированные рассылки (`next_run_at` ≤ now) у тенантов с правом `broadcasts`; периодичные сами переносят `next_run_at` после доставки. У тенантов без права ничего не крутится. |
| Поле `conversations.contact_ref` | Внешняя привязка контакта для деталей диалога: мессенджеры — ссылка на аккаунт (Telegram: `https://t.me/<username>`, если задан), веб-виджет — IP посетителя. Показывается в карточке диалога (`Cabinet/Conversations/Show`). |
| `App\Services\NameDetector` | Определяет имя клиента LLM-классификацией ответа на вопрос «Как вас зовут?» (люди пишут просто «Павел», без «меня зовут»). |
| `App\Llm\Contracts\LlmClient` | Порт LLM (реализации: `FakeLlmClient`, `YandexGptClient` — OpenAI-совместимый эндпоинт Yandex Cloud AI; выбор по `LLM_DRIVER`). В отличие от каналов/CRM/нотификаторов, у LLM нет `Resolver`: драйвер выбирается **глобально по конфигу** (одна модель на приложение), а не по записи тенанта — поэтому биндится `match($driver)` в `AppServiceProvider`. |
| `App\Services\PromptBuilder` | Системный промпт из профиля бизнеса + опубликованной базы знаний. Сентинелы (распознаются в любом месте ответа и вырезаются из текста): `[[ESCALATE]]` (эскалация), `[[CLARIFY]]` (уточняющий вопрос), `[[BOOKED]]` (запись оформлена → закрыть), `[[CANCELLED]]` (клиент отменил запись → закрыть), `[[BOOK]]` (клиент хочет записаться → передать пошаговому мастеру записи). `[[BOOK]]` включается в промпт только когда запись доступна (`bookingEnabled` в `BotResponder` = **право `crm` у тенанта** `features()->crm` И активное подключение). Нет права на CRM (YClients) → запись «резко отключена»: бот её не предлагает, `BookingFlow::start()` возвращает `null`, диалог уходит на человека. Запись на услугу провайдер-агностична (порт `CrmGateway`), но сейчас подключён только **YClients** (`CrmProvider`); в UI всё помечено «YClients». Если контакты клиента уже известны (узнали по чату/телефону/нику — перенос в `firstOrCreateForChat`), `ReplyComposer` передаёт имя/факт телефона в `build()`, и промпт велит боту обращаться по имени и НЕ переспрашивать контакты, сразу предлагая релевантные варианты. |
| `App\Llm\Contracts\Embedder` | Порт эмбеддингов (вектор смысла). Реализации: `YandexEmbedder` (Yandex Cloud textEmbedding) и `FakeEmbedder` (детерминированный локальный); выбор по `EMBEDDER_DRIVER`. |
| `App\Speech\Contracts\SpeechToText` | **Распознавание голосовых сообщений** (войс → текст). Реализации: `YandexSpeechToText` (Yandex SpeechKit, OGG/Opus нативно, Api-Key как у GPT) и `FakeSpeechToText` (для тестов/локалки); выбор по `SPEECH_DRIVER` (глобально, как у LLM). Гейтвеи мессенджеров реализуют `App\Channels\Contracts\ReceivesVoice` (скачивают аудио из апдейта: Telegram getFile+download, VK link_ogg, MAX url); `App\Services\VoiceTranscriptionService` резолвит гейтвей→скачивает→STT. Джобы каналов при пустом тексте подставляют расшифровку как обычный ввод. При `fake`/сбое STT голос тихо игнорируется (бот не падает). |
| `App\Models` `knowledge_chunks` (RLS) | Векторный индекс знаний (RAG): чанк на запись клиентской БЗ и БЗ из CRM + эмбеддинг. На PostgreSQL — колонка `vector` (pgvector) + поиск по `<=>`; на sqlite (тесты) — эмбеддинг в JSON, косинус в PHP. |
| `App\Services\KnowledgeIndexer` + `App\Jobs\IndexKnowledge` | Пересборка векторного индекса тенанта (опубликованная БЗ + БЗ из CRM → эмбеддинги → `knowledge_chunks`, атомарно). Ставится в очередь при изменении БЗ и после выгрузки из CRM. |
| `App\Services\KnowledgeRetriever` | Семантический поиск под вопрос клиента: эмбеддит запрос, отдаёт id релевантных записей (топ-K). Пустой индекс/сбой эмбеддера → `null` (мягкий фолбэк на «вся база в промпт»). |
| `App\Services\ReplyComposer` | Сборка ответа: промпт + история **по чату** (`recentForChat` — через все диалоги клиента, чтобы бот помнил прошлое общение, напр. оформленную запись, после закрытия диалога) → LLM. `[[ESCALATE]]` → сразу на администратора; `[[CLARIFY]]` → уточняющий вопрос, до 3 раз подряд (`clarification_attempts`), затем эскалация; `[[BOOKED]]` → `markBooked` (`booked_at`, диалог остаётся `open` — «в работе» до визита); `[[CANCELLED]]` → `markCancelled` (`closed` + `cancelled_at`); `[[BOOK]]` → `BotReply.startBooking` (мастер записи); обычный ответ сбрасывает счётчик. |
| `App\Services\BotResponder` | Выбирает, кто отвечает клиенту. Порядок: мета-намерение «отменить/перенести» (`interceptIntent`) → **контактная форма** (`ContactGate`) → активная запись (`conversations.booking_state` → `BookingFlow`) → `ReplyComposer` (и при `startBooking` запускает мастер записи). У вернувшегося клиента с **уже активной записью** на `[[BOOK]]` показывается меню `BookingFlow::bookingChoiceMenu` (кнопки «Перенести запись»/«Отменить запись»/«Новая запись»), а не молча заводится вторая; «Новая запись» (точный текст) форсит свежий мастер минуя меню/LLM. ВАЖНО: схема — один активный (не closed) диалог на чат (partial-unique), поэтому у чата максимум ОДНА активная запись; несколько одновременных записей на клиента потребуют отдельной таблицы связей (`lead_crm_links`). Точка входа для Telegram (`IncomingMessageService`) и веб-виджета (`WebWidgetService`). |
| `App\Services\ContactGate` | Контактная форма в начале диалога (едина для всех каналов): новому клиенту — приветствие + запрос **имени и телефона (обязательно), email (по желанию)** со СТРОГОЙ валидацией телефона (`PhoneExtractor::analyze` — короткий/длинный/мусор → просим исправить, а не «хаваем»). Собрали — даём кликабельные кнопки действий (`ReplyKeyboard`: «Записаться»/«Цены и услуги»/«Адрес и часы»). Вернувшегося узнаём (контакты перенеслись из прошлого диалога чата) — форму НЕ показываем, здороваемся по имени. Имя из первого сообщения-вопроса НЕ вытаскиваем (только из ответа на форму). Флаг прохождения — `conversations.contacts_gate_done`; email — `conversations.contact_email` (→ `ClientService` в карточку). |
| `App\Services\BookingFlow` | Пошаговая запись клиента в CRM: услуга → мастер (или «любой», `staff_id=0`) → день → конкретное время → контакты (**обязательно имя и телефон**) → `createBooking`. Имя/телефон ловит `ContactCapture` (ИИ) в вызывающем слое + детерминированный фолбэк (`extractName`/`PhoneExtractor`); если за несколько попыток не собрали — эскалация. Каталог и слоты берутся ТОЛЬКО из CRM (источник истины); выбранный слот — строка `datetime` от CRM, возвращается без изменений (бот не выдумывает время). Выбор клиента распознаётся сначала детерминированно (номер/совпадение названия), затем через LLM (`resolveChoice`/`matchWithLlm` — свободные формулировки и опечатки «давайте к савелею»); дата — `RussianDateParser` + LLM-фолбэк. Свободное время: мало окон (≤6) — списком, много — бот не спамит, спрашивает удобное время и сопоставляет с реальными слотами (полный список только если названное время не подошло). На каждом шаге бот отдаёт **кликабельную клавиатуру** (`BotReply->keyboard`, см. `App\DTO\ReplyKeyboard`): календарь ближайших дней, кнопки времени/услуг/мастеров — клик = отправка подписи, поэтому проходит тот же разбор, что и текст. Состояние шага — `conversations.booking_state`. **Мета-намерения** «отменить»/«перенести» запись перехватываются (`interceptIntent` → `BotResponder`) В ЛЮБОЙ момент — и во время мастера записи, и вне его, поэтому клиент не «застревает» на шаге: отмена → сигнал `cancelled` (вызывающий слой отменяет в CRM и закрывает диалог); перенос → запись заводится заново, прежняя отменяется в CRM ТОЛЬКО после успешного создания новой (`supersedes_record_id`) — клиент не остаётся без слота. **Прошедшая дата** не валит запрос слотов в CRM (HTTP 422), а вежливо просит будущую. **Вернувшийся клиент** (диалог привязан к карточке и телефон уже известен — переносятся из прошлого диалога чата в `firstOrCreateForChat`) узнаётся: бот обращается по имени и перед записью подтверждает телефон (шаг `confirm_contact`; прислал новый номер — обновляется). Понятные статусы: успех с деталями, неудача → текст + телефон бизнеса (`withPhone`) + эскалация. При успехе `record_id` сохраняется в `conversations.crm_record_id`; на просьбу отменить `cancelLastBooking` находит последнюю запись чата и отменяет её в CRM, **возвращая успех**: `interceptIntent` подтверждает отмену клиенту ТОЛЬКО при успехе CRM-отмены, иначе НЕ врёт «отменил», а эскалирует на администратора (важно: отмена YClients требует `YCLIENTS_PARTNER_TOKEN` в env — без него DELETE `/record` отдаёт 401). `cancelBookingForConversation` отменяет запись КОНКРЕТНОГО лида по его собственной привязке (`crm_connection_id`+`crm_record_id`) — вызывается при удалении лида и при ручной смене статуса на «Отменён» в кабинете (раздел «Лиды» = `ConversationController`). Все шаги/запросы/исходы логируются (`Log::info('booking.*')`). Фолбэки: нет подключения → `start()` возвращает `null`; нет услуг/слотов/сбой API → эскалация. Смену статуса (`booked`/`needs_human`) делает вызывающий слой по флагам `BotReply`. |
| `App\Support\RussianDateParser` | Детерминированный разбор даты из текста: «сегодня/завтра/послезавтра», дни недели (ближайший от сегодня), `dd.mm[.yyyy]`/`dd/mm`, «dd <месяц>», голое число дня («20», «на 20», «20 числа»); прошедшие даты переносятся вперёд. Голое число НЕ распознаётся как дата, если это время («в 15») или часть фразы с днём недели («в воскресенье в 15» = воскресенье, а не 15-е) — такие случаи уходят в LLM-фолбэк. Возвращает `Y-m-d` или `null`. |
| Поле `conversations.booking_state` | JSON-состояние активной пошаговой записи (`BookingFlow`): текущий шаг и накопленный выбор. `null` — активной записи нет, диалог ведёт обычный бот. |
| `App\Enums\ConversationOutcome` | Универсальный итог по лиду (под любой бизнес): `booked` (Успешный лид), `cancelled` (Отменён клиентом), `lost` (Потерянный лид), `needs_human`, `open` (В работе), `spam` (Спам/нерелевантный). `Conversation::outcome()` выводит автоматически, но админ может выставить любой вручную (`outcome_override`, приоритетнее) — `PUT /cabinet/conversations/{id}/status` с полем `outcome`; статус диалога синхронизируется (закрытые итоги → `closed`). **Жизненный цикл записи:** оформленная запись (`markBooked` → `booked_at`, но диалог НЕ закрывается, статус `open`) — это **«В работе»**, пока время визита (`booked_for`) впереди: клиент может вернуться, перенести или отменить. **«Успешный лид»** — когда визит уже прошёл (`booked_for` в прошлом) либо админ проставил вручную; финально закрывает диалог планировщик (`bookings:reconcile`). Колонка «Итог» в журнале + выпадающий список в карточке диалога. |
| `App\Console\Commands\CloseStaleConversations` (`conversations:close-stale`) | Закрывает открытые диалоги без активности дольше 30 мин и без записи (потерянные лиды), по всем тенантам. Гоняется планировщиком (`routes/console.php`, каждые 5 мин) — отдельный контейнер `scheduler` (`schedule:work`) в проде. |
| `App\Console\Commands\ReconcileBookings` (`bookings:reconcile`) | Раз в час сверяет записи: закрывает диалоги с CRM-бронью, время визита которой прошло (`booked_for` < now) → лид становится «Успешным». Работает ТОЛЬКО у тенантов с активной CRM (`activeForCurrentTenant`); у бизнеса без CRM запись всегда уходит на человека и планировщик ничего не делает (лишний код не крутится). Полноценный двусторонний обмен статусами с YClients (детект отмен мастером в CRM) — пока не реализован (нет get-record метода в `CrmGateway`). |
| `App\Console\Commands\SendAppointmentReminders` (`appointments:send-reminders`) + `App\Jobs\SendAppointmentReminder` | Напоминания клиентам о записи (в рамках CRM-интеграции). Планировщик (каждые 5 мин) находит записи, у которых наступило время напоминания (`booked_for − офсет`), «столбит» их (`reminders_sent`, без дублей) и ставит отправку в очередь (Horizon, ретраи). Настройки — в `CrmConnection.settings['reminders']` (`enabled` + офсеты, `App\DTO\ReminderSettings`), редактируются в «Интеграциях». Доставка — только в push-каналы (Telegram); записи вне бота (нет канала клиента) не покрываются. |
| `App\Services\LeadAnalyticsService` | Аналитика по лидам за период (`LeadAnalyticsPeriod`: 7/30/90 дней / всё время): KPI с динамикой к прошлому периоду (новые лиды, конверсия в запись, сбор контактов, вовлечённость, эскалации, ср. уточнений), ряды для графиков (по дням/часам/дням недели), разбивки по каналам/статусам, **покрытие 24/7** (`byDaypart` — рабочее время vs «вне рабочих часов» 8–20, метрика `afterHours`) и **глубину диалога** (`engagement` — распределение лидов по числу входящих сообщений), воронка и «пробелы» (`Gap` — чего и где не хватает, базовый детерминированный разбор). Считает поверх выборки из `LeadAnalyticsRepository`; DTO в `App\DTO\Analytics`. Графики (`DonutChart`/`AreaChart`/`BarChart`) интерактивны: ховер-тултипы, выделение/затемнение сегментов, клик-закрепление сектора пончика. |
| `App\Services\ValueReportService` | «Отчёт ценности» — что бот принёс **в деньгах**, **отдельно по каждой CRM** тенанта (у диалога-записи `crm_connection_id`; на тенанта может быть несколько CRM). Метрики на одну CRM за период (+динамика): выручка (точно по записям — снимок цены услуги в момент записи), записей оформлено, средний чек, конверсия лид→запись, напоминаний клиентам, отмен; плюс топ услуг по выручке (`ServiceRevenue`). Переиспользует `MetricCard`. Показывается **только при праве `crm`** (нет интеграции с CRM по тарифу/оверрайду — блок скрыт, остаётся общая аналитика). DTO `App\DTO\Analytics\ValueReport`. |
| `App\Services\LeadInsightsService` | ИИ-разбор «чего и где не хватает»: метрики периода → `LlmClient` → список наблюдений с рекомендациями. Кэшируется по тенанту+периоду (НЕ считается на каждой загрузке): обновляется по устареванию (>12 ч) фоновой задачей `RefreshLeadInsights` (Horizon) или по кнопке. Если LLM недоступна/вернула не JSON — фолбек на правила (`Gap`). |
| `App\Repositories\…\LeadAnalyticsRepository` | Тонкий слой выборки для аналитики (лиды за окно с числом входящих, активные каналы, свежие лиды); всё скоупится тенантом (RLS). |
| `App\Http\Controllers\Cabinet\AnalyticsController` | Страница аналитики `GET /cabinet/analytics` (`?period=7d\|30d\|90d\|all`) + «Отчёт ценности» (`valueReports`, только при праве `crm`); обновление ИИ-разбора `POST /cabinet/analytics/insights/refresh`; выгрузки CSV (UTF-8 BOM) `GET /cabinet/analytics/export/{leads\|daily}` и записи по CRM `…/export/value?crm={id}` (тоже под правом `crm`). Дашборд (`DashboardController`) остаётся хабом-навигацией. |
| Поле `conversations.booked_at` | Момент оформления записи (сигнал `[[BOOKED]]`) — для метрики конверсии лидов в запись. |
| Поля `conversations.crm_connection_id` / `booked_service_id` / `booked_service_title` / `booked_service_price` | Снимок ценности оформленной записи для «Отчёта ценности»: в какую CRM ушла запись и какая услуга/цена (рубли) зафиксированы в момент записи (`BookingFlow::book` → `recordBookingValue`). Цена-снимок → переоценка услуг в CRM не искажает историческую выручку. |
| `App\Models\CrmConnection` | Подключение тенанта к CRM (провайдер + зашифрованные креды); строгий RLS. |
| `App\Enums\CrmProvider` | CRM-провайдер (`yclients`; расширяется). |
| `App\Crm\Contracts\CrmGateway` | Стратегия CRM (verify + услуги/мастера/слоты/создание и отмена записи; DTO в `App\Crm\Data`). Реестр стратегий `CrmGatewayResolver` по тегу `crm.gateways` — новый CRM = новый адаптер. Реализация: `App\Crm\Yclients\YclientsGateway`. |
| `App\Services\CrmConnectionService` | Подключение/проверка/отключение CRM + делегирование booking-операций (таб «Интеграции»). |
| `App\Services\YclientsMarketplaceService` + `App\Models\YclientsLink` (`yclients_links`) | Подключение YClients через маркетплейс (флоу «Подключить»). Факты приходят двумя путями в любом порядке: бизнес возвращается на Registration Redirect залогиненным (привязка `salon_id`→тенант) и server-to-server вебхук YClients с `salon_id`+user-токеном. Промежуточные факты — в `yclients_links` (вне тенант-изоляции: вебхук без контекста; user-токен шифруется). Когда известны И тенант, И user-токен — материализуется рабочее `CrmConnection` под контекстом тенанта (`settings.source=marketplace`). Партнёрский токен — общий (env), user-токен — per-бизнес (с правами на филиал; нужен для отмены/переноса). |
| `App\Services\CrmKnowledgeSyncService` + `App\Jobs\SyncCrmKnowledge` | Фоновая выгрузка справочника CRM (услуги+цены, мастера, филиал; слоты НЕ выгружаются — они реал-тайм) в отдельную нередактируемую базу знаний `crm_knowledge_entries`. Кнопка в кабинете вешает задачу на очередь (Horizon); выгрузка `replaceForCurrentTenant` атомарна (delete+insert). Клиентская БЗ (`knowledge_entries`) не трогается. |
| `App\Models\CrmKnowledgeEntry` (`crm_knowledge_entries`, RLS) | Нередактируемая запись из CRM (категория `service`/`staff`/`company`). В промпте бота — приоритетный источник: при расхождении с клиентской БЗ верны данные CRM (актуальнее). |
| `App\Http\Controllers\Cabinet\CrmKnowledgeController` | Вкладка «База знаний из CRM» (`GET /cabinet/knowledge-crm`, только чтение, сгруппировано) + запуск выгрузки (`POST /cabinet/knowledge-crm/sync`). Гейт `plan:crm` («Макс»). |
| `App\Channels\Contracts\MessengerGateway` / `ChannelGateway` | Порт отправки в мессенджер; `ChannelGateway` добавляет `provider()` (тип канала). `send()` принимает необязательную `App\DTO\ReplyKeyboard` (клавиатуру-подсказку) — каждый канал рендерит её в свой формат: Telegram/VK — reply-кнопки (нажатие шлёт подпись обычным сообщением), MAX — inline-кнопки с callback (нажатие приходит как `message_callback`). Так клик попадает в тот же разбор шага записи (`resolveChoice`/`RussianDateParser`), отдельной инфраструктуры колбэков не нужно. Реализации: `Telegram\TelegramGateway`, `Vk\VkGateway`, `Max\MaxGateway`, `WhatsApp\WhatsAppGateway` (Green API). |
| `App\DTO\ReplyKeyboard` | Канало-независимая клавиатура-подсказка (строки кнопок; подпись = что «отправится» при нажатии). `BookingFlow` отдаёт её в `BotReply->keyboard`: **кликабельный календарь** (ближайшие дни), время, услуги, мастера, кнопка «Да» для подтверждения телефона. Подпись ОБЯЗАНА быть распознаваемой шагом (дата — «dd.mm», время — «HH:MM»). |
| `App\Jobs\ProcessTelegramUpdate` / `ProcessVkUpdate` / `ProcessMaxUpdate` | Асинхронный разбор апдейта в тенант-контексте (Horizon). У VK/MAX нет операторского моста (операторы в Telegram), поэтому job проще: parse → `IncomingMessageService::handle`. `ProcessMaxUpdate` также ловит нажатия inline-кнопок (`message_callback`): payload = выбор клиента → подаём как обычный ввод + `answerCallback` (гасит «часики»). |
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

### Подключение сообщества ВКонтакте

```bash
php artisan channel:connect-vk <tenant-uuid> <community-token> <group-id>
```

Создаёт канал тенанта с зашифрованными кредами (токен сообщества + `group_id`) и
проверяет сообщество (`groups.getById`) внутри транзакции — при ошибке канал не
сохраняется. В кабинете эту операцию заменяет форма (вкладка «Каналы» → ВКонтакте).
В сообществе нужен ключ с правами на сообщения и включённый **Long Poll API**
(события `message_new`); входящие забирает `vk:poll` (вебхук не требуется).

### Подключение бота MAX

```bash
php artisan channel:connect-max <tenant-uuid> <bot-token>
```

MAX — российский мессенджер (max.ru). Бот создаётся через **@MasterBot** (`/newbot`),
который выдаёт токен. `connectMax` создаёт канал с зашифрованным токеном и проверяет
его через `GET /me` внутри транзакции (битый токен → 401 → откат). Токен передаётся в
заголовке `Authorization`. Входящие забирает `max:poll` (long polling, вебхук не нужен).
В кабинете эту операцию заменяет форма (вкладка «Каналы» → MAX).

## Веб-интерфейс, роли и доступ (Фаза 2)

| Сущность | Назначение |
|---|---|
| `App\Enums\UserRole` | `super_admin` / `owner` / `member` (метод `label()`). |
| `App\Models\KnowledgeEntry` | Запись базы знаний (title/content/is_published + `links`/`images` jsonb); строгий RLS. |
| `App\Support\KnowledgeImageStorage` | Хранение картинок-«примеров работ» на public-диске под путём тенанта. |
| `App\Models\SiteSetting` | Контент публичного лендинга, контакты и юр. реквизиты (`legal_name`/`inn`/`ogrnip`); редактируется супер-админом в `/admin/site`. |
| `App\Enums\TenantPlan` | Тарифы `trial`/`standard`/`max` (Пробный/Стандарт/Макс). `tier()` (пробный = уровень «Стандарт») и `features()` → `App\DTO\PlanFeatures`. |
| `App\DTO\PlanFeatures` | Возможности тарифа: `maxOperators`, `crm`, `analytics`, `broadcasts`, `clientBase`, `allChannels`, `webWidget`, `reminders`, `rag`, `aiInsights`, `maxNotifyEmail`, `maxNotifyTelegram`. `merge()` накладывает индивидуальные оверрайды (СУ правит каждый флаг по тенанту). `aiInsights` (Макс/Индивидуальный) — ИИ-рекомендации в аналитике («чего и где не хватает»): без права блок скрыт и пересчёт `…/insights/refresh` отдаёт 403, остаётся общая аналитика. Источник матрицы гейтинга. |
| `App\Enums\TenantPlan` | Тарифы: `trial` (0), `standard` (9 900 ₽), `max` (14 900 ₽ — всё включено, удвоенные лимиты), `individual` (по договорённости — всё + кратно бо́льшие лимиты). `priceRub()` — цена; `features()` — матрица возможностей; `tier()` (Trial→Standard). |
| `Tenant::features()` + `settings['overrides']` | Эффективные возможности бизнеса = тариф + индивидуальные оверрайды супер-админа (по договорённости). СУ задаёт права/лимиты конкретному бизнесу: `PUT/DELETE /admin/tenants/{tenant}/overrides`. Inertia и `EnsurePlanFeature` используют именно эффективные фичи. |
| `App\Http\Middleware\EnsurePlanFeature` (alias `plan`) | Гейт маршрута по возможности тарифа: `->middleware('plan:crm')`. CRM-интеграции — только «Макс». Аналитика — `plan:analytics`. |
| `App\Http\Controllers\Cabinet\TeamController` + `App\Enums\MemberPermission` | Команда бизнеса (`/cabinet/team`, только владелец): добавление сотрудников (роль `member`) и выдача прав галочками (`users.permissions`). **Две матрицы прав:** тенантная (`PlanFeatures`, правит СУ оверрайдами — что бизнесу доступно по тарифу) и матрица мемберов (`MemberPermission`, правит владелец — что сотрудник может внутри доступного). Матрица мемберов ⊆ тенантной: `MemberPermission::grantableWith($features)` не даёт выдать право вне тарифа (`CabinetSection::requiredFeature`). Лимит — `maxOperators`. |
| `App\Enums\MemberPermission` | Единый каталог прав сотрудника: доступ к разделам (значение = ключ `CabinetSection`) + права-действия (`conversations.edit/delete`, `clients.edit/delete`). `User::allows($key)`/`effectivePermissions()` (владелец/СУ — все). Фронт гейтит кнопки через `useCan()` (shared-prop `auth.user.permissions`). |
| `App\Http\Middleware\EnsureSectionAllowed` | Гейтит сотруднику разделы кабинета по `User::permissions` (раздел = `cabinet.<section>` из имени маршрута). Права-действия (удаление/редактирование лидов и клиентов) проверяются в контроллерах (`allows('...delete')`). Владелец/супер-админ — без ограничений. |
| `App\Http\Controllers\Admin\ImpersonationController` | Вход супер-админа в кабинет бизнеса (`POST /admin/tenants/{tenant}/impersonate`) и выход обратно (`POST /impersonate/leave`); исходный супер-админ хранится в сессии. Кнопка в карточке бизнеса; баннер выхода в `AppLayout` (shared-prop `impersonating`). |
| Уведомления владельцу | Получатели на бизнесе (`notification_recipients`, RLS): email + Telegram, лимиты из тарифа (Стандарт 1 email / 4 telegram; Макс — больше/по договорённости). Кабинет: `/cabinet/notifications` (`NotificationController` + `NotificationRecipientService` — проверка лимита). Telegram подключается по диплинку `t.me/<bot>?start=notify_<token>` (`TelegramLinkService` ловит `/start` в `ProcessTelegramUpdate`). Доставка — стратегии `Notifier` (`EmailNotifier`/`TelegramNotifier`, реестр `NotifierResolver` по тегу `notifiers`), рассылка в фоне (`SendOwnerNotification` → `NotificationService`). События: новый лид, нужен оператор, запись, отмена. В уведомление включается ссылка на аккаунт клиента в мессенджере (VK `vk.com/id…`, Telegram `t.me/…`) — поле «Профиль», чтобы владелец написал клиенту в его канал. |
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
| `App\Models\KnowledgeGap` (`knowledge_gaps`, RLS) + `KnowledgeGapRepository` | «Пробелы бота»: вопросы клиентов, на которые бот не нашёл ответа. Пишутся в `IncomingMessageService` при эскалации с флагом `BotReply.knowledgeGap` (его ставит `ReplyComposer` на `[[ESCALATE]]`/исчерпании уточнений — НЕ на эскалациях из booking-флоу). Дедуп по нормализованному вопросу в пределах тенанта (`occurrences`). Вкладка «Развитие бота» в базе знаний (`KnowledgeGapController`: `promote` → черновик записи + статус `resolved`; `dismiss`; `destroy`) — рычаг удержания: бизнес видит, чего боту не хватает, и дополняет базу. |
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
| GET | `/` · `/contacts` · `/privacy` | `Site\HomeController` | Публичный лендинг, контакты и политика конфиденциальности 152-ФЗ (маркетинг-домен). |
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
| GET/POST/DELETE | `/cabinet/broadcasts` (+ `…/{id}` отчёт, `…/{id}/run`, `…/{id}/cancel`) | `Cabinet\BroadcastController` | Рассылки по базе клиентов (auth + `tenant` + `plan:broadcasts` + раздел `broadcasts`). Создание (сейчас/по расписанию), запуск, снятие с расписания, удаление; отчёт о доставке (журнал по получателям). |
| GET | `/account` | `Account\AccountController` | Настройки аккаунта (хаб: пароль / почта). В шапке — иконка-шестерёнка. 2FA — позже. |
| GET/PUT | `/account/password` | `Account\PasswordController` | Смена своего пароля (auth). |
| GET/POST | `/account/email`, `/account/email/confirm` | `Account\EmailController` | Смена e-mail с подтверждением: код (6 цифр, 15 мин, хеш в `email_change_codes`) уходит на НОВЫЙ адрес через `EmailChangeService`; почта меняется только после ввода кода. Запрос требует текущий пароль и свободный адрес. |
| GET/POST/DELETE | `/account/two-factor`, `…/confirm` | `Account\TwoFactorController` | Двухфакторка (TOTP, `TwoFactorService` на `pragmarx/google2fa`): включение требует пароль → QR + резервные коды → подтверждение кодом; секрет/коды хранятся зашифрованными в `users`. При входе (`AuthenticatedSessionController` + `Auth\TwoFactorChallengeController`, GET/POST `/two-factor-challenge`) после пароля запрашивается код приложения или резервный код. Доступно всем (вкл. супер-админа). |
| POST | `/webhooks/telegram/{tenant}/{channel}` | `Webhooks\TelegramWebhookController` | Приём вебхука Telegram (stateless, без CSRF; верификация secret-токена; ack 200 → очередь). |
| GET | `/yclients/connect` | `Yclients\MarketplaceController` | Registration Redirect YClients (auth + `tenant`): привязка `?salon_id=` к тенанту. |
| POST | `/yclients/webhook` · `/yclients/disconnect` | `Yclients\MarketplaceController` | Вебхуки маркетплейса YClients (stateless, без CSRF; подлинность — партнёрский токен в теле; throttle). Подключение/события и отключение интеграции. |

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
| `VK_API_URL` | Базовый URL VK API | `https://api.vk.com/method` |
| `VK_API_VERSION` | Версия VK API | `5.199` |
| `MAX_API_URL` | Базовый URL Bot API мессенджера MAX | `https://botapi.max.ru` |
| `LLM_DRIVER` | Провайдер LLM: `fake` / `yandexgpt` (gigachat — TODO) | `fake` |
| `YANDEX_API_KEY` / `YANDEX_FOLDER_ID` | Ключ и folder каталога Yandex Cloud (для `yandexgpt`) | — |
| `YANDEX_GPT_MODEL` | Модель YandexGPT | `yandexgpt-lite` |
| `EMBEDDER_DRIVER` | Провайдер эмбеддингов (RAG): `fake` / `yandex` | `fake` |
| `EMBEDDING_DIM` | Размерность вектора (совпадает со схемой `knowledge_chunks`) | `256` |
| `SPEECH_DRIVER` | Распознавание голосовых: `fake` / `yandex` (Yandex SpeechKit, переиспользует `YANDEX_API_KEY`/`YANDEX_FOLDER_ID`) | `fake` |
| `YANDEX_EMBED_MODEL` | Модель эмбеддингов Yandex | `text-search-query` |
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

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
| `App\Enums\ConversationStatus` | `open` / `closed`. |
| `App\Services\ChannelService` | Подключение Telegram-бота к тенанту: создание канала + `setWebhook`. |
| `App\Services\IncomingMessageService` | Обработка входящего: фиксация диалога/сообщения, эхо-ответ, отправка (RAG+LLM — далее). |
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
| `App\Models\KnowledgeEntry` | Запись базы знаний тенанта (title/content/is_published); строгий RLS. |
| `App\Http\Middleware\BindTenantToRequest` | Ставит тенант-контекст по `Auth::user()->tenant_id`, сбрасывает в `terminate()`. |
| `App\Http\Middleware\EnsureSuperAdmin` (alias `super-admin`) | Доступ к `/admin/*` только супер-админу. |
| `App\Http\Middleware\EnsureTenantUser` (alias `tenant`) | Доступ к `/cabinet/*` только пользователю тенанта. |
| `App\Http\Controllers\Auth\AuthenticatedSessionController` | Вход/выход (session). |
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

## Маршруты

| Метод | URL | Контроллер | Назначение |
|---|---|---|---|
| GET | `/` | `WelcomeController` | Лендинг (`Welcome`); авторизованного редиректит в его раздел. |
| GET | `/up` | — | Health-check Laravel. |
| GET/POST | `/login` · POST `/logout` | `Auth\AuthenticatedSessionController` | Вход/выход (session). |
| GET/POST/GET | `/admin/tenants[/{tenant}]` | `Admin\TenantController` | Реестр тенантов, создание, детали (auth + `super-admin`). |
| — | `/cabinet`, `/cabinet/channels`, `/cabinet/profile`, `/cabinet/knowledge` | `Cabinet\*` | Кабинет тенанта (auth + `tenant`). |
| GET/PUT | `/account/password` | `Account\PasswordController` | Смена своего пароля (auth). |
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

Тесты используют отдельный профиль из `phpunit.xml` (sqlite `:memory:`).

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

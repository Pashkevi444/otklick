<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        // Базовый URL Bot API (переопределяется для прокси/тестов).
        'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),
        // Публичный базовый URL приложения для setWebhook (HTTPS, доступен Telegram).
        'webhook_base_url' => env('TELEGRAM_WEBHOOK_BASE_URL', env('APP_URL')),
        // Форсировать IPv6 для запросов к Telegram: в РФ api.telegram.org
        // заблокирован по IPv4, и без этого Guzzle сначала висит на IPv4-таймауте
        // (~5 с на каждый вызов, включая ответы бота), потом уходит в IPv6.
        'force_ipv6' => (bool) env('TELEGRAM_FORCE_IPV6', false),
        // Прокси для запросов к Telegram (напр. socks5h://singbox:1080) — обход
        // блокировки РКН через VPN. Общий CHANNEL_PROXY или свой TELEGRAM_PROXY.
        'proxy' => env('TELEGRAM_PROXY', env('CHANNEL_PROXY')) ?: null,
    ],

    'yclients' => [
        'api_url' => env('YCLIENTS_API_URL', 'https://api.yclients.com/api/v1'),
        // Партнёрский токен приложения (общий для платформы); пользовательский
        // токен и company_id задаёт бизнес при подключении.
        'partner_token' => env('YCLIENTS_PARTNER_TOKEN'),
    ],

    'llm' => [
        // fake — локальная детерминированная модель (по умолчанию, без ключей).
        // gigachat / yandexgpt — реальные провайдеры (адаптеры добавляются при наличии ключей).
        'driver' => env('LLM_DRIVER', 'fake'),

        'gigachat' => [
            'client_id' => env('GIGACHAT_CLIENT_ID'),
            'client_secret' => env('GIGACHAT_CLIENT_SECRET'),
            'scope' => env('GIGACHAT_SCOPE', 'GIGACHAT_API_PERS'),
        ],

        'yandexgpt' => [
            // OpenAI-совместимый эндпоинт Yandex Cloud AI.
            'api_url' => env('YANDEX_API_URL', 'https://ai.api.cloud.yandex.net/v1/chat/completions'),
            'api_key' => env('YANDEX_API_KEY'),
            'folder_id' => env('YANDEX_FOLDER_ID'),
            'model' => env('YANDEX_GPT_MODEL', 'yandexgpt-lite'),
        ],
    ],

    'speech' => [
        // fake — без распознавания (по умолчанию, без ключей). yandex — SpeechKit.
        'driver' => env('SPEECH_DRIVER', 'fake'),

        'yandex' => [
            'api_url' => env('YANDEX_STT_API_URL', 'https://stt.api.cloud.yandex.net/speech/v1/stt:recognize'),
            'api_key' => env('YANDEX_API_KEY'),
            'folder_id' => env('YANDEX_FOLDER_ID'),
        ],
    ],

    'vision' => [
        // fake — без распознавания (по умолчанию, без ключей): бот «не видит» фото
        // и передаёт администратору. yandex — vision-модель Yandex Cloud AI
        // (OpenAI-совместимый эндпоинт, как у LLM). Модель должна быть vision-способной.
        'driver' => env('VISION_DRIVER', 'fake'),

        'yandex' => [
            'api_url' => env('YANDEX_API_URL', 'https://ai.api.cloud.yandex.net/v1/chat/completions'),
            'api_key' => env('YANDEX_API_KEY'),
            'folder_id' => env('YANDEX_FOLDER_ID'),
            'model' => env('VISION_MODEL', 'qwen2.5-vl-7b-instruct'),
        ],
    ],

    'vk' => [
        'api_url' => env('VK_API_URL', 'https://api.vk.com/method'),
        'version' => env('VK_API_VERSION', '5.199'),
    ],

    'whatsapp' => [
        // WhatsApp через провайдера Green API (привязка реального аккаунта по QR).
        'api_url' => env('GREENAPI_API_URL', 'https://api.green-api.com'),
        // Прокси для Green API (VPN, обход блокировки). Общий CHANNEL_PROXY или
        // свой WHATSAPP_PROXY.
        'proxy' => env('WHATSAPP_PROXY', env('CHANNEL_PROXY')) ?: null,
    ],

    'max' => [
        // Базовый URL Bot API мессенджера MAX (botapi.max.ru). Токен — в заголовке
        // Authorization. Сервер сам тянет апдейты через long polling (GET /updates).
        'api_url' => env('MAX_API_URL', 'https://botapi.max.ru'),
    ],

    'embedder' => [
        // fake — детерминированный локальный (по умолчанию). yandex — Yandex Cloud.
        'driver' => env('EMBEDDER_DRIVER', 'fake'),
        // Размерность вектора. ДОЛЖНА совпадать со схемой knowledge_chunks.
        'dimension' => (int) env('EMBEDDING_DIM', 256),

        'yandex' => [
            'api_url' => env('YANDEX_EMBED_API_URL', 'https://llm.api.cloud.yandex.net/foundationModels/v1/textEmbedding'),
            'api_key' => env('YANDEX_API_KEY'),
            'folder_id' => env('YANDEX_FOLDER_ID'),
            'model' => env('YANDEX_EMBED_MODEL', 'text-search-query'),
        ],
    ],

    'flows' => [
        // Порог косинусной близости сообщения к фразе-триггеру для семантического
        // запуска сценария (когда не совпало по основе слова). У Yandex-эмбеддингов
        // высокий базовый косинус: релевантное ~0.82, нерелевантное ~0.62 — порог
        // 0.74 разделяет (иначе «хочу записаться» ложно перехватывается воронкой).
        'semantic_threshold' => (float) env('FLOW_SEMANTIC_THRESHOLD', 0.74),
    ],

    // Ссылка на дашборд трекера ошибок (self-hosted GlitchTip/Sentry) — показывается
    // супер-админу в меню. Сами ошибки шлёт SDK по SENTRY_LARAVEL_DSN.
    'error_tracking_url' => env('ERROR_TRACKING_URL'),

];

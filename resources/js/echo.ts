import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Конфиг Reverb-клиента приходит shared-prop'ом `reverb` (сервер читает из config
 * в рантайме; ключ публичный). Это обходит проблему build-time env у Vite. Если
 * конфига нет (BROADCAST != reverb) — реалтайм выключен, работает поллинг.
 */
export interface ReverbConfig {
    key: string;
    host: string;
    port: number;
    scheme: string;
}

let instance: Echo<'reverb'> | null = null;

export function realtime(config: ReverbConfig | null | undefined): Echo<'reverb'> | null {
    if (!config || !config.key) {
        return null;
    }
    if (instance) {
        return instance;
    }

    (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

    instance = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return instance;
}

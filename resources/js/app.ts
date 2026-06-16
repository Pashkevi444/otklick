import '../css/app.css';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ToastHost from './Components/ToastHost.vue';
import { useToast } from './composables/useToast';

const appName = import.meta.env.VITE_APP_NAME ?? 'Отклик';
const toast = useToast();

// Серверная ошибка (500 / не-Inertia ответ): вместо белой модалки на весь экран —
// аккуратный тост снизу.
router.on('httpException', (event) => {
    event.preventDefault();
    toast.error('Произошла ошибка на сервере. Мы записали детали — попробуйте ещё раз.');
});

// Сетевой сбой запроса.
router.on('networkError', (event) => {
    event.preventDefault();
    toast.error('Не удалось выполнить запрос. Проверьте соединение и попробуйте ещё раз.');
});

// Flash-сообщения после действий показываем тостом.
router.on('success', (event) => {
    const flash = event.detail.page.props.flash;
    if (flash?.error) {
        toast.error(flash.error);
    } else if (flash?.success) {
        toast.success(flash.success);
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => [h(App, props), h(ToastHost)] })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#2E74B5',
    },
});

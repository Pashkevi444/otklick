import { ref } from 'vue';

// Признак тёмной темы. Источник истины — класс .dark на <html>
// (его ставит инлайн-скрипт в app.blade.php до рендера).
const isDark = ref<boolean>(typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));

export function useTheme(): { isDark: typeof isDark; toggle: () => void } {
    const toggle = (): void => {
        isDark.value = !isDark.value;
        document.documentElement.classList.toggle('dark', isDark.value);
        try {
            localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
        } catch (e) {
            // localStorage недоступен — тема просто не сохранится между визитами.
        }
    };

    return { isDark, toggle };
}

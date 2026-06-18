import { usePage } from '@inertiajs/vue3';

/**
 * Проверка права текущего пользователя (матрица мемберов). Владелец/супер-админ
 * получают все права в `auth.user.permissions` с бэкенда, поэтому отдельная
 * проверка роли не нужна. Используется для показа/скрытия кнопок
 * редактирования/удаления в гридах.
 */
export function useCan(): (permission: string) => boolean {
    const page = usePage();

    return (permission: string): boolean => page.props.auth.user?.permissions?.includes(permission) ?? false;
}

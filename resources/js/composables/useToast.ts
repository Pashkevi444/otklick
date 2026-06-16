import { reactive } from 'vue';

export type ToastType = 'error' | 'success' | 'info';

export interface Toast {
    id: number;
    message: string;
    type: ToastType;
}

const state = reactive<{ items: Toast[] }>({ items: [] });
let seq = 0;

function dismiss(id: number): void {
    const i = state.items.findIndex((t) => t.id === id);
    if (i !== -1) {
        state.items.splice(i, 1);
    }
}

function push(message: string, type: ToastType, timeout: number): void {
    if (!message) {
        return;
    }
    const id = ++seq;
    state.items.push({ id, message, type });
    if (timeout > 0) {
        window.setTimeout(() => dismiss(id), timeout);
    }
}

/**
 * Глобальные всплывающие уведомления (тосты). Источник истины — общий reactive-стор.
 */
export function useToast() {
    return {
        items: state.items,
        dismiss,
        error: (m: string): void => push(m, 'error', 7000),
        success: (m: string): void => push(m, 'success', 4000),
        info: (m: string): void => push(m, 'info', 5000),
    };
}

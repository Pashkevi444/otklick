// Универсальный грид: типы и клиентская логика фильтрации/сортировки/формата.
// Данные грузятся целиком (объёмы CRM малы), фильтр и сортировка — в браузере.

export type ColumnType = 'text' | 'number' | 'select' | 'date' | 'bool' | 'badge';

export interface ColumnDef {
    key: string; // путь с точками: 'title', 'client.name', 'custom.<key>'
    label: string;
    type: ColumnType;
    options?: string[]; // варианты для select/badge
    sortable?: boolean; // по умолчанию true
    always?: boolean; // нельзя скрыть/убрать из колонок
}

export interface Filter {
    field: string;
    op: string;
    value: unknown;
}

export interface GridConfig {
    columns: string[]; // видимые ключи колонок по порядку
    filters: Filter[];
    sort: { field: string; dir: 'asc' | 'desc' } | null;
}

export type Row = Record<string, unknown>;

/** Доступные операторы по типу колонки. */
export const OPS_BY_TYPE: Record<ColumnType, { op: string; label: string }[]> = {
    text: [{ op: 'contains', label: 'содержит' }],
    number: [
        { op: 'eq', label: '=' },
        { op: 'gte', label: '≥' },
        { op: 'lte', label: '≤' },
    ],
    date: [
        { op: 'gte', label: 'с' },
        { op: 'lte', label: 'по' },
    ],
    select: [{ op: 'eq', label: '=' }],
    badge: [{ op: 'eq', label: '=' }],
    bool: [{ op: 'eq', label: '=' }],
};

/** Значение по пути с точками ('client.name', 'custom.budget'). */
export function resolvePath(row: Row, path: string): unknown {
    return path.split('.').reduce<unknown>((acc, part) => {
        if (acc !== null && typeof acc === 'object' && part in (acc as Row)) {
            return (acc as Row)[part];
        }
        return undefined;
    }, row);
}

function matches(row: Row, filter: Filter): boolean {
    const raw = resolvePath(row, filter.field);
    const fv = filter.value;
    if (fv === '' || fv === null || fv === undefined) return true; // пустой фильтр не режет

    switch (filter.op) {
        case 'contains':
            return String(raw ?? '').toLowerCase().includes(String(fv).toLowerCase());
        case 'eq':
            if (typeof fv === 'boolean') return Boolean(raw) === fv;
            return String(raw ?? '') === String(fv);
        case 'gte':
            return raw != null && raw !== '' && cmp(raw, fv) >= 0;
        case 'lte':
            return raw != null && raw !== '' && cmp(raw, fv) <= 0;
        default:
            return true;
    }
}

/** Сравнение двух значений: числовое, если оба числа; иначе строковое. */
function cmp(a: unknown, b: unknown): number {
    const na = Number(a);
    const nb = Number(b);
    if (!Number.isNaN(na) && !Number.isNaN(nb) && a !== '' && b !== '') {
        return na - nb;
    }
    return String(a ?? '').localeCompare(String(b ?? ''), 'ru');
}

export function applyFilters(rows: Row[], filters: Filter[]): Row[] {
    if (filters.length === 0) return rows;
    return rows.filter((row) => filters.every((f) => matches(row, f)));
}

export function applySort(rows: Row[], sort: GridConfig['sort']): Row[] {
    if (!sort) return rows;
    const dir = sort.dir === 'desc' ? -1 : 1;
    return [...rows].sort((a, b) => cmp(resolvePath(a, sort.field), resolvePath(b, sort.field)) * dir);
}

/** Человекочитаемое значение ячейки. */
export function formatCell(value: unknown, type: ColumnType): string {
    if (value === null || value === undefined || value === '') return '—';
    if (type === 'bool') return value ? 'Да' : '—';
    if (type === 'number') return new Intl.NumberFormat('ru-RU').format(Number(value));
    return String(value);
}

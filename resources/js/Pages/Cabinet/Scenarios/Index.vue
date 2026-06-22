<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import type { RequestPayload } from '@inertiajs/core';
import { computed, reactive, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Toggle from '@/Components/Toggle.vue';
import FlowCanvas from '@/Components/FlowCanvas.vue';
import Hint from '@/Components/Hint.vue';
import Pagination from '@/Components/Pagination.vue';

interface OptionEdge {
    label: string;
    next: string;
}
interface Variant {
    label: string;
    next: string;
}
interface NodeImage {
    path: string;
    url: string;
}
interface FlowNodeDef {
    type?: string;
    title?: string;
    text?: string;
    action?: string;
    options?: OptionEdge[];
    variable?: string;
    next?: string;
    operator?: string;
    value?: string;
    else?: string;
    variants?: Variant[];
    images?: NodeImage[];
    knowledge_id?: string;
    position?: { x: number; y: number };
}
interface AbStat {
    variant: string;
    total: number;
    booked: number;
    conversion: number;
}
interface FlowRow {
    id: string;
    name: string;
    is_active: boolean;
    triggers: string[];
    definition: { start?: string; nodes?: Record<string, FlowNodeDef> };
    ab?: AbStat[];
}
type NodeKind = 'message' | 'input' | 'condition' | 'split';
interface NodeEdit {
    id: string;
    type: NodeKind;
    title: string;
    text: string;
    action: string;
    options: OptionEdge[];
    variable: string;
    next: string;
    operator: string;
    value: string;
    else: string;
    variants: Variant[];
    images: NodeImage[];
    knowledgeId: string;
    x: number;
    y: number;
}
interface Option {
    value: string;
    label: string;
}

interface KnowledgeOption {
    id: string;
    title: string;
}
interface FlowTemplate {
    key: string;
    name: string;
    description: string;
    businessType: string | null;
    triggers: string[];
    definition: { start?: string; nodes?: Record<string, FlowNodeDef> };
}
const props = defineProps<{
    flows: FlowRow[];
    pagination: { current: number; last: number; total: number };
    actionOptions: Option[];
    nodeTypeOptions: Option[];
    operatorOptions: Option[];
    yclientsActive: boolean;
    knowledgeEntries: KnowledgeOption[];
    templates: FlowTemplate[];
    businessTypes: Option[];
}>();

// Готовые шаблоны прячем за кнопкой, чтобы не занимали весь экран (их много).
const showTemplates = ref(false);
// Фильтр пикера шаблонов: поиск + тип бизнеса (иначе листать десятки штук тяжело).
const tplQuery = ref('');
const tplType = ref<string>('');

// Чипы-фильтры по типу бизнеса с количеством.
const tplChips = computed(() => {
    const chips = [{ key: '', label: 'Все', count: props.templates.length }];
    const general = props.templates.filter((t) => !t.businessType).length;
    if (general) chips.push({ key: 'general', label: 'Общие', count: general });
    for (const bt of props.businessTypes) {
        const count = props.templates.filter((t) => t.businessType === bt.value).length;
        if (count) chips.push({ key: bt.value, label: bt.label, count });
    }
    return chips;
});

// Шаблоны, отфильтрованные (поиск + тип) и сгруппированные по типу бизнеса:
// сперва «Общие» (businessType=null), затем по нишам в порядке реестра.
const templateGroups = computed<{ key: string; label: string; items: FlowTemplate[] }[]>(() => {
    const q = tplQuery.value.trim().toLowerCase();
    const match = (t: FlowTemplate): boolean => {
        const typeKey = t.businessType ?? 'general';
        if (tplType.value && typeKey !== tplType.value) return false;
        if (!q) return true;
        return t.name.toLowerCase().includes(q) || t.description.toLowerCase().includes(q) || t.triggers.some((x) => x.toLowerCase().includes(q));
    };
    const groups: { key: string; label: string; items: FlowTemplate[] }[] = [];
    const general = props.templates.filter((t) => !t.businessType && match(t));
    if (general.length) groups.push({ key: 'general', label: 'Общие сценарии', items: general });
    for (const bt of props.businessTypes) {
        const items = props.templates.filter((t) => t.businessType === bt.value && match(t));
        if (items.length) groups.push({ key: bt.value, label: bt.label, items });
    }
    return groups;
});

const editing = ref<string | null | 'new'>(null);
const form = useForm<{ id: string | null; name: string; is_active: boolean; triggers: string; start: string; nodes: NodeEdit[] }>({
    id: null,
    name: '',
    is_active: true,
    triggers: '',
    start: 'n1',
    nodes: [],
});

const nodeIds = computed(() => form.nodes.map((n) => n.id));

// Переменные, доступные для подстановки: встроенные из карточки + захваченные в вопросах.
const availableVars = computed<string[]>(() => {
    const captured = form.nodes.filter((n) => n.type === 'input' && n.variable.trim() !== '').map((n) => n.variable.trim());
    return ['client_name', 'client_phone', 'client_email', ...new Set(captured)];
});

// Человеческие подписи встроенных переменных (остальные — как назвал бизнес в вопросе).
const VAR_LABELS: Record<string, string> = {
    client_name: 'Имя клиента',
    client_phone: 'Телефон клиента',
    client_email: 'Email клиента',
};
const variableLabel = (v: string): string => VAR_LABELS[v] ?? `Ответ клиента: ${v}`;
// Опции для выбора переменной в условии (вместо ручного ввода имени — выпадающий список).
const variableOptions = computed<{ value: string; label: string }[]>(() =>
    availableVars.value.map((v) => ({ value: v, label: variableLabel(v) })),
);

// Дружелюбное имя узла для выпадашек «→ узел»: своё название бизнеса > начало текста
// > тип/действие. Бизнес видит «n2 · Запись», а не безликое «n2».
const nodeLabel = (id: string): string => {
    const n = form.nodes.find((x) => x.id === id);
    if (!n) return id;
    if (n.title.trim()) return `${id} · ${n.title.trim()}`;
    const snippet = (n.text || '').replace(/\s+/g, ' ').trim().slice(0, 30);
    if (snippet) return `${id} · ${snippet}`;
    const kind =
        n.type === 'input'
            ? 'Вопрос'
            : n.type === 'condition'
              ? 'Условие'
              : n.type === 'split'
                ? 'A/B'
                : n.action !== 'none'
                  ? actionLabel(n.action)
                  : 'Сообщение';
    return `${id} · ${kind}`;
};

// Переходы узла (куда он может вести) — для проверки целостности и достижимости.
const nodeTargets = (n: NodeEdit): string[] => {
    if (n.type === 'input') return [n.next];
    if (n.type === 'condition') return [n.next, n.else];
    if (n.type === 'split') return n.variants.map((v) => v.next);
    if (n.type === 'message' && n.action === 'none') return n.options.map((o) => o.next);
    return [];
};

// Живая валидация воронки: битые ссылки, незаданные ветки, недостижимые узлы.
const issues = computed<string[]>(() => {
    const out: string[] = [];
    const ids = new Set(form.nodes.map((n) => n.id));

    if (!ids.has(form.start)) out.push('Не задан стартовый узел.');

    for (const n of form.nodes) {
        for (const t of nodeTargets(n)) {
            if (t && !ids.has(t)) out.push(`Узел ${n.id}: переход на несуществующий узел «${t}».`);
        }
        if (n.type === 'input' && !n.variable.trim()) out.push(`Узел ${n.id} (вопрос): не задана переменная.`);
        if (n.type === 'input' && !n.next) out.push(`Узел ${n.id} (вопрос): не задан переход «дальше».`);
        if (n.type === 'condition' && (!n.next || !n.else)) out.push(`Узел ${n.id} (условие): задай обе ветки — ДА и НЕТ.`);
        if (n.type === 'split' && n.variants.filter((v) => v.next).length < 2) out.push(`Узел ${n.id} (A/B): нужно минимум 2 варианта.`);
        if (n.type === 'message' && n.action === 'show_knowledge' && !n.knowledgeId) out.push(`Узел ${n.id}: не выбран элемент базы знаний для показа.`);
    }

    if (ids.has(form.start)) {
        const seen = new Set<string>([form.start]);
        const queue: string[] = [form.start];
        while (queue.length) {
            const id = queue.shift();
            const node = form.nodes.find((n) => n.id === id);
            if (!node) continue;
            for (const t of nodeTargets(node)) {
                if (t && ids.has(t) && !seen.has(t)) {
                    seen.add(t);
                    queue.push(t);
                }
            }
        }
        for (const n of form.nodes) {
            if (!seen.has(n.id)) out.push(`Узел ${n.id} недостижим из старта.`);
        }
    }

    return out;
});

const blankNode = (id: string, x = 0, y = 0): NodeEdit => ({
    id, type: 'message', title: '', text: '', action: 'none', options: [],
    variable: '', next: '', operator: 'eq', value: '', else: '', variants: [], images: [], knowledgeId: '', x, y,
});

const newFlow = (): void => {
    editing.value = 'new';
    form.defaults({ id: null, name: '', is_active: true, triggers: '', start: 'n1', nodes: [] });
    form.reset();
    form.nodes = [blankNode('n1')];
    form.start = 'n1';
};

// Узлы из definition (JSON) → редактируемые NodeEdit. Общая логика для правки
// существующего сценария и старта из готового шаблона.
const nodesFromDefinition = (nodesObj: Record<string, FlowNodeDef>): NodeEdit[] =>
    Object.entries(nodesObj).map(([id, n]) => ({
        id,
        type: (['input', 'condition', 'split'].includes(n.type ?? '') ? n.type : 'message') as NodeKind,
        title: n.title ?? '',
        text: n.text ?? '',
        action: n.action ?? 'none',
        options: (n.options ?? []).map((o) => ({ label: o.label, next: o.next })),
        variable: n.variable ?? '',
        next: n.next ?? '',
        operator: n.operator ?? 'eq',
        value: n.value ?? '',
        else: n.else ?? '',
        variants: (n.variants ?? []).map((v) => ({ label: v.label, next: v.next })),
        images: (n.images ?? []).map((im) => ({ path: im.path, url: im.url })),
        knowledgeId: n.knowledge_id ?? '',
        x: n.position?.x ?? 0,
        y: n.position?.y ?? 0,
    }));

const editFlow = (f: FlowRow): void => {
    editing.value = f.id;
    const nodes = nodesFromDefinition(f.definition.nodes ?? {});
    form.id = f.id;
    form.name = f.name;
    form.is_active = f.is_active;
    form.triggers = f.triggers.join(', ');
    form.start = f.definition.start ?? nodes[0]?.id ?? 'n1';
    form.nodes = nodes.length ? nodes : [blankNode('n1')];
};

// Открыть редактор, предзаполнив готовым шаблоном (новый сценарий — id=null).
const startFromTemplate = (t: FlowTemplate): void => {
    editing.value = 'new';
    form.defaults({ id: null, name: '', is_active: true, triggers: '', start: 'n1', nodes: [] });
    form.reset();
    const nodes = nodesFromDefinition(t.definition.nodes ?? {});
    form.id = null;
    form.name = t.name;
    form.is_active = true;
    form.triggers = t.triggers.join(', ');
    form.start = t.definition.start ?? nodes[0]?.id ?? 'n1';
    form.nodes = nodes.length ? nodes : [blankNode('n1')];
};

const cancel = (): void => {
    editing.value = null;
    form.clearErrors();
};

const addNode = (): void => {
    const nums = form.nodes.map((n) => parseInt(n.id.replace(/\D/g, ''), 10) || 0);
    const i = form.nodes.length;
    form.nodes.push(blankNode('n' + (Math.max(0, ...nums) + 1), (i % 4) * 230, Math.floor(i / 4) * 150));
};

const onNodeMoved = (id: string, x: number, y: number): void => {
    const n = form.nodes.find((nn) => nn.id === id);
    if (n) {
        n.x = x;
        n.y = y;
    }
};
const focusNode = (id: string): void => {
    document.getElementById(`node-card-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

// Рисование/удаление стрелок на схеме → проставляем переход в нужное поле узла.
const setHandleTarget = (source: string, handle: string, target: string): void => {
    const n = form.nodes.find((nn) => nn.id === source);
    if (!n) return;
    if (handle === 'no') n.else = target;
    else if (handle === 'next' || handle === 'yes') n.next = target;
    else if (handle.startsWith('v')) {
        const v = n.variants[Number(handle.slice(1))];
        if (v) v.next = target;
    } else if (handle.startsWith('o')) {
        const o = n.options[Number(handle.slice(1))];
        if (o) o.next = target;
    }
};
const onNodeConnect = (source: string, handle: string, target: string): void => setHandleTarget(source, handle, target);
const onNodeDisconnect = (source: string, handle: string): void => setHandleTarget(source, handle, '');
const removeNode = (id: string): void => {
    form.nodes = form.nodes.filter((n) => n.id !== id);
    form.nodes.forEach((n) => {
        n.options = n.options.filter((o) => o.next !== id);
        n.variants = n.variants.filter((v) => v.next !== id);
        if (n.next === id) n.next = '';
        if (n.else === id) n.else = '';
    });
    if (form.start === id) form.start = form.nodes[0]?.id ?? '';
};
const addOption = (node: NodeEdit): void => {
    node.options.push({ label: '', next: form.nodes[0]?.id ?? '' });
};
const removeOption = (node: NodeEdit, i: number): void => {
    node.options.splice(i, 1);
};
const addVariant = (node: NodeEdit): void => {
    node.variants.push({ label: String.fromCharCode(65 + node.variants.length), next: form.nodes[0]?.id ?? '' });
};
const removeVariant = (node: NodeEdit, i: number): void => {
    node.variants.splice(i, 1);
};

// Загрузка фото узла: грузим сразу на сервер (как в базе знаний) и кладём {path,url}
// в узел — сам сценарий сохранится обычным PUT/POST, URL уже внутри definition.
const uploadingImage = ref<string | null>(null);
const uploadNodeImage = async (node: NodeEdit, event: Event): Promise<void> => {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    uploadingImage.value = node.id;
    try {
        for (const file of files) {
            const body = new FormData();
            body.append('image', file);
            const res = await fetch('/cabinet/scenarios/image', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
                credentials: 'same-origin',
                body,
            });
            if (res.ok) node.images.push((await res.json()) as NodeImage);
        }
    } finally {
        uploadingImage.value = null;
        input.value = '';
    }
};
const removeNodeImage = (node: NodeEdit, i: number): void => {
    node.images.splice(i, 1);
};

const buildDefinition = (): { start: string; nodes: Record<string, FlowNodeDef> } => {
    const nodes: Record<string, FlowNodeDef> = {};
    for (const n of form.nodes) {
        if (n.type === 'input') {
            nodes[n.id] = { type: 'input', text: n.text, variable: n.variable.trim(), next: n.next, images: n.images };
        } else if (n.type === 'condition') {
            nodes[n.id] = { type: 'condition', variable: n.variable.trim(), operator: n.operator, value: n.value, next: n.next, else: n.else };
        } else if (n.type === 'split') {
            nodes[n.id] = { type: 'split', variants: n.variants.filter((v) => v.next) };
        } else {
            nodes[n.id] = {
                type: 'message',
                text: n.text,
                action: n.action,
                options: n.action === 'none' ? n.options.filter((o) => o.label.trim() !== '' && o.next) : [],
                images: n.images,
                // Для действия «показать элемент базы знаний» — id выбранного элемента.
                ...(n.action === 'show_knowledge' ? { knowledge_id: n.knowledgeId } : {}),
            };
        }
        if (n.title.trim()) nodes[n.id].title = n.title.trim(); // своё название шага (движок игнорирует, для людей)
        nodes[n.id].position = { x: n.x, y: n.y }; // позиция на холсте (бэкенд игнорирует)
    }
    return { start: form.start, nodes };
};

const save = (): void => {
    const payload = {
        name: form.name,
        is_active: form.is_active,
        triggers: form.triggers.split(',').map((t) => t.trim()).filter((t) => t !== ''),
        definition: buildDefinition(),
    } as unknown as RequestPayload;
    const opts = { preserveScroll: true, onSuccess: () => (editing.value = null) };
    if (form.id) {
        router.put(`/cabinet/scenarios/${form.id}`, payload, opts);
    } else {
        router.post('/cabinet/scenarios', payload, opts);
    }
};

const toggle = (f: FlowRow): void => router.post(`/cabinet/scenarios/${f.id}/toggle`, {}, { preserveScroll: true });
const destroy = (f: FlowRow): void => {
    if (confirm(`Удалить сценарий «${f.name}»?`)) router.delete(`/cabinet/scenarios/${f.id}`, { preserveScroll: true });
};

// Подписи действий — на случай, если опции нет в списке (например start_booking
// скрыт из-за отключённого YClients, но уже выбран в существующем узле).
const KNOWN_ACTION_LABELS: Record<string, string> = {
    none: 'Нет (показать кнопки/сообщение)',
    start_booking: 'Начать запись в YClients',
    show_knowledge: 'Показать элемент базы знаний',
    escalate: 'Позвать администратора',
    end: 'Завершить сценарий',
};
const actionLabel = (value: string): string => props.actionOptions.find((a) => a.value === value)?.label ?? KNOWN_ACTION_LABELS[value] ?? value;

// Опции действия для узла: список с сервера + текущее действие, если его там нет
// (чтобы выбранное действие не «потерялось» из дропдауна и не затёрлось при сохранении).
const actionOptionsFor = (node: NodeEdit): Option[] =>
    node.action && !props.actionOptions.some((a) => a.value === node.action)
        ? [{ value: node.action, label: actionLabel(node.action) }, ...props.actionOptions]
        : props.actionOptions;

// Литералы с {{ }} нельзя писать прямо в шаблоне (Vue примет за интерполяцию) — выносим в строки.
const msgPlaceholder = 'Сообщение бота. Можно подставлять {{переменную}}';
const inputHint = 'Ответ клиента сохранится и его можно вставить в любой текст как {{name}}.';
const varTag = (v: string): string => '{{' + v + '}}';

// --- Тест-прогон воронки (сухой, без реальных эффектов) ---
interface TestMsg {
    from: 'bot' | 'you';
    text: string;
    note?: string;
    buttons?: string[];
    images?: string[];
}
interface TestState {
    node: string | null;
    vars: Record<string, unknown>;
}
interface TestResult {
    reply: string | null;
    buttons: string[];
    vars: Record<string, unknown>;
    node: string | null;
    done: boolean;
    note: string | null;
    images?: string[];
}
const testOpen = ref(false);
const testLog = ref<TestMsg[]>([]);
const testState = ref<TestState | null>(null);
const testDone = ref(false);
const testInput = ref('');
const testBusy = ref(false);

const xsrf = (): string =>
    decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

const testStep = async (text: string | null): Promise<void> => {
    testBusy.value = true;
    try {
        const res = await fetch('/cabinet/scenarios/test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ definition: buildDefinition(), state: testState.value, text }),
        });
        const d: TestResult = await res.json();
        if (d.reply) testLog.value.push({ from: 'bot', text: d.reply, note: d.note ?? undefined, buttons: d.buttons, images: d.images });
        else if (d.note) testLog.value.push({ from: 'bot', text: '—', note: d.note, images: d.images });
        testDone.value = d.done;
        testState.value = d.done ? null : { node: d.node, vars: d.vars };
    } finally {
        testBusy.value = false;
    }
};
const testStart = (): void => {
    testOpen.value = true;
    testLog.value = [];
    testState.value = null;
    testDone.value = false;
    void testStep(null);
};
const testSend = (text?: string): void => {
    const t = (text ?? testInput.value).trim();
    if (t === '' || testDone.value || testBusy.value) return;
    testLog.value.push({ from: 'you', text: t });
    testInput.value = '';
    void testStep(t);
};
</script>

<template>
    <Head title="Сценарии" />

    <AppLayout title="Сценарии">
        <p class="mb-4 max-w-2xl text-sm text-slate-500">
            Конструктор воронок: задайте «если клиент написал X → ответь Y, предложи кнопки Z, сделай действие».
            Сработавший сценарий ведёт диалог по кнопкам; не совпало — отвечает ИИ по базе знаний.
        </p>

        <!-- Список сценариев -->
        <div v-if="editing === null">
            <div class="mb-4 flex flex-wrap gap-2">
                <button type="button" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96]" @click="newFlow">
                    + Новый сценарий с нуля
                </button>
                <button
                    v-if="templates.length"
                    type="button"
                    class="rounded-lg border border-[#2E74B5] px-4 py-2 text-sm font-medium text-[#2E74B5] hover:bg-[#EAF2FB] dark:border-sky-400 dark:text-sky-300 dark:hover:bg-white/5"
                    @click="showTemplates = !showTemplates"
                >
                    {{ showTemplates ? 'Скрыть шаблоны' : '📋 Готовые шаблоны' }}
                </button>
            </div>

            <!-- Готовые шаблоны, сгруппированные по типу бизнеса (сперва «Общие») -->
            <div v-if="showTemplates && templates.length" class="mb-5">
                <div class="mb-3 text-sm font-medium text-slate-600 dark:text-slate-300">Выберите готовый шаблон — он откроется в редакторе, останется поправить под себя:</div>

                <!-- Поиск + фильтр по типу бизнеса -->
                <input
                    v-model="tplQuery"
                    type="search"
                    placeholder="Поиск шаблона по названию или фразе запуска…"
                    class="mb-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
                />
                <div class="mb-4 flex flex-wrap gap-2">
                    <button
                        v-for="chip in tplChips"
                        :key="chip.key"
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-medium transition"
                        :class="tplType === chip.key ? 'bg-[#2E74B5] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/10 dark:text-slate-300'"
                        @click="tplType = chip.key"
                    >
                        {{ chip.label }} <span class="opacity-70">{{ chip.count }}</span>
                    </button>
                </div>

                <div v-if="templateGroups.length === 0" class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">
                    Ничего не найдено — измените запрос или фильтр.
                </div>

                <div v-for="g in templateGroups" :key="g.key" class="mb-4">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ g.label }}</span>
                        <span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <button
                            v-for="t in g.items"
                            :key="t.key"
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white p-4 text-left transition hover:border-[#2E74B5] hover:shadow-sm dark:border-white/10 dark:bg-white/5"
                            @click="startFromTemplate(t)"
                        >
                            <div class="text-sm font-semibold text-[#1F4E79] dark:text-sky-200">{{ t.name }}</div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ t.description }}</div>
                            <div class="mt-2 text-[11px] text-slate-400">Запуск по: {{ t.triggers.slice(0, 3).join(', ') }}…</div>
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="flows.length === 0" class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-400">
                Сценариев пока нет. Создайте первый — возьмите шаблон выше или начните с нуля.
            </div>

            <div class="space-y-3">
                <div v-for="f in flows" :key="f.id" class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ f.name }}</div>
                            <div class="mt-1 text-xs text-slate-400">Запуск по фразам: {{ f.triggers.join(', ') || '—' }}</div>
                        </div>
                        <div class="flex flex-none items-center gap-3">
                            <Toggle :model-value="f.is_active" @update:model-value="toggle(f)" />
                            <button type="button" class="text-sm text-[#2E74B5] hover:underline dark:text-sky-300" @click="editFlow(f)">Редактировать</button>
                            <button type="button" class="text-sm text-red-600 hover:underline" @click="destroy(f)">Удалить</button>
                        </div>
                    </div>

                    <!-- A/B-конверсия по вариантам -->
                    <div v-if="f.ab && f.ab.length" class="mt-3 border-t border-slate-100 pt-3 dark:border-white/10">
                        <div class="mb-1 text-xs font-medium text-slate-500">A/B-конверсия (запись)</div>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="s in f.ab" :key="s.variant" class="rounded-lg bg-[#EAF2FB] px-2.5 py-1 text-xs text-[#1F4E79] dark:bg-white/10 dark:text-sky-200">
                                {{ s.variant }}: <b>{{ s.conversion }}%</b> ({{ s.booked }}/{{ s.total }})
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Пагинация списка сценариев -->
            <Pagination :current="pagination.current" :last="pagination.last" :total="pagination.total" />
        </div>

        <!-- Редактор -->
        <form v-else class="max-w-3xl space-y-5" @submit.prevent="save">
            <!-- Простое объяснение для новичка -->
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100">
                <div class="font-medium">Как это работает 👇</div>
                <p class="mt-1 text-xs leading-relaxed text-sky-800/90 dark:text-sky-100/80">
                    Сценарий — это «если клиент написал нужное слово, бот ведёт его по шагам». Шаги называются <b>узлами</b>: бот пишет сообщение,
                    задаёт вопрос, делает развилку или A/B-тест. Соберите шаги ниже, перетащите их на схеме как удобно, и нажмите
                    <b>«🧪 Тест-прогон»</b>, чтобы проверить, как пойдёт диалог — без реальной переписки.
                </p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Название
                            <Hint text="Внутреннее имя сценария — только для вас, клиент его не видит." />
                        </label>
                        <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Напр. Акция месяца" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Запуск по фразам (через запятую)
                            <Hint text="Слова, при которых сработает сценарий. Перечислите через запятую: акция, скидка, промо. Ловятся любые формы слова (акции, акцию) и опечатки по смыслу." />
                        </label>
                        <input v-model="form.triggers" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="акция, скидка, промо" />
                        <div class="mt-2 flex items-start gap-2.5 rounded-lg border border-amber-300/70 bg-amber-50 px-3 py-2.5 text-sm font-medium text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                            <span class="mt-px flex-none rounded-md bg-amber-400/30 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-amber-900 dark:text-amber-100">Совет</span>
                            <span>На один ключ — <b>одно-два слова без предлогов</b> (напр. «стрижка», а не «как записаться на стрижку»). Так бот точнее ловит запрос и не путает сценарии. Разные ключи — через запятую.</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <Toggle v-model="form.is_active" /> Сценарий включён
                    <Hint text="Выключенный сценарий не срабатывает у клиентов, но остаётся в списке." />
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Стартовый узел
                        <Hint text="С какого шага начинается воронка, когда сработал запуск по фразам." />
                    </label>
                    <select v-model="form.start" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option v-for="id in nodeIds" :key="id" :value="id">{{ nodeLabel(id) }}</option>
                    </select>
                </div>

                <!-- Доступные переменные для подстановки -->
                <div class="mt-4 border-t border-slate-100 pt-3 dark:border-white/10">
                    <div class="mb-1 text-xs font-medium text-slate-500">Переменные для вставки в тексты
                        <Hint text="Вставьте такую переменную в любой текст — бот подставит значение. client_* берутся из карточки клиента сами; остальные — это ответы из узлов-вопросов." />
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <code v-for="v in availableVars" :key="v" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ varTag(v) }}</code>
                    </div>
                </div>
            </div>

            <!-- Визуальная схема воронки: тяни узлы мышкой, клик — к редактору узла -->
            <div>
                <div class="mb-1 text-sm font-medium text-slate-700 dark:text-slate-200">Схема воронки</div>
                <FlowCanvas :nodes="form.nodes" :start="form.start" @moved="onNodeMoved" @select="focusNode" @connect="onNodeConnect" @disconnect="onNodeDisconnect" />
                <p class="mt-1 text-xs text-slate-400">Тяни узлы мышкой; от нижних точек тяни стрелку к другому узлу — задашь переход. Клик по узлу откроет его настройки. Стрелку можно выделить и удалить (Delete).</p>
            </div>

            <!-- Тест-прогон: проверить воронку, не написав живому боту -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium text-slate-700 dark:text-slate-200">🧪 Тест-прогон</div>
                    <button type="button" class="text-sm text-[#2E74B5] hover:underline dark:text-sky-300" @click="testStart">{{ testLog.length ? 'Перезапустить' : 'Запустить' }}</button>
                </div>

                <div v-if="testOpen" class="mt-3">
                    <div class="max-h-72 space-y-2 overflow-y-auto rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                        <div v-for="(m, i) in testLog" :key="i" :class="m.from === 'you' ? 'text-right' : 'text-left'">
                            <span class="inline-block max-w-[85%] rounded-2xl px-3 py-1.5 text-sm" :class="m.from === 'you' ? 'bg-[#2E74B5] text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 dark:bg-white/10 dark:text-slate-200 dark:ring-white/10'">{{ m.text }}</span>
                            <div v-if="m.images && m.images.length" class="mt-1 flex flex-wrap gap-1" :class="m.from === 'you' ? 'justify-end' : ''">
                                <img v-for="(src, k) in m.images" :key="k" :src="src" alt="" class="h-20 w-20 rounded-lg object-cover ring-1 ring-slate-200 dark:ring-white/10" />
                            </div>
                            <div v-if="m.note" class="mt-0.5 text-xs italic text-slate-400">{{ m.note }}</div>
                            <div v-if="m.buttons && m.buttons.length" class="mt-1 flex flex-wrap gap-1" :class="m.from === 'you' ? 'justify-end' : ''">
                                <button v-for="b in m.buttons" :key="b" type="button" class="rounded-full border border-[#2E74B5] px-2.5 py-0.5 text-xs text-[#2E74B5] hover:bg-[#EAF2FB] dark:border-sky-400 dark:text-sky-300" @click="testSend(b)">{{ b }}</button>
                            </div>
                        </div>
                        <div v-if="testDone" class="text-center text-xs text-slate-400">— конец сценария —</div>
                    </div>
                    <div v-if="!testDone" class="mt-2 flex gap-2">
                        <input v-model="testInput" type="text" class="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm" placeholder="Ответ клиента…" :disabled="testBusy" @keydown.enter.prevent="testSend()" />
                        <button type="button" class="rounded-lg bg-[#2E74B5] px-3 py-1.5 text-sm text-white hover:bg-[#255f96] disabled:opacity-50" :disabled="testBusy" @click="testSend()">→</button>
                    </div>
                </div>
                <p v-else class="mt-1 text-xs text-slate-400">Прогон по текущей (несохранённой) схеме — без реальной записи/эскалации.</p>
            </div>

            <!-- Узлы -->
            <div v-for="node in form.nodes" :id="`node-card-${node.id}`" :key="node.id" class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-500 dark:bg-white/10 dark:text-slate-300">{{ node.id }}</span>
                        <span v-if="form.start === node.id" class="rounded bg-[#EAF2FB] px-1.5 py-0.5 text-xs text-[#2E74B5]">старт</span>
                        <input
                            v-model="node.title"
                            type="text"
                            maxlength="40"
                            placeholder="Название шага (напр. Приветствие)"
                            class="min-w-0 flex-1 rounded-lg border border-transparent bg-transparent px-1.5 py-0.5 text-sm font-semibold text-[#1F4E79] outline-none transition hover:border-slate-200 focus:border-[#2E74B5] dark:text-sky-200 dark:hover:border-white/10"
                        />
                    </div>
                    <button v-if="form.nodes.length > 1" type="button" class="flex-none text-xs text-red-600 hover:underline" @click="removeNode(node.id)">Удалить узел</button>
                </div>
                <div class="mb-3">
                    <label class="mb-1 block text-xs font-medium text-slate-500">Тип узла
                        <Hint text="Сообщение — бот пишет текст и кнопки. Вопрос — спрашивает и запоминает ответ. Условие — развилка по переменной (если…то). A/B-сплит — делит людей на варианты, чтобы сравнить, что лучше записывает." />
                    </label>
                    <select v-model="node.type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:w-auto">
                        <option v-for="o in nodeTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>

                <!-- СООБЩЕНИЕ: текст + действие + кнопки -->
                <template v-if="node.type === 'message'">
                    <textarea v-model="node.text" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :placeholder="msgPlaceholder"></textarea>
                    <div class="mt-3">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Действие
                            <Hint text="Что бот сделает на этом шаге. «Нет» — просто покажет сообщение и кнопки. Или сразу начнёт запись в YClients / позовёт администратора / завершит сценарий." />
                        </label>
                        <select v-model="node.action" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:w-auto">
                            <option v-for="a in actionOptionsFor(node)" :key="a.value" :value="a.value">{{ a.label }}</option>
                        </select>
                    </div>
                    <div v-if="node.action === 'none'" class="mt-3">
                        <div class="mb-1 text-xs font-medium text-slate-500">Кнопки (вариант → переход к узлу)
                            <Hint text="Клиент нажимает кнопку — бот переходит к указанному узлу. Без кнопок шаг просто покажет сообщение и завершится." />
                        </div>
                        <div class="space-y-2">
                            <div v-for="(opt, i) in node.options" :key="i" class="flex items-center gap-2">
                                <input v-model="opt.label" type="text" class="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm" placeholder="Текст кнопки" />
                                <span class="text-slate-400">→</span>
                                <select v-model="opt.next" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <option v-for="id in nodeIds" :key="id" :value="id">{{ nodeLabel(id) }}</option>
                                </select>
                                <button type="button" class="text-xs text-red-600 hover:underline" @click="removeOption(node, i)">убрать</button>
                            </div>
                        </div>
                        <button type="button" class="mt-2 text-sm text-[#2E74B5] hover:underline dark:text-sky-300" @click="addOption(node)">+ кнопка</button>
                    </div>
                    <!-- Действие «Показать элемент базы знаний» — выбор конкретного элемента -->
                    <div v-else-if="node.action === 'show_knowledge'" class="mt-3">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Какой элемент базы знаний показать
                            <Hint text="Бот отправит текст этого элемента вместе с его фото и ссылками и завершит сценарий. Удобно для кнопок вроде «Барбер Никита» — клиент сразу получит инфо из базы знаний." />
                        </label>
                        <select v-model="node.knowledgeId" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">— выберите элемент —</option>
                            <option v-for="k in knowledgeEntries" :key="k.id" :value="k.id">{{ k.title }}</option>
                        </select>
                        <p v-if="knowledgeEntries.length === 0" class="mt-1 text-xs text-amber-600 dark:text-amber-300">В базе знаний пока нет элементов — сначала добавьте их в разделе «База знаний».</p>
                        <p class="mt-1 text-xs text-slate-400">Текст узла выше (если задан) добавится перед содержимым элемента. Это конечный шаг сценария.</p>
                    </div>
                    <p v-else class="mt-2 text-xs text-slate-400">{{ actionLabel(node.action) }} — это конечный шаг сценария.</p>
                </template>

                <!-- ВОПРОС: приглашение + сохранить ответ в переменную + переход -->
                <template v-else-if="node.type === 'input'">
                    <textarea v-model="node.text" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Вопрос клиенту (например: Как вас зовут?)"></textarea>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Сохранить ответ в переменную
                                <Hint text="Имя латиницей без пробелов: name, phone. Ответ клиента запомнится, и его можно вставить дальше как {{name}}." />
                            </label>
                            <input v-model="node.variable" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm" placeholder="name" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Дальше → узел</label>
                            <select v-model="node.next" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="">—</option>
                                <option v-for="id in nodeIds" :key="id" :value="id">{{ nodeLabel(id) }}</option>
                            </select>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">{{ inputHint }}</p>
                </template>

                <!-- УСЛОВИЕ: если «что проверяем» «как» «значение» → да/нет -->
                <template v-else-if="node.type === 'condition'">
                    <div class="mb-1 text-xs font-medium text-slate-500">Если…
                        <Hint text="Проверяем данные клиента или его ответ из прошлого вопроса и ведём в нужную ветку. Пример: «Имя клиента — содержит — Иван» → ветка ДА, иначе НЕТ. «Заполнена» — просто проверка, что значение вообще есть (тогда поле справа не нужно). Сравнение идёт без участия клиента." />
                    </div>
                    <div class="grid items-center gap-2 sm:grid-cols-[auto_1fr_auto_1fr] sm:gap-3">
                        <span class="text-sm text-slate-500">Если</span>
                        <select v-model="node.variable" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            <option value="">— что проверяем —</option>
                            <option v-for="o in variableOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                            <option v-if="node.variable && !availableVars.includes(node.variable)" :value="node.variable">{{ node.variable }} (своя)</option>
                        </select>
                        <select v-model="node.operator" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            <option v-for="o in operatorOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <input v-if="node.operator !== 'filled'" v-model="node.value" type="text" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm" placeholder="напр. маникюр" />
                        <span v-else class="text-xs text-slate-400">значение не нужно</span>
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-emerald-600">Если ДА → узел</label>
                            <select v-model="node.next" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="">—</option>
                                <option v-for="id in nodeIds" :key="id" :value="id">{{ nodeLabel(id) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-red-600">Если НЕТ → узел</label>
                            <select v-model="node.else" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="">—</option>
                                <option v-for="id in nodeIds" :key="id" :value="id">{{ nodeLabel(id) }}</option>
                            </select>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">Условие проходит без участия клиента — сразу уводит в нужную ветку.</p>
                </template>

                <!-- A/B-СПЛИТ: варианты, между которыми делится трафик -->
                <template v-else>
                    <div class="mb-1 text-xs font-medium text-slate-500">Варианты (трафик делится поровну, липко на клиента)
                        <Hint text="Каждый клиент случайно попадает в один вариант и всегда видит его же. Потом в списке сценариев увидите, какой вариант чаще доводит до записи." />
                    </div>
                    <div class="space-y-2">
                        <div v-for="(v, i) in node.variants" :key="i" class="flex items-center gap-2">
                            <input v-model="v.label" type="text" class="w-20 rounded-lg border border-slate-300 px-3 py-1.5 text-sm" placeholder="A" />
                            <span class="text-slate-400">→</span>
                            <select v-model="v.next" class="flex-1 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="">—</option>
                                <option v-for="id in nodeIds" :key="id" :value="id">{{ nodeLabel(id) }}</option>
                            </select>
                            <button type="button" class="text-xs text-red-600 hover:underline" @click="removeVariant(node, i)">убрать</button>
                        </div>
                    </div>
                    <button type="button" class="mt-2 text-sm text-[#2E74B5] hover:underline dark:text-sky-300" @click="addVariant(node)">+ вариант</button>
                    <p class="mt-2 text-xs text-slate-400">Конверсия по вариантам (% записей) появится в карточке сценария в списке.</p>
                </template>

                <!-- Фото шага: бот пришлёт их настоящими картинками (как в базе знаний) -->
                <div v-if="node.type === 'message' || node.type === 'input'" class="mt-3 border-t border-slate-100 pt-3 dark:border-white/10">
                    <div class="mb-1 text-xs font-medium text-slate-500">Фото на этом шаге
                        <Hint text="Прикреплённые фото бот отправит клиенту настоящими картинками вместе с сообщением (как примеры работ в базе знаний)." />
                    </div>
                    <div v-if="node.images.length" class="mb-2 flex flex-wrap gap-2">
                        <div v-for="(img, i) in node.images" :key="img.path" class="relative">
                            <img :src="img.url" alt="" class="h-16 w-16 rounded-lg object-cover ring-1 ring-slate-200 dark:ring-white/10" />
                            <button type="button" class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs text-white" @click="removeNodeImage(node, i)">×</button>
                        </div>
                    </div>
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[#2E74B5] hover:underline dark:text-sky-300">
                        <input type="file" accept="image/*" multiple class="hidden" :disabled="uploadingImage === node.id" @change="uploadNodeImage(node, $event)" />
                        {{ uploadingImage === node.id ? 'Загрузка…' : '+ фото' }}
                    </label>
                </div>
            </div>

            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:border-white/15 dark:text-slate-200" @click="addNode">
                + Добавить узел
            </button>

            <!-- Проверка воронки: предупреждения о битых/недостижимых узлах -->
            <div v-if="issues.length" class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                <div class="mb-1 text-sm font-medium text-amber-800 dark:text-amber-300">⚠️ Проверьте воронку ({{ issues.length }})</div>
                <ul class="list-disc space-y-0.5 pl-5 text-xs text-amber-700 dark:text-amber-200/90">
                    <li v-for="(msg, i) in issues" :key="i">{{ msg }}</li>
                </ul>
            </div>

            <div class="flex gap-3">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50">Сохранить</button>
                <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-white/10" @click="cancel">Отмена</button>
            </div>
        </form>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import type { RequestPayload } from '@inertiajs/core';
import { computed, reactive, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Toggle from '@/Components/Toggle.vue';

interface OptionEdge {
    label: string;
    next: string;
}
interface FlowNodeDef {
    type?: string;
    text?: string;
    action?: string;
    options?: OptionEdge[];
    variable?: string;
    next?: string;
    operator?: string;
    value?: string;
    else?: string;
}
interface FlowRow {
    id: string;
    name: string;
    is_active: boolean;
    triggers: string[];
    definition: { start?: string; nodes?: Record<string, FlowNodeDef> };
}
type NodeKind = 'message' | 'input' | 'condition';
interface NodeEdit {
    id: string;
    type: NodeKind;
    text: string;
    action: string;
    options: OptionEdge[];
    variable: string;
    next: string;
    operator: string;
    value: string;
    else: string;
}
interface Option {
    value: string;
    label: string;
}

const props = defineProps<{ flows: FlowRow[]; actionOptions: Option[]; nodeTypeOptions: Option[]; operatorOptions: Option[] }>();

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

const blankNode = (id: string): NodeEdit => ({
    id, type: 'message', text: '', action: 'none', options: [],
    variable: '', next: '', operator: 'eq', value: '', else: '',
});

const newFlow = (): void => {
    editing.value = 'new';
    form.defaults({ id: null, name: '', is_active: true, triggers: '', start: 'n1', nodes: [] });
    form.reset();
    form.nodes = [blankNode('n1')];
    form.start = 'n1';
};

const editFlow = (f: FlowRow): void => {
    editing.value = f.id;
    const nodesObj = f.definition.nodes ?? {};
    const nodes: NodeEdit[] = Object.entries(nodesObj).map(([id, n]) => ({
        id,
        type: (n.type === 'input' || n.type === 'condition' ? n.type : 'message') as NodeKind,
        text: n.text ?? '',
        action: n.action ?? 'none',
        options: (n.options ?? []).map((o) => ({ label: o.label, next: o.next })),
        variable: n.variable ?? '',
        next: n.next ?? '',
        operator: n.operator ?? 'eq',
        value: n.value ?? '',
        else: n.else ?? '',
    }));
    form.id = f.id;
    form.name = f.name;
    form.is_active = f.is_active;
    form.triggers = f.triggers.join(', ');
    form.start = f.definition.start ?? nodes[0]?.id ?? 'n1';
    form.nodes = nodes.length ? nodes : [blankNode('n1')];
};

const cancel = (): void => {
    editing.value = null;
    form.clearErrors();
};

const addNode = (): void => {
    const nums = form.nodes.map((n) => parseInt(n.id.replace(/\D/g, ''), 10) || 0);
    form.nodes.push(blankNode('n' + (Math.max(0, ...nums) + 1)));
};
const removeNode = (id: string): void => {
    form.nodes = form.nodes.filter((n) => n.id !== id);
    form.nodes.forEach((n) => {
        n.options = n.options.filter((o) => o.next !== id);
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

const save = (): void => {
    const nodes: Record<string, FlowNodeDef> = {};
    for (const n of form.nodes) {
        if (n.type === 'input') {
            nodes[n.id] = { type: 'input', text: n.text, variable: n.variable.trim(), next: n.next };
        } else if (n.type === 'condition') {
            nodes[n.id] = { type: 'condition', variable: n.variable.trim(), operator: n.operator, value: n.value, next: n.next, else: n.else };
        } else {
            nodes[n.id] = {
                type: 'message',
                text: n.text,
                action: n.action,
                options: n.action === 'none' ? n.options.filter((o) => o.label.trim() !== '' && o.next) : [],
            };
        }
    }
    const payload = {
        name: form.name,
        is_active: form.is_active,
        triggers: form.triggers.split(',').map((t) => t.trim()).filter((t) => t !== ''),
        definition: { start: form.start, nodes },
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

const actionLabel = (value: string): string => props.actionOptions.find((a) => a.value === value)?.label ?? value;

// Литералы с {{ }} нельзя писать прямо в шаблоне (Vue примет за интерполяцию) — выносим в строки.
const msgPlaceholder = 'Сообщение бота. Можно подставлять {{переменную}}';
const inputHint = 'Ответ клиента сохранится и его можно вставить в любой текст как {{name}}.';
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
            <button type="button" class="mb-4 rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96]" @click="newFlow">
                + Новый сценарий
            </button>

            <div v-if="flows.length === 0" class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-400">
                Сценариев пока нет. Создайте первый — например «акция» → предложить запись.
            </div>

            <div class="space-y-3">
                <div v-for="f in flows" :key="f.id" class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ f.name }}</div>
                            <div class="mt-1 text-xs text-slate-400">Запуск по фразам: {{ f.triggers.join(', ') || '—' }}</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Toggle :model-value="f.is_active" @update:model-value="toggle(f)" />
                            <button type="button" class="text-sm text-[#2E74B5] hover:underline dark:text-sky-300" @click="editFlow(f)">Редактировать</button>
                            <button type="button" class="text-sm text-red-600 hover:underline" @click="destroy(f)">Удалить</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Редактор -->
        <form v-else class="max-w-3xl space-y-5" @submit.prevent="save">
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Название</label>
                        <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Напр. Акция месяца" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Запуск по фразам (через запятую)</label>
                        <input v-model="form.triggers" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="акция, скидка, промо" />
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <Toggle v-model="form.is_active" /> Сценарий включён
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Стартовый узел</label>
                    <select v-model="form.start" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option v-for="id in nodeIds" :key="id" :value="id">{{ id }}</option>
                    </select>
                </div>
            </div>

            <!-- Узлы -->
            <div v-for="node in form.nodes" :key="node.id" class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="mb-2 flex items-center justify-between">
                    <div class="text-sm font-semibold text-[#1F4E79] dark:text-sky-200">Узел {{ node.id }}<span v-if="form.start === node.id" class="ml-2 rounded bg-[#EAF2FB] px-1.5 py-0.5 text-xs text-[#2E74B5]">старт</span></div>
                    <button v-if="form.nodes.length > 1" type="button" class="text-xs text-red-600 hover:underline" @click="removeNode(node.id)">Удалить узел</button>
                </div>
                <div class="mb-3">
                    <label class="mb-1 block text-xs font-medium text-slate-500">Тип узла</label>
                    <select v-model="node.type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:w-auto">
                        <option v-for="o in nodeTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>

                <!-- СООБЩЕНИЕ: текст + действие + кнопки -->
                <template v-if="node.type === 'message'">
                    <textarea v-model="node.text" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :placeholder="msgPlaceholder"></textarea>
                    <div class="mt-3">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Действие</label>
                        <select v-model="node.action" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:w-auto">
                            <option v-for="a in actionOptions" :key="a.value" :value="a.value">{{ a.label }}</option>
                        </select>
                    </div>
                    <div v-if="node.action === 'none'" class="mt-3">
                        <div class="mb-1 text-xs font-medium text-slate-500">Кнопки (вариант → переход к узлу)</div>
                        <div class="space-y-2">
                            <div v-for="(opt, i) in node.options" :key="i" class="flex items-center gap-2">
                                <input v-model="opt.label" type="text" class="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm" placeholder="Текст кнопки" />
                                <span class="text-slate-400">→</span>
                                <select v-model="opt.next" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <option v-for="id in nodeIds" :key="id" :value="id">{{ id }}</option>
                                </select>
                                <button type="button" class="text-xs text-red-600 hover:underline" @click="removeOption(node, i)">убрать</button>
                            </div>
                        </div>
                        <button type="button" class="mt-2 text-sm text-[#2E74B5] hover:underline dark:text-sky-300" @click="addOption(node)">+ кнопка</button>
                    </div>
                    <p v-else class="mt-2 text-xs text-slate-400">{{ actionLabel(node.action) }} — это конечный шаг сценария.</p>
                </template>

                <!-- ВОПРОС: приглашение + сохранить ответ в переменную + переход -->
                <template v-else-if="node.type === 'input'">
                    <textarea v-model="node.text" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Вопрос клиенту (например: Как вас зовут?)"></textarea>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Сохранить ответ в переменную</label>
                            <input v-model="node.variable" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm" placeholder="name" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Дальше → узел</label>
                            <select v-model="node.next" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="">—</option>
                                <option v-for="id in nodeIds" :key="id" :value="id">{{ id }}</option>
                            </select>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">{{ inputHint }}</p>
                </template>

                <!-- УСЛОВИЕ: если переменная … → да/нет -->
                <template v-else>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <input v-model="node.variable" type="text" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm" placeholder="переменная (name)" />
                        <select v-model="node.operator" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            <option v-for="o in operatorOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <input v-model="node.value" type="text" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm" placeholder="значение" :disabled="node.operator === 'filled'" />
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-emerald-600">Если ДА → узел</label>
                            <select v-model="node.next" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="">—</option>
                                <option v-for="id in nodeIds" :key="id" :value="id">{{ id }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-red-600">Если НЕТ → узел</label>
                            <select v-model="node.else" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="">—</option>
                                <option v-for="id in nodeIds" :key="id" :value="id">{{ id }}</option>
                            </select>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">Условие проходит без участия клиента — сразу уводит в нужную ветку.</p>
                </template>
            </div>

            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:border-white/15 dark:text-slate-200" @click="addNode">
                + Добавить узел
            </button>

            <div class="flex gap-3">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50">Сохранить</button>
                <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-white/10" @click="cancel">Отмена</button>
            </div>
        </form>
    </AppLayout>
</template>

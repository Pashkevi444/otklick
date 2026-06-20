<script setup lang="ts">
import { computed } from 'vue';
import { VueFlow, type Edge, type Node, type NodeDragEvent, type NodeMouseEvent } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';

interface Variant {
    label: string;
    next: string;
}
interface OptionEdge {
    label: string;
    next: string;
}
interface CanvasNode {
    id: string;
    type: 'message' | 'input' | 'condition' | 'split';
    text: string;
    action: string;
    options: OptionEdge[];
    variable: string;
    next: string;
    operator: string;
    value: string;
    else: string;
    variants: Variant[];
    x: number;
    y: number;
}

const props = defineProps<{ nodes: CanvasNode[]; start: string }>();
const emit = defineEmits<{ select: [id: string]; moved: [id: string, x: number, y: number] }>();

const TYPE_LABEL: Record<string, string> = { message: 'Сообщение', input: 'Вопрос', condition: 'Условие', split: 'A/B' };

const snippet = (n: CanvasNode): string => {
    if (n.type === 'condition') return `если ${n.variable || '?'} ${n.operator} ${n.value}`;
    if (n.type === 'split') return `${n.variants.length} вариант(а)`;
    if (n.type === 'input') return n.text || `→ ${n.variable || '?'}`;
    if (n.action && n.action !== 'none') return n.action;
    return n.text || '—';
};

// Узлы-карточки: позиция из модели (или авто-сетка), подпись и цвет по типу.
const vfNodes = computed<Node[]>(() =>
    props.nodes.map((n, i) => ({
        id: n.id,
        position: { x: n.x || (i % 4) * 230, y: n.y || Math.floor(i / 4) * 150 },
        label: `${n.id} · ${TYPE_LABEL[n.type] ?? n.type}${n.id === props.start ? ' ⭐' : ''}\n${snippet(n).slice(0, 40)}`,
        class: `fc-node fc-${n.type}${n.id === props.start ? ' fc-start' : ''}`,
        draggable: true,
    })),
);

// Связи: кнопки/переходы/ветки условия/варианты A-B.
const vfEdges = computed<Edge[]>(() => {
    const edges: Edge[] = [];
    const add = (source: string, target: string, key: string, label?: string): void => {
        if (target) edges.push({ id: `${source}-${key}`, source, target, label, animated: false, markerEnd: 'arrowclosed' });
    };
    for (const n of props.nodes) {
        if (n.type === 'message' && n.action === 'none') n.options.forEach((o, i) => add(n.id, o.next, `o${i}`, o.label));
        else if (n.type === 'input') add(n.id, n.next, 'n');
        else if (n.type === 'condition') {
            add(n.id, n.next, 'y', 'да');
            add(n.id, n.else, 'e', 'нет');
        } else if (n.type === 'split') n.variants.forEach((v, i) => add(n.id, v.next, `v${i}`, v.label));
    }
    return edges;
});

const onDragStop = (e: NodeDragEvent): void => emit('moved', e.node.id, Math.round(e.node.position.x), Math.round(e.node.position.y));
const onNodeClick = (e: NodeMouseEvent): void => emit('select', e.node.id);
</script>

<template>
    <div class="h-[380px] overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5">
        <VueFlow :nodes="vfNodes" :edges="vfEdges" fit-view-on-init :min-zoom="0.3" :max-zoom="1.6" @node-drag-stop="onDragStop" @node-click="onNodeClick">
            <Background :gap="18" pattern-color="#cbd5e1" />
        </VueFlow>
    </div>
</template>

<style>
.fc-node {
    white-space: pre-line;
    border-radius: 10px;
    border-width: 1px;
    padding: 8px 10px;
    font-size: 11px;
    line-height: 1.25;
    text-align: left;
    width: 180px;
}
.fc-message { background: #eff6ff; border-color: #93c5fd; color: #1e3a8a; }
.fc-input { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
.fc-condition { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
.fc-split { background: #f5f3ff; border-color: #c4b5fd; color: #5b21b6; }
.fc-start { box-shadow: 0 0 0 2px #2e74b5; }
</style>

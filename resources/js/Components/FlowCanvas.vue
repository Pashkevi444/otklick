<script setup lang="ts">
import { computed } from 'vue';
import { VueFlow, Handle, Position, type Connection, type Edge, type EdgeChange, type Node, type NodeDragEvent, type NodeMouseEvent } from '@vue-flow/core';
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
const emit = defineEmits<{
    select: [id: string];
    moved: [id: string, x: number, y: number];
    connect: [source: string, handle: string, target: string];
    disconnect: [source: string, handle: string];
}>();

const TYPE_LABEL: Record<string, string> = { message: 'Сообщение', input: 'Вопрос', condition: 'Условие', split: 'A/B' };

const snippet = (n: CanvasNode): string => {
    if (n.type === 'condition') return `если ${n.variable || '?'} ${n.operator} ${n.value}`;
    if (n.type === 'split') return `${n.variants.length} вариант(а)`;
    if (n.type === 'input') return n.text || `→ ${n.variable || '?'}`;
    if (n.action && n.action !== 'none') return n.action;
    return n.text || '—';
};

// Исходящие «ручки» узла: id кодирует поле перехода (next / да-нет / кнопка / вариант).
const handlesOf = (n: CanvasNode): { id: string; label: string; color: string }[] => {
    if (n.type === 'input') return [{ id: 'next', label: '', color: '#64748b' }];
    if (n.type === 'condition')
        return [
            { id: 'yes', label: 'да', color: '#059669' },
            { id: 'no', label: 'нет', color: '#dc2626' },
        ];
    if (n.type === 'split') return n.variants.map((v, i) => ({ id: 'v' + i, label: v.label, color: '#7c3aed' }));
    if (n.type === 'message' && n.action === 'none') return n.options.map((o, i) => ({ id: 'o' + i, label: o.label || '·', color: '#2563eb' }));
    return [];
};

const vfNodes = computed<Node[]>(() =>
    props.nodes.map((n, i) => ({
        id: n.id,
        type: 'fnode',
        position: { x: n.x || (i % 4) * 230, y: n.y || Math.floor(i / 4) * 150 },
        data: { node: n, handles: handlesOf(n), start: n.id === props.start },
        draggable: true,
    })),
);

const targetOf = (n: CanvasNode, handleId: string): string => {
    if (handleId === 'next') return n.next;
    if (handleId === 'yes') return n.next;
    if (handleId === 'no') return n.else;
    if (handleId.startsWith('v')) return n.variants[Number(handleId.slice(1))]?.next ?? '';
    if (handleId.startsWith('o')) return n.options[Number(handleId.slice(1))]?.next ?? '';
    return '';
};

const vfEdges = computed<Edge[]>(() => {
    const edges: Edge[] = [];
    for (const n of props.nodes) {
        for (const h of handlesOf(n)) {
            const target = targetOf(n, h.id);
            if (target) edges.push({ id: `${n.id}::${h.id}`, source: n.id, sourceHandle: h.id, target, label: h.label, markerEnd: 'arrowclosed' });
        }
    }
    return edges;
});

const handleLeft = (i: number, total: number): string => (total > 1 ? `${((i + 1) / (total + 1)) * 100}%` : '50%');

const onDragStop = (e: NodeDragEvent): void => emit('moved', e.node.id, Math.round(e.node.position.x), Math.round(e.node.position.y));
const onNodeClick = (e: NodeMouseEvent): void => emit('select', e.node.id);
const onConnect = (c: Connection): void => {
    if (c.source && c.sourceHandle && c.target) emit('connect', c.source, c.sourceHandle, c.target);
};
const onEdgesChange = (changes: EdgeChange[]): void => {
    for (const ch of changes) {
        if (ch.type === 'remove') {
            const [source, handle] = ch.id.split('::');
            if (source && handle) emit('disconnect', source, handle);
        }
    }
};
</script>

<template>
    <div class="h-[420px] overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5">
        <VueFlow
            :nodes="vfNodes"
            :edges="vfEdges"
            fit-view-on-init
            :min-zoom="0.3"
            :max-zoom="1.6"
            @node-drag-stop="onDragStop"
            @node-click="onNodeClick"
            @connect="onConnect"
            @edges-change="onEdgesChange"
        >
            <Background :gap="18" pattern-color="#cbd5e1" />

            <template #node-fnode="{ data }">
                <div class="fc-node" :class="`fc-${data.node.type}${data.start ? ' fc-start' : ''}`">
                    <Handle type="target" :position="Position.Top" />
                    <div class="fc-title">{{ data.node.id }} · {{ TYPE_LABEL[data.node.type] }}<span v-if="data.start"> ⭐</span></div>
                    <div class="fc-snip">{{ snippet(data.node).slice(0, 42) }}</div>
                    <Handle
                        v-for="(h, i) in data.handles"
                        :key="h.id"
                        type="source"
                        :id="h.id"
                        :position="Position.Bottom"
                        :style="{ left: handleLeft(Number(i), Number(data.handles.length)), background: h.color }"
                    />
                    <div v-if="data.handles.length" class="fc-handles">
                        <span v-for="h in data.handles" :key="h.id" :style="{ color: h.color }">{{ h.label }}</span>
                    </div>
                </div>
            </template>
        </VueFlow>
    </div>
</template>

<style>
.fc-node {
    position: relative;
    border-radius: 10px;
    border-width: 1px;
    padding: 8px 10px 14px;
    width: 180px;
    text-align: left;
}
.fc-title { font-size: 11px; font-weight: 600; }
.fc-snip { margin-top: 2px; font-size: 11px; line-height: 1.25; opacity: 0.85; white-space: pre-line; }
.fc-handles { margin-top: 4px; display: flex; justify-content: space-around; font-size: 9px; font-weight: 700; }
.fc-message { background: #eff6ff; border-color: #93c5fd; color: #1e3a8a; }
.fc-input { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
.fc-condition { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
.fc-split { background: #f5f3ff; border-color: #c4b5fd; color: #5b21b6; }
.fc-start { box-shadow: 0 0 0 2px #2e74b5; }
.vue-flow__handle { width: 9px; height: 9px; }
</style>

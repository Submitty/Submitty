<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

type PanelPosition = 'leftTop' | 'leftBottom' | 'rightTop' | 'rightBottom';

interface PositionOption {
    value: PanelPosition;
    label: string;
    side: 'left' | 'right';
}

type Listener = (state: { numOfPanels: number; dividedColName: 'LEFT' | 'RIGHT' }) => void;

interface StoreData {
    state: { numOfPanels: number; dividedColName: 'LEFT' | 'RIGHT' };
    listeners: Listener[];
}

const GLOBAL_KEY = '__submittyPanelLayoutStore__';

function getStore(): StoreData {
    if (typeof window !== 'undefined' && !(window as any)[GLOBAL_KEY]) {
        (window as any)[GLOBAL_KEY] = {
            state: { numOfPanels: 1, dividedColName: 'LEFT' },
            listeners: [],
        };
    }
    return (window as any)[GLOBAL_KEY];
}

function onLayoutChange(fn: Listener): () => void {
    const store = getStore();
    store.listeners.push(fn);
    return () => {
        const idx = store.listeners.indexOf(fn);
        if (idx >= 0) {
            store.listeners.splice(idx, 1);
        }
    };
}

const props = withDefaults(defineProps<{
    panelId: string;
    currentPosition?: string | null;
    numOfPanels?: number;
    dividedColName?: 'LEFT' | 'RIGHT';
}>(), {
    currentPosition: null,
    numOfPanels: 1,
    dividedColName: 'LEFT',
});

const emit = defineEmits<{
    'position-change': [payload: { panelId: string; position: PanelPosition }];
}>();

const numOfPanels = ref(props.numOfPanels);
const dividedCol = ref(props.dividedColName);

let unsubscribe: (() => void) | null = null;

onMounted(() => {
    unsubscribe = onLayoutChange((layout) => {
        numOfPanels.value = layout.numOfPanels;
        dividedCol.value = layout.dividedColName;
    });
});

onUnmounted(() => {
    unsubscribe?.();
});

const options = computed<PositionOption[]>(() => {
    const panelCount = numOfPanels.value;
    if (panelCount <= 1) {
        return [];
    }
    const dividedRight = dividedCol.value === 'RIGHT';
    const is2Panel = panelCount === 2;
    const is3PanelRight = panelCount === 3 && dividedRight;

    const showLeftBottom = !is2Panel && !is3PanelRight;
    const showRightBottom = !is2Panel && (panelCount === 4 || dividedRight);

    const leftTopText = (is2Panel || is3PanelRight) ? 'Open as left panel' : 'Open as top left panel';
    const rightTopText = !showRightBottom ? 'Open as right panel' : 'Open as top right panel';

    const result: PositionOption[] = [
        { value: 'leftTop', label: leftTopText, side: 'left' },
    ];
    if (showLeftBottom) {
        result.push({ value: 'leftBottom', label: 'Open as bottom left panel', side: 'left' });
    }
    result.push({ value: 'rightTop', label: rightTopText, side: 'right' });
    if (showRightBottom) {
        result.push({ value: 'rightBottom', label: 'Open as bottom right panel', side: 'right' });
    }
    return result;
});

function onChange(event: Event) {
    const position = (event.target as HTMLSelectElement).value as PanelPosition;
    emit('position-change', { panelId: props.panelId, position });
}
</script>

<template>
  <select
    :id="`${panelId}_select`"
    class="panel-position-cont"
    :size="options.length"
    data-testid="panel-position-select"
    @change="onChange"
  >
    <option
      v-for="opt in options"
      :key="opt.value"
      :class="`panel-position-${opt.side}`"
      :value="opt.value"
      :data-testid="`panel-position-${opt.value}`"
    >
      {{ opt.label }}
    </option>
  </select>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { getLayoutState, onLayoutChange } from '../../../ts/panel-layout-store';

type PanelPosition = 'leftTop' | 'leftBottom' | 'rightTop' | 'rightBottom';

interface PositionOption {
    value: PanelPosition;
    label: string;
    side: 'left' | 'right';
}

const props = defineProps<{
    panelId: string;
}>();

const emit = defineEmits<{
    'position-change': [payload: { panelId: string; position: PanelPosition }];
}>();

const layout = ref(getLayoutState());
const numOfPanels = computed(() => layout.value.numOfPanels);
const dividedCol = computed(() => layout.value.dividedColName);

let unsubscribe: (() => void) | null = null;

onMounted(() => {
    unsubscribe = onLayoutChange((newState) => {
        layout.value = newState;
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

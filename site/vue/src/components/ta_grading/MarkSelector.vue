<script setup lang="ts">
const props = defineProps<{
    markId: number;
    componentId: number;
    order: number;
    isChecked: boolean;
}>();

// PR #1: routed through events bridge to window.onToggleMarkById.
// PR #3: RubricComponent catches @toggle-mark directly, no bridge needed.
const emit = defineEmits<{
    'toggle-mark': [data: { componentId: number; markId: number }];
}>();

function handleClick(event: MouseEvent) {
    event.stopPropagation();
    emit('toggle-mark', { componentId: props.componentId, markId: props.markId });
}
</script>

<template>
  <span
    class="mark-selector-container"
    :data-mark_id="markId"
    @click="handleClick"
  >
    <span
      class="mark-selector col-no-gutters"
      :class="[{ 'mark-selected': isChecked }]"
      :data-mark_id="markId"
    >
      {{ order }}
    </span>
  </span>
</template>

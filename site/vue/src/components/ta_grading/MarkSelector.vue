<script setup lang="ts">
const props = defineProps<{
    markId: number;
    componentId: number;
    order: number;
    isChecked: boolean;
    editMarksEnabled: boolean;
    markDisabled: boolean;
}>();

// PR #1: routed through events bridge to window.onToggleMarkById.
// PR #3: RubricComponent catches @toggle-mark directly, no bridge needed.
const emit = defineEmits<{
    'toggle-mark': [data: { componentId: number; markId: number }];
}>();

function handleClick(event: MouseEvent): void {
    event.stopPropagation();
    // Match the legacy div's onclick guard: `edit_marks_enabled or mark_disabled ? '' : 'onToggleMark(this)'`
    if (props.editMarksEnabled || props.markDisabled) {
        return;
    }
    emit('toggle-mark', { componentId: props.componentId, markId: props.markId });
}
</script>

<template>
  <span
    class="mark-selector-container"
    :data-mark_id="markId"
    data-testid="mark-selector"
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

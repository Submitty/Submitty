<script setup lang="ts">
const props = defineProps<{
    collapsed: boolean;
}>();

const emit = defineEmits<{
    'toggle-all': [];
}>();

function toggleAll() {
    emit('toggle-all');
}
</script>

<template>
  <button
    id="toggle-all-sections-btn"
    type="button"
    class="btn btn-primary"
    data-testid="toggle-all-sections"
    :title="props.collapsed ? 'Expand all sections' : 'Collapse all sections'"
    @click="toggleAll"
  >
    {{ props.collapsed ? 'Expand All Sections' : 'Collapse All Sections' }}
  </button>
</template>

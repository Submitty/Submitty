<script setup lang="ts">
import { onMounted } from 'vue';

declare global {
    interface Window {
        toggleAllSections?: () => void;
        updateToggleButtonText?: () => void;
    }
}

const onClick = () => {
    window.toggleAllSections?.();
};

onMounted(() => {
    window.updateToggleButtonText?.();
});
</script>

<template>
  <button
    type="button"
    class="btn btn-primary"
    id="toggle-all-sections-btn"
    data-testid="toggle-all-sections"
    @click="onClick"
  >
    Collapse All Sections
  </button>
</template>

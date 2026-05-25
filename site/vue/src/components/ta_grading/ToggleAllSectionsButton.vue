<script setup lang="ts">
import { onMounted } from 'vue';

declare global {
  interface Window {
    toggleAllSections?: () => void;
    updateToggleButtonText?: () => void;
  }
}

const onClick = () => {
  if (typeof window.toggleAllSections === 'function') {
    window.toggleAllSections();
  }
};

onMounted(() => {
  if (typeof window.updateToggleButtonText === 'function') {
    window.updateToggleButtonText();
  }
});
</script>

<template>
  <button
    type="button"
    id="toggle-all-sections-btn"
    class="btn btn-primary"
    data-testid="toggle-all-sections"
    @click="onClick"
  >
    Collapse All Sections
  </button>
</template>

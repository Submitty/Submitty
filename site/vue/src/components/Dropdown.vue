<script setup lang="ts">
import { ref } from 'vue';

withDefaults(defineProps<{
    label?: string;
    align?: 'left' | 'right';
    id?: string;
}>(), {
    label: 'Dropdown',
    align: 'right',
    id: 'dropdown',
});

const isOpen = ref(false);
const triggerRef = ref<HTMLElement | null>(null);

function handleTriggerClick() {
    isOpen.value = !isOpen.value;
}

function onEscape() {
    if (!isOpen.value) {
        return;
    }
    isOpen.value = false;
    triggerRef.value?.focus();
}
</script>

<template>
  <div>
    <!-- full-viewport overlay, only when open (like Popup.vue's .popup-box) -->
    <div
      v-if="isOpen"
      class="dropdown-backdrop"
      data-testid="dropdown-backdrop"
      @click="isOpen = false"
    />

    <div
      :id="id"
      class="dropdown"
      data-testid="dropdown"
      @keydown.escape="onEscape"
    >
      <div class="btn-group">
        <slot
          name="trigger"
          :open="isOpen"
          :toggle="handleTriggerClick"
        >
          <button
            ref="triggerRef"
            type="button"
            class="btn btn-default dropdown-toggle"
            data-testid="dropdown-trigger"
            :aria-haspopup="true"
            :aria-expanded="isOpen"
            @click="handleTriggerClick"
          >
            {{ label }}
          </button>
        </slot>

        <div
          class="dropdown-menu"
          :class="[align === 'right' ? 'dropdown-menu-right' : 'dropdown-menu-left', { show: isOpen }]"
          data-testid="dropdown-menu"
        >
          <slot :close="() => { isOpen = false }" />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dropdown-backdrop {
  position: fixed;
  inset: 0;
  z-index: 0;
  background: transparent;
}

.dropdown {
  position: relative;
  z-index: 1000;
}
</style>

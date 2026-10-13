<script setup lang="ts">
import { ref } from 'vue';

const props = withDefaults(defineProps<{
    label?: string;
    align?: 'left' | 'right';
    id?: string;
    items: Array<{
        id: string;
        displayText: string;
        title?: string;
        link?: string;
        badgeText?: string;
        hidden?: boolean;
    }>;
}>(), {
    label: 'Dropdown',
    align: 'right',
    id: 'dropdown',
});

const emit = defineEmits<{
    select: [itemId: string];
    navigate: [url: string];
}>();

const isOpen = ref(false);
const triggerRef = ref<HTMLElement | null>(null);
const showAttachments = ref(false);

function displayTextForItem(item: { id: string; displayText: string }): string {
    if (item.id === 'toggle-attachments') {
        return showAttachments.value ? 'Hide Attachments' : item.displayText;
    }
    return item.displayText;
}

function handleItemClick(item: { id: string; link?: string }) {
    if (item.id === 'toggle-attachments') {
        showAttachments.value = !showAttachments.value;
    }
    if (item.link && item.link !== '#') {
        emit('navigate', item.link);
    }
    else {
        emit('select', item.id);
    }
    isOpen.value = false;
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
      :id="props.id"
      class="dropdown"
      data-testid="dropdown"
      @keydown.escape="onEscape"
    >
      <div class="btn-group">
        <button
          ref="triggerRef"
          type="button"
          class="btn btn-default dropdown-toggle"
          data-testid="dropdown-trigger"
          :aria-haspopup="true"
          :aria-expanded="isOpen"
          @click="isOpen = !isOpen"
        >
          {{ props.label }}
        </button>

        <div
          class="dropdown-menu"
          :class="[props.align === 'right' ? 'dropdown-menu-right' : 'dropdown-menu-left', { show: isOpen }]"
          data-testid="dropdown-menu"
        >
          <template
            v-for="item in props.items"
            :key="item.id"
          >
            <a
              v-if="!item.hidden"
              :id="item.id"
              :data-testid="item.id"
              class="dropdown-item"
              :title="item.title"
              href="#"
              @click.prevent="handleItemClick(item)"
            >
              <span>{{ displayTextForItem(item) }}</span>
              <span
                v-if="item.badgeText"
                class="attachment-badge badge"
                data-testid="attachment-badge"
              >{{ item.badgeText }}</span>
            </a>
          </template>
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

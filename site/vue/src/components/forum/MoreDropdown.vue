<script setup lang="ts">
import { ref } from 'vue';
import Dropdown from '../Dropdown.vue';

interface MoreDropdownItem {
    id: string;
    displayText: string;
    title: string;
    link?: string;
    optionalClass?: string;
    dividerBefore?: boolean;
    dividerAfter?: boolean;
    badgeText?: string;
}

defineProps<{
    items: MoreDropdownItem[];
    currentDisplayOption: string;
    threadExists: boolean;
    isFullThreadsPage: boolean;
}>();

const emit = defineEmits<{
    'display-option-change': [option: string];
    'toggle-merged': [];
    'toggle-deleted': [];
    'navigate-stats': [];
    'navigate': [url: string];
    'item-click': [itemId: string];
    'toggle-attachments': [];
}>();

interface DisplayOption {
    id: string;
    label: string;
    icon?: string;
}

const displayOptions: DisplayOption[] = [
    { id: 'tree', label: 'Hierarchical Post Order' },
    { id: 'time', label: 'Chronological Post Order', icon: 'fas fa-angle-up' },
    { id: 'reverse-time', label: 'Chronological Post Order', icon: 'fas fa-angle-down' },
    { id: 'alpha', label: 'Alphabetical Post Order' },
    { id: 'alpha_by_registration', label: 'Alpha by Registration Post Order' },
    { id: 'alpha_by_rotating', label: 'Alpha by Rotating Post Order' },
];

const showAttachments = ref(false);

function displayTextForItem(item: MoreDropdownItem): string {
    if (item.id === 'toggle-attachments') {
        return showAttachments.value ? 'Hide Attachments' : 'Show Attachments';
    }
    return item.displayText;
}

function handleItemClick(item: MoreDropdownItem, close: () => void) {
    switch (item.id) {
        case 'toggle-attachments':
            showAttachments.value = !showAttachments.value;
            emit('toggle-attachments');
            break;
        case 'merge_thread':
            emit('toggle-merged');
            break;
        case 'delete':
            emit('toggle-deleted');
            break;
        case 'forum_stats':
            emit('navigate-stats');
            break;
        default:
            if (item.link && item.link !== '#') {
                emit('navigate', item.link);
            }
            else {
                emit('item-click', item.id);
            }
            break;
    }
    close();
}

function handleOptionClick(option: string, close: () => void) {
    emit('display-option-change', option);
    close();
}
</script>

<template>
  <Dropdown
    label="More"
    align="right"
    data-testid="more-dropdown"
  >
    <template #trigger="{ open: isOpen, toggle }">
      <button
        type="button"
        class="btn btn-default dropdown-toggle"
        data-testid="more-dropdown-trigger"
        aria-haspopup="true"
        :aria-expanded="isOpen"
        @click="toggle"
      >
        More
      </button>
    </template>

    <template #default="{ close }">
      <template
        v-for="item in items"
        :key="item.id"
      >
        <div
          v-if="item.dividerBefore"
          class="dropdown-divider"
          data-testid="dropdown-divider"
        />
        <a
          :id="item.id"
          :data-testid="item.id"
          class="dropdown-item"
          :class="item.optionalClass ?? ''"
          :title="item.title"
          href="#"
          @click.prevent.stop="handleItemClick(item, close)"
        >
          <span
            v-if="item.badgeText"
            class="status"
            data-testid="attachment-status"
          />
          <span>{{ displayTextForItem(item) }}</span>
          <span
            v-if="item.badgeText"
            class="attachment-badge badge"
            data-testid="attachment-badge"
          >{{ item.badgeText }}</span>
        </a>
        <div
          v-if="item.dividerAfter"
          class="dropdown-divider"
          data-testid="dropdown-divider"
        />
      </template>
      <template v-if="items.length > 0 && threadExists && !isFullThreadsPage">
        <div
          class="dropdown-divider"
          data-testid="dropdown-divider"
        />
      </template>
      <template v-if="threadExists && !isFullThreadsPage">
        <a
          v-for="opt in displayOptions"
          :id="opt.id"
          :key="opt.id"
          class="key_to_click dropdown-item"
          :class="{ active: currentDisplayOption === opt.id }"
          href="#"
          :title="'Sort posts by ' + opt.label"
          :data-testid="`display-option-${opt.id}`"
          @click.prevent.stop="handleOptionClick(opt.id, close)"
        >
          {{ opt.label }}
          <i
            v-if="opt.icon"
            :class="opt.icon.split(' ')"
            aria-hidden="true"
          />
        </a>
      </template>
    </template>
  </Dropdown>
</template>

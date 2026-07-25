<script setup lang="ts">
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

const props = defineProps<{
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

function handleItemClick(item: MoreDropdownItem) {
    switch (item.id) {
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
}

function handleOptionClick(option: string) {
    emit('display-option-change', option);
}
</script>

<template>
  <div
    data-testid="more-dropdown"
    class="dropdown more-dropdown"
  >
    <div class="btn-group">
      <button
        type="button"
        class="btn btn-default dropdown-toggle"
        data-toggle="dropdown"
        aria-haspopup="true"
        aria-expanded="false"
      >
        More
      </button>
      <div class="dropdown-menu dropdown-menu-right">
        <template
          v-for="item in items"
          :key="item.id"
        >
          <div
            v-if="item.dividerBefore"
            class="dropdown-divider"
          />
          <a
            :id="item.id"
            :data-testid="item.id"
            class="dropdown-item"
            :class="item.optionalClass ?? ''"
            :title="item.title"
            href="#"
            @click.prevent="handleItemClick(item)"
          >
            <span
              v-if="item.badgeText"
              class="status"
            />
            <span v-if="item.displayText">{{ item.displayText }}</span>
            <span
              v-if="item.badgeText"
              class="attachment-badge badge"
            >{{ item.badgeText }}</span>
          </a>
          <div
            v-if="item.dividerAfter"
            class="dropdown-divider"
          />
        </template>
        <template v-if="items.length > 0 && threadExists && !isFullThreadsPage">
          <div class="dropdown-divider" />
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
            @click.prevent="handleOptionClick(opt.id)"
          >
            {{ opt.label }}
            <i
              v-if="opt.icon"
              :class="opt.icon.split(' ')"
              aria-hidden="true"
            />
          </a>
        </template>
      </div>
    </div>
  </div>
</template>

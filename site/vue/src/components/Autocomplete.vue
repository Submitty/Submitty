<script setup lang="ts">
import {
    ref, computed, watch, nextTick, onMounted, onBeforeUnmount,
} from 'vue';

interface AutocompleteItem {
    label: string;
    value: string;
}

interface Props {
    //The current value - what's being typexd in the textarea 
    modelValue: string;
    // Array of items to show in dropdown 
    items: AutocompleteItem[];
    // Minimum characters needed before showing dropdown
    minLength?: number;
    // Whether dropdown is open
    isOpen?: boolean;
    // Position styling for dropdown
    position?: { my: string; at: string };
}

const props = withDefaults(defineProps<Props>(), {
    minLength: 0,
    isOpen: false,
    position: () => ({ my: 'left top', at: 'left bottom+5' }),
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'select': [item: AutocompleteItem];
    'update:isOpen': [open: boolean];
}>();

const highlightedIndex = ref(-1);
const containerRef = ref<HTMLDivElement | null>(null);

// Determine if dropdown should be shown
const showDropdown = computed(() => {
    return props.isOpen && props.items.length > 0;
});

// Handle keyboard navigation
function handleKeyDown(event: KeyboardEvent) {
    if (!showDropdown.value) {
        return;
    }

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            highlightedIndex.value = Math.min(
                highlightedIndex.value + 1,
                props.items.length - 1,
            );
            break;
        case 'ArrowUp':
            event.preventDefault();
            highlightedIndex.value = Math.max(highlightedIndex.value - 1, -1);
            break;
        case 'Enter':
            event.preventDefault();
            if (highlightedIndex.value >= 0 && highlightedIndex.value < props.items.length) {
                const item = props.items[highlightedIndex.value];
                if (item) {
                    selectItem(item);
                }
            }
            break;
        case 'Escape':
            event.preventDefault();
            emit('update:isOpen', false);
            break;
        default:
            break;
    }
}

// Select an item from dropdown
function selectItem(item: AutocompleteItem) {
    emit('select', item);
    emit('update:modelValue', item.value);
    emit('update:isOpen', false);
    highlightedIndex.value = -1;
}

// Reset highlighted index when items change
watch(
    () => props.items,
    () => {
        highlightedIndex.value = -1;
    },
);

// Reset highlighted index when dropdown closes
watch(
    () => props.isOpen,
    (isOpen) => {
        if (!isOpen) {
            highlightedIndex.value = -1;
        }
    },
);

// Close dropdown when clicking outside
function handleClickOutside(event: MouseEvent) {
    if (
        containerRef.value
        && !containerRef.value.contains(event.target as Node)
    ) {
        emit('update:isOpen', false);
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
  <div
    ref="containerRef"
    class="autocomplete-container"
  >
    <!-- Dropdown is positioned absolutely, rendered after trigger element -->
    <div
      v-if="showDropdown"
      class="autocomplete-dropdown"
      role="listbox"
    >
      <div
        v-for="(item, index) in items"
        :key="item.value"
        class="autocomplete-item"
        :class="{ 'highlighted': index === highlightedIndex }"
        role="option"
        :aria-selected="index === highlightedIndex"
        @click="selectItem(item)"
        @mouseenter="highlightedIndex = index"
      >
        {{ item.label }}
      </div>
    </div>

    <!-- Slot for the trigger element (usually a textarea or input) -->
    <slot
      :handle-key-down="handleKeyDown"
    />
  </div>
</template>

<style scoped>
.autocomplete-container {
    position: relative;
}

.autocomplete-dropdown {
    position: absolute;
    max-height: 250px;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 0;
    margin: 0;
    background-color: var(--default-white);
    border: 1px solid var(--standard-light-gray);
    border-radius: 4px;
    box-shadow: 0 4px 12px var(--box-shadow-slightly-transparent);
    z-index: 1000;
    list-style: none;
    min-width: 200px;
    top: 100%;
    left: 0;
    margin-top: 5px;
}

.autocomplete-item {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 14px;
    color: var(--btn-default-text);
    transition: background-color 0.1s ease;
    user-select: none;
}

.autocomplete-item:hover,
.autocomplete-item.highlighted {
    background-color: var(--actionable-blue);
    color: var(--text-white);
}

/* Dark theme support */
[data-theme="dark"] .autocomplete-dropdown {
    background-color: var(--default-white);
    border: 1px solid var(--standard-light-gray);
}

[data-theme="dark"] .autocomplete-item {
    color: var(--default-black);
}

[data-theme="dark"] .autocomplete-item:hover,
[data-theme="dark"] .autocomplete-item.highlighted {
    background-color: var(--standard-light-gray);
    color: var(--default-black);
}
</style>

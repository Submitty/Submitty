<script setup lang="ts">
import { ref, onMounted } from 'vue';

interface ForumCategory {
    id: number;
    description: string;
    visibleDate: string | null;
    diff?: number;
}

interface Props {
    categories: ForumCategory[];
}

const props = defineProps<Props>();

const isVisibleCategory = (category: ForumCategory): boolean => {
    return category.visibleDate === null || (Number(category.diff ?? 0) >= 0);
};

//Reactive filter states
const selectedCategoryIds = ref<number[]>([]);
const selectedThreadStatuses = ref<number[]>([]);

// Expose on window so legacy JS can read filter values.
// The Vue component now owns the single source of truth.
(window as any).selectedCategoryIds = selectedCategoryIds;
(window as any).selectedThreadStatuses = selectedThreadStatuses;

//Initialise state from DOM on mount 
function readInitialStateFromDOM(): void {
    const cats: number[] = [];
    document.querySelectorAll('#thread_category button').forEach(btn => {
        if ((btn as HTMLElement).dataset.btnSelected === 'true') {
            const id = parseInt((btn as HTMLElement).dataset.cat_id ?? '', 10);
            if (!isNaN(id)) cats.push(id);
        }
    });
    selectedCategoryIds.value = cats;

    const statuses: number[] = [];
    document.querySelectorAll('#thread_status_select button').forEach(btn => {
        if ((btn as HTMLElement).dataset.btnSelected === 'true') {
            const id = parseInt((btn as HTMLElement).dataset.sel_id ?? '', 10);
            if (!isNaN(id)) statuses.push(id);
        }
    });
    selectedThreadStatuses.value = statuses;
}

onMounted(() => {
    readInitialStateFromDOM();
});

function toggleFilterButton(event: MouseEvent, catId?: number, statusSelId?: number): void {
    const btn = event.currentTarget as HTMLElement;
    const $btn = (window as any).$(btn);
    const current = btn.dataset.btnSelected;

    if (current === 'true') {
        btn.dataset.btnSelected = 'false';
        btn.classList.remove('filter-active');
        btn.classList.add('filter-inactive');
        $btn.data('btn-selected', 'false');
        if (catId !== undefined) {
            selectedCategoryIds.value = selectedCategoryIds.value.filter(id => id !== catId);
        }
        if (statusSelId !== undefined) {
            selectedThreadStatuses.value = selectedThreadStatuses.value.filter(id => id !== statusSelId);
        }
    }
    else {
        btn.dataset.btnSelected = 'true';
        btn.classList.remove('filter-inactive');
        btn.classList.add('filter-active');
        $btn.data('btn-selected', 'true');
        if (catId !== undefined) {
            selectedCategoryIds.value = [...selectedCategoryIds.value, catId];
        }
        if (statusSelId !== undefined) {
            selectedThreadStatuses.value = [...selectedThreadStatuses.value, statusSelId];
        }
    }
    (window as any).updateClearFilterButton?.();
    (window as any).updateThreads?.(true, (window as any).saveFilterState);
}

function toggleUnreadLabel(event: MouseEvent): void {
    const label = event.currentTarget as HTMLElement;
    label.classList.toggle('filter-inactive');
    label.classList.toggle('filter-active');
}

function onUnreadChange(): void {
    (window as any).updateThreads?.(true, (window as any).saveFilterState);
    (window as any).checkUnread?.();
}
</script>

<template>
  <div
    id="forum_filter_bar"
    data-testid="forum-filter-bar"
  >
    <label
      id="filter_unread_btn"
      class="btn btn-default btn-sm inline-block filter-inactive"
      for="unread"
      data-testid="filter-unread-label"
      @mousedown="toggleUnreadLabel"
    >
      Unread Only
    </label>
    <input
      id="unread"
      name="unread"
      type="checkbox"
      data-ays-ignore="true"
      data-testid="filter-unread-checkbox"
      @change="onUnreadChange"
    />

    <div
      id="thread_category"
      aria-label="Select thread category"
      class="inline-block"
      data-ays-ignore="true"
      data-testid="thread-category-filter"
    >
      <button
        v-for="category in props.categories.filter(isVisibleCategory)"
        :id="`categoryid_${category.id}`"
        :key="category.id"
        :data-cat_id="String(category.id)"
        class="btn btn-sm filter-inactive"
        data-btn-selected="false"
        type="button"
        :data-testid="`thread-category-${category.id}`"
        @mousedown.prevent="toggleFilterButton($event, category.id, undefined)"
      >
        {{ category.description }}
      </button>
    </div>

    <div
      id="thread_status_select"
      aria-label="Select thread status"
      class="inline-block"
      data-ays-ignore="true"
      data-testid="thread-status-filter"
    >
      <button
        class="btn btn-sm btn-default inline-block filter-inactive"
        data-btn-selected="false"
        data-sel_id="0"
        type="button"
        data-testid="thread-status-comment"
        @mousedown.prevent="toggleFilterButton($event, undefined, 0)"
      >
        Comment
      </button>
      <button
        class="btn btn-sm btn-default inline-block filter-inactive"
        data-btn-selected="false"
        data-sel_id="-1"
        type="button"
        data-testid="thread-status-unresolved"
        @mousedown.prevent="toggleFilterButton($event, undefined, -1)"
      >
        Unresolved
      </button>
      <button
        class="btn btn-sm btn-default inline-block filter-inactive"
        data-btn-selected="false"
        data-sel_id="1"
        type="button"
        data-testid="thread-status-resolved"
        @mousedown.prevent="toggleFilterButton($event, undefined, 1)"
      >
        Resolved
      </button>
    </div>
  </div>
</template>

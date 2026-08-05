<script setup lang="ts">
import { ref, watch } from 'vue';

interface ForumCategory {
    id: number;
    description: string;
    visibleDate: string | null;
    diff?: number;
}

interface Props {
    categories: ForumCategory[];
    initialSelectedCategoryIds?: number[];
    initialSelectedThreadStatuses?: number[];
    initialUnreadChecked?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    initialSelectedCategoryIds: () => [],
    initialSelectedThreadStatuses: () => [],
});

const emit = defineEmits<{
    'update:selectedCategoryIds': [ids: number[]];
    'update:selectedThreadStatuses': [statuses: number[]];
    'update:unreadChecked': [checked: boolean];
    'filter-change': [state: { categories: number[]; statuses: number[]; unread: boolean }];
    'save-state': [];
}>();

const isVisibleCategory = (category: ForumCategory): boolean => {
    return category.visibleDate === null || (Number(category.diff ?? 0) >= 0);
};

const selectedCategoryIds = ref<number[]>([...props.initialSelectedCategoryIds]);
const selectedThreadStatuses = ref<number[]>([...props.initialSelectedThreadStatuses]);
const unreadChecked = ref(props.initialUnreadChecked);

watch(selectedCategoryIds, (ids) => {
    emit('update:selectedCategoryIds', ids);
    emitFilterChange();
}, { deep: true });

watch(selectedThreadStatuses, (statuses) => {
    emit('update:selectedThreadStatuses', statuses);
    emitFilterChange();
}, { deep: true });

watch(unreadChecked, (checked) => {
    emit('update:unreadChecked', checked);
    emitFilterChange();
});

function emitFilterChange(): void {
    emit('filter-change', {
        categories: [...selectedCategoryIds.value],
        statuses: [...selectedThreadStatuses.value],
        unread: unreadChecked.value,
    });
}

function toggleCategory(categoryId: number): void {
    const index = selectedCategoryIds.value.indexOf(categoryId);
    if (index === -1) {
        selectedCategoryIds.value = [...selectedCategoryIds.value, categoryId];
    }
    else {
        selectedCategoryIds.value = selectedCategoryIds.value.filter((id) => id !== categoryId);
    }
    emit('save-state');
}

function toggleStatus(statusSelId: number): void {
    const index = selectedThreadStatuses.value.indexOf(statusSelId);
    if (index === -1) {
        selectedThreadStatuses.value = [...selectedThreadStatuses.value, statusSelId];
    }
    else {
        selectedThreadStatuses.value = selectedThreadStatuses.value.filter((id) => id !== statusSelId);
    }
    emit('save-state');
}

function toggleUnread(): void {
    unreadChecked.value = !unreadChecked.value;
    emit('save-state');
}

function isCategorySelected(categoryId: number): boolean {
    return selectedCategoryIds.value.includes(categoryId);
}

function isStatusSelected(statusSelId: number): boolean {
    return selectedThreadStatuses.value.includes(statusSelId);
}
</script>

<template>
  <div
    id="forum_filter_bar"
    data-testid="forum-filter-bar"
  >
    <button
      id="filter_unread_btn"
      class="btn btn-sm btn-default inline-block"
      :class="[unreadChecked ? 'filter-active' : 'filter-inactive']"
      data-testid="filter-unread-label"
      title="Toggle unread filter"
      @click="toggleUnread"
    >
      Unread Only
    </button>
    <input
      id="unread"
      name="unread"
      type="checkbox"
      data-ays-ignore="true"
      data-testid="filter-unread-checkbox"
      hidden
      :checked="unreadChecked"
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
        class="btn btn-sm"
        :class="isCategorySelected(category.id) ? 'filter-active' : 'filter-inactive'"
        :data-btn-selected="String(isCategorySelected(category.id))"
        type="button"
        :data-testid="`thread-category-${category.id}`"
        :title="`Filter by ${category.description}`"
        @click.prevent="toggleCategory(category.id)"
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
        class="btn btn-sm btn-default inline-block"
        :class="isStatusSelected(0) ? 'filter-active' : 'filter-inactive'"
        :data-btn-selected="String(isStatusSelected(0))"
        data-sel_id="0"
        type="button"
        data-testid="thread-status-comment"
        title="Filter by comment status"
        @click.prevent="toggleStatus(0)"
      >
        Comment
      </button>
      <button
        class="btn btn-sm btn-default inline-block"
        :class="isStatusSelected(-1) ? 'filter-active' : 'filter-inactive'"
        :data-btn-selected="String(isStatusSelected(-1))"
        data-sel_id="-1"
        type="button"
        data-testid="thread-status-unresolved"
        title="Filter by unresolved status"
        @click.prevent="toggleStatus(-1)"
      >
        Unresolved
      </button>
      <button
        class="btn btn-sm btn-default inline-block"
        :class="isStatusSelected(1) ? 'filter-active' : 'filter-inactive'"
        :data-btn-selected="String(isStatusSelected(1))"
        data-sel_id="1"
        type="button"
        data-testid="thread-status-resolved"
        title="Filter by resolved status"
        @click.prevent="toggleStatus(1)"
      >
        Resolved
      </button>
    </div>
  </div>
</template>

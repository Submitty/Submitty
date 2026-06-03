<script setup lang="ts">
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

function toggleFilterButton(event: MouseEvent): void {
    const btn = event.currentTarget as HTMLElement;
    const $btn = (window as any).$(btn);
    const current = btn.dataset.btnSelected;
    if (current === 'true') {
        btn.dataset.btnSelected = 'false';
        btn.classList.remove('filter-active');
        btn.classList.add('filter-inactive');
        $btn.data('btn-selected', 'false');
    }
    else {
        btn.dataset.btnSelected = 'true';
        btn.classList.remove('filter-inactive');
        btn.classList.add('filter-active');
        $btn.data('btn-selected', 'true');
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
        @mousedown.prevent="toggleFilterButton"
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
        @mousedown.prevent="toggleFilterButton"
      >
        Comment
      </button>
      <button
        class="btn btn-sm btn-default inline-block filter-inactive"
        data-btn-selected="false"
        data-sel_id="-1"
        type="button"
        data-testid="thread-status-unresolved"
        @mousedown.prevent="toggleFilterButton"
      >
        Unresolved
      </button>
      <button
        class="btn btn-sm btn-default inline-block filter-inactive"
        data-btn-selected="false"
        data-sel_id="1"
        type="button"
        data-testid="thread-status-resolved"
        @mousedown.prevent="toggleFilterButton"
      >
        Resolved
      </button>
    </div>
  </div>
</template>

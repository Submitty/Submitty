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
    >
      Unread Only
    </label>
    <input
      id="unread"
      name="unread"
      type="checkbox"
      data-ays-ignore="true"
      data-testid="filter-unread-checkbox"
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
      >
        Comment
      </button>
      <button
        class="btn btn-sm btn-default inline-block filter-inactive"
        data-btn-selected="false"
        data-sel_id="-1"
        type="button"
        data-testid="thread-status-unresolved"
      >
        Unresolved
      </button>
      <button
        class="btn btn-sm btn-default inline-block filter-inactive"
        data-btn-selected="false"
        data-sel_id="1"
        type="button"
        data-testid="thread-status-resolved"
      >
        Resolved
      </button>
    </div>
  </div>
</template>

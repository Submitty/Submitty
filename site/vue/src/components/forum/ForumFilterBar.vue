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
  <div id="forum_filter_bar">
    <label
      id="filter_unread_btn"
      class="btn btn-default btn-sm inline-block filter-inactive"
      for="unread"
    >
      Unread Only
    </label>
    <input
      id="unread"
      name="unread"
      type="checkbox"
      data-ays-ignore="true"
    />

    <div
      id="thread_category"
      aria-label="Select thread category"
      class="inline-block"
      data-ays-ignore="true"
    >
      <button
        v-for="category in props.categories.filter(isVisibleCategory)"
        :id="`categoryid_${category.id}`"
        :key="category.id"
        :data-cat_id="String(category.id)"
        class="btn btn-sm filter-inactive"
        data-btn-selected="false"
        type="button"
      >
        {{ category.description }}
      </button>
    </div>

    <div
      id="thread_status_select"
      aria-label="Select thread status"
      class="inline-block"
      data-ays-ignore="true"
    >
      <button
        class="btn btn-sm btn-default inline-block filter-inactive"
        data-btn-selected="false"
        data-sel_id="0"
        type="button"
      >
        Comment
      </button>
      <button
        class="btn btn-sm btn-default inline-block filter-inactive"
        data-btn-selected="false"
        data-sel_id="-1"
        type="button"
      >
        Unresolved
      </button>
      <button
        class="btn btn-sm btn-default inline-block filter-inactive"
        data-btn-selected="false"
        data-sel_id="1"
        type="button"
      >
        Resolved
      </button>
    </div>
  </div>
</template>

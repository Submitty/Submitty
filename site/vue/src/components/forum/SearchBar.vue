<script setup lang="ts">
import $ from 'jquery';
import { ref, watch } from 'vue';

const props = defineProps<{
    csrfToken: string;
    searchQuery?: string;
}>();

const emit = defineEmits<{
    search: [query: string];
}>();

const query = ref(props.searchQuery ?? '');

// Transitional: keep hidden #search-content synced for jQuery consumers
// (modifyThreadList reads it, Search/Filter buttons submit via updateThreads)
watch(query, (val) => {
    $('#search-content').val(val);
}, { immediate: true });

function submitSearch() {
    const trimmed = query.value.trim();
    query.value = trimmed;
    emit('search', trimmed);
}

function clearSearch() {
    query.value = '';
    emit('search', '');
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter') {
        e.preventDefault();
        submitSearch();
    }
}
</script>

<template>
  <div
    class="search-input-wrapper"
    data-testid="search-bar-vue"
  >
    <input
      v-model="query"
      type="text"
      placeholder="Search here..."
      aria-label="Forum Search Input Box"
      data-ays-ignore="true"
      style="height: 100%; width: 100%; padding-right: 2rem;"
      @keydown="handleKeydown"
      @change="query = query.trim()"
    />
    <button
      v-if="query.length > 0"
      id="search-clear"
      style="display: block;"
      type="button"
      title="Clear search"
      aria-label="Clear search"
      data-ays-ignore="true"
      @click="clearSearch"
    >
      <i
        class="fa-solid fa-circle-xmark"
        aria-hidden="true"
      />
    </button>
  </div>
</template>

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
  <div data-testid="search-bar-vue">
    <div class="search-input-wrapper">
      <input
        v-model="query"
        type="text"
        placeholder="Search here..."
        aria-label="Forum Search Input Box"
        data-ays-ignore="true"
        @keydown="handleKeydown"
        @change="query = query.trim()"
      />
      <button
        v-show="query.length > 0"
        type="button"
        title="Clear search"
        aria-label="Clear search"
        data-ays-ignore="true"
        @click="clearSearch"
      >
        <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    csrfToken: string;
    searchQuery?: string;
}>();

const emit = defineEmits<{
    search: [query: string];
}>();

const query = ref(props.searchQuery ?? '');

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
      class="search-bar-input"
      type="text"
      placeholder="Search here..."
      aria-label="Forum Search Input Box"
      data-ays-ignore="true"
      data-testid="search-content-input"
      @keydown="handleKeydown"
      @change="query = query.trim()"
    />
    <button
      v-if="query.length > 0"
      id="search-clear"
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

<style scoped>
.search-bar-input {
    height: 100%;
    width: 100%;
    padding-right: 2rem;
}

#search-clear {
    display: block;
}
</style>

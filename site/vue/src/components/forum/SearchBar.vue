<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
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
    <div class="search-input-inner">
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
        class="search-clear"
        type="button"
        title="Clear search"
        aria-label="Clear search"
        data-ays-ignore="true"
        data-testid="search-clear"
        @click="clearSearch"
      >
        <i
          class="fa-solid fa-circle-xmark"
          aria-hidden="true"
        />
      </button>
    </div>
    <button
      type="button"
      name="search"
      title="Submit search"
      class="btn btn-primary"
      data-testid="search-submit"
      @click="submitSearch"
    >
      Search
    </button>
  </div>
</template>

<style scoped>
.search-input-wrapper {
    display: flex;
    align-items: stretch;
    gap: 0.5rem;
}

.search-input-inner {
    position: relative;
    flex: 1 1 auto;
    min-width: 30px;
}

.search-bar-input {
    box-sizing: border-box;
    height: 100%;
    width: 100%;
    padding-right: 2rem;
}

.search-clear {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    position: absolute;
    top: 50%;
    right: 0.6rem;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: var(--standard-medium-gray);
    padding: 0;
    line-height: 1;
    cursor: pointer;
}

.search-clear:hover,
.search-clear:focus {
    color: var(--default-black);
}
</style>

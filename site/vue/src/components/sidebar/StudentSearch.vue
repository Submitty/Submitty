<script setup lang="ts">
import { ref } from 'vue';
import { buildUrl } from '../../../../ts/utils/server';

type Course = { term: string; course: string };
type Result = {
    user_id: string;
    given_name: string;
    family_name: string;
    courses: Course[];
};

const query = ref('');
const results = ref<Result[]>([]);
const searched = ref(false);
const open = ref(false);
let timer: number | undefined;
let controller: AbortController | undefined;

function clear() {
    controller?.abort();
    query.value = '';
    results.value = [];
    searched.value = false;
    open.value = false;
}

function onInput() {
    window.clearTimeout(timer);
    timer = window.setTimeout(search, 250);
}

function onFocusOut(e: FocusEvent) {
    const root = e.currentTarget as HTMLElement;
    if (!root.contains(e.relatedTarget as Node | null)) {
        open.value = false;
    }
}

async function search() {
    controller?.abort();
    const q = query.value.trim();
    if (q.length < 2) {
        results.value = [];
        searched.value = false;
        return;
    }
    controller = new AbortController();
    try {
        const res = await fetch(
            `${buildUrl(['home', 'students', 'search'])}?q=${encodeURIComponent(q)}`,
            { signal: controller.signal },
        );
        const json = await res.json();
        results.value = json.status === 'success' ? json.data : [];
        searched.value = true;
        open.value = true;
    }
    catch (e) {
        if ((e as Error).name !== 'AbortError') {
            results.value = [];
            searched.value = true;
            open.value = true;
        }
    }
}
</script>

<template>
  <div
    class="student-search"
    data-testid="student-search"
    @focusout="onFocusOut"
  >
    <input
      v-model="query"
      type="search"
      placeholder="Search students"
      aria-label="Search students"
      data-testid="student-search-input"
      @input="onInput"
      @focus="open = true"
      @keydown.esc="clear"
    />
    <div
      v-if="open && searched"
      class="results"
      tabindex="-1"
      data-testid="student-search-results"
    >
      <p
        v-if="!results.length"
        class="empty"
      >
        No students found
      </p>
      <div
        v-for="s in results"
        :key="s.user_id"
        class="result"
      >
        <strong>{{ s.given_name }} {{ s.family_name }}</strong>
        <span class="uid">({{ s.user_id }})</span>
        <div class="courses">
          <a
            v-for="c in s.courses"
            :key="c.term + c.course"
            :href="buildUrl(['courses', c.term, c.course])"
          >{{ c.term }} {{ c.course }}</a>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.student-search {
    position: relative;
    margin: 0 15px 10px;
}
.student-search input {
    width: 100%;
    padding: 6px 8px;
    box-sizing: border-box;
    background: var(--standard-input-background);
    color: var(--default-black);
    border: 1px solid var(--standard-light-medium-gray);
    border-radius: 4px;
}
.results {
    position: absolute;
    top: 100%;
    left: 0;
    width: 320px;
    max-height: 400px;
    overflow-y: auto;
    z-index: 10;
    padding: 8px;
    background: var(--default-white);
    color: var(--default-black);
    border: 1px solid var(--standard-light-medium-gray);
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
}
.result {
    padding: 6px 0;
}
.uid {
    margin-left: 4px;
}
.courses {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 10px;
}
.courses a {
    color: var(--nav-sidebar-text);
}
.courses a:hover {
    text-decoration: underline;
}
</style>
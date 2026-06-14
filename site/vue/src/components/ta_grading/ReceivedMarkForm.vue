<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';

interface MarkStats {
    section_submitter_count: string;
    total_submitter_count: string;
    section_graded_component_count: string;
    total_graded_component_count: string;
    section_total_component_count: string;
    total_total_component_count: string;
    submitter_ids: string[];
    submitter_anon_ids: Record<string, string>;
}

interface ShowMarkStatsEvent extends CustomEvent {
    detail: {
        componentTitle: string;
        markTitle: string;
        stats: MarkStats;
    };
}

interface StudentLink {
    name: string;
    url: string;
}

const visible = ref(false);
const componentTitle = ref('');
const markTitle = ref('');
const stats = ref<MarkStats | null>(null);
const { baseUrl, searchParams } = buildUrlParts();

const studentLinks = computed<StudentLink[]>(() => {
    if (!stats.value) return [];
    const params = new URLSearchParams(searchParams);
    return stats.value.submitter_ids.map((id) => {
        params.set('who_id', stats.value!.submitter_anon_ids[id] ?? id);
        return { name: id, url: `${baseUrl}?${params.toString()}` };
    });
});

function buildUrlParts() {
    const loc = window.location.href.split('?');
    let base = loc[0];
    if (base.endsWith('update')) {
        base = `${base.slice(0, -6)}grading/grade`;
    }
    return { baseUrl: base, searchParams: loc[1] ?? '' };
}

function onShowMarkStats(e: Event) {
    const detail = (e as ShowMarkStatsEvent).detail;
    componentTitle.value = detail.componentTitle;
    markTitle.value = detail.markTitle;
    stats.value = detail.stats;
    visible.value = true;
}

function close() {
    visible.value = false;
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && visible.value) {
        close();
    }
}

onMounted(() => {
    window.addEventListener('show-mark-stats', onShowMarkStats);
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('show-mark-stats', onShowMarkStats);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <div
    v-if="visible"
    class="popup-form"
    data-testid="mark-stats-popup"
    style="display: block;"
  >
    <div class="popup-box" @click="close">
      <div class="popup-window" @click.stop data-testid="popup-window">
        <div class="form-title">
          <h1>Mark Statistics</h1>
          <button
            data-testid="close-button"
            class="btn btn-default close-button"
            tabindex="-1"
            type="button"
            @click="close"
          >Close</button>
        </div>
        <div class="form-body">
          <h3>
            <span data-testid="question-title">{{ componentTitle }}</span>:
            <em data-testid="mark-title">{{ markTitle }}</em>
          </h3>
          <br>
          <strong># of submitters with mark:</strong>
          <span data-testid="section-submitter-count">{{ stats?.section_submitter_count ?? '0' }}</span>
          (<span data-testid="total-submitter-count">{{ stats?.total_submitter_count ?? '0' }}</span>)
          <br>
          <strong># of graded components:</strong>
          <span data-testid="section-graded-component-count">{{ stats?.section_graded_component_count ?? '0' }}</span>
          (<span data-testid="total-graded-component-count">{{ stats?.total_graded_component_count ?? '0' }}</span>)
          <br>
          <strong># of total components:</strong>
          <span data-testid="section-total-component-count">{{ stats?.section_total_component_count ?? '0' }}</span>
          (<span data-testid="total-total-component-count">{{ stats?.total_total_component_count ?? '0' }}</span>)
          <br>
          <br>
          <strong>Students:</strong>
          <br>
          <span data-testid="student-names">
            <template v-if="studentLinks.length > 0">
              <span v-for="(student, index) in studentLinks" :key="student.name">
                <a :href="student.url">{{ student.name }}</a><span v-if="index < studentLinks.length - 1">, </span>
              </span>
            </template>
            <template v-else>
              <br>
            </template>
          </span>
          <div class="form-buttons">
            <div class="form-button-container">
              <button
                class="btn btn-default close-button"
                data-testid="popup-close-button"
                tabindex="0"
                type="button"
                @click="close"
              >Close</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

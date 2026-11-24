<script setup lang="ts">
import Popup from '../Popup.vue';

export interface MarkStats {
    section_submitter_count: string;
    total_submitter_count: string;
    is_full_grader?: boolean;
    submitter_ids: string[];
    submitter_anon_ids: Record<string, string>;
    graded_by_me_with_mark?: string[];
    graded_by_others_with_mark?: string[];
    graded_by_me_without_mark?: string[];
    graded_by_others_without_mark?: string[];
}

export interface StudentLink {
    name: string;
    url: string;
}

withDefaults(
    defineProps<{
        show: boolean;
        componentTitle: string;
        markTitle: string;
        stats: MarkStats | null;
        studentLinks?: StudentLink[];
        gradedByMeWithMark?: StudentLink[];
        gradedByOthersWithMark?: StudentLink[];
        gradedByMeWithoutMark?: StudentLink[];
        gradedByOthersWithoutMark?: StudentLink[];
    }>(),
    {
        studentLinks: () => [],
        gradedByMeWithMark: () => [],
        gradedByOthersWithMark: () => [],
        gradedByMeWithoutMark: () => [],
        gradedByOthersWithoutMark: () => [],
    },
);

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
  <Popup
    v-if="show"
    :visible="show"
    title="Mark Statistics"
    @toggle="emit('close')"
  >
    <template #trigger>
      <span class="hidden-trigger" />
    </template>
    <div class="form-body">
      <h3>
        <span data-testid="question-title">{{ componentTitle }}</span>:
        <em data-testid="mark-title">{{ markTitle }}</em>
      </h3>
      <br>

      <div class="mark-stats-section">
        <h4 class="mark-stats-heading">
          Students who received this mark
        </h4>
        <p>
          <strong>Number of submitters: </strong>
          <span data-testid="section-submitter-count">{{ gradedByMeWithMark.length + gradedByOthersWithMark.length }}</span>
          <template v-if="!stats?.is_full_grader && stats && stats.total_submitter_count !== stats.section_submitter_count">
            (<span data-testid="total-submitter-count">{{ stats.total_submitter_count }}</span>)
          </template>
        </p>

        <div class="mark-stats-group">
          <strong>1. Graded by me ({{ gradedByMeWithMark.length }}):</strong>
          <div data-testid="graded-by-me-with-mark">
            <template v-if="gradedByMeWithMark.length > 0">
              <span
                v-for="(student, index) in gradedByMeWithMark"
                :key="student.name"
              >
                <a :href="student.url">{{ student.name }}</a><span
                  v-if="index < gradedByMeWithMark.length - 1"
                >, </span>
              </span>
            </template>
            <template v-else>
              <em>None</em>
            </template>
          </div>
        </div>

        <div class="mark-stats-group">
          <strong>2. Graded by others ({{ gradedByOthersWithMark.length }}):</strong>
          <div data-testid="graded-by-others-with-mark">
            <template v-if="gradedByOthersWithMark.length > 0">
              <span
                v-for="(student, index) in gradedByOthersWithMark"
                :key="student.name"
              >
                <a :href="student.url">{{ student.name }}</a><span
                  v-if="index < gradedByOthersWithMark.length - 1"
                >, </span>
              </span>
            </template>
            <template v-else>
              <em>None</em>
            </template>
          </div>
        </div>
      </div>

      <hr class="mark-stats-divider">

      <div class="mark-stats-section">
        <h4 class="mark-stats-heading">
          Students who did not receive this mark
        </h4>
        <p>
          <strong>Number of submitters: </strong>
          <span data-testid="different-mark-submitter-count">{{ gradedByMeWithoutMark.length + gradedByOthersWithoutMark.length }}</span>
        </p>

        <div class="mark-stats-group">
          <strong>1. Graded by me ({{ gradedByMeWithoutMark.length }}):</strong>
          <div data-testid="graded-by-me-without-mark">
            <template v-if="gradedByMeWithoutMark.length > 0">
              <span
                v-for="(student, index) in gradedByMeWithoutMark"
                :key="student.name"
              >
                <a :href="student.url">{{ student.name }}</a><span
                  v-if="index < gradedByMeWithoutMark.length - 1"
                >, </span>
              </span>
            </template>
            <template v-else>
              <em>None</em>
            </template>
          </div>
        </div>

        <div class="mark-stats-group">
          <strong>2. Graded by others ({{ gradedByOthersWithoutMark.length }}):</strong>
          <div data-testid="graded-by-others-without-mark">
            <template v-if="gradedByOthersWithoutMark.length > 0">
              <span
                v-for="(student, index) in gradedByOthersWithoutMark"
                :key="student.name"
              >
                <a :href="student.url">{{ student.name }}</a><span
                  v-if="index < gradedByOthersWithoutMark.length - 1"
                >, </span>
              </span>
            </template>
            <template v-else>
              <em>None</em>
            </template>
          </div>
        </div>
      </div>
    </div>
  </Popup>
</template>

<style scoped>
.hidden-trigger {
    display: none;
}
.mark-stats-section {
    margin-top: 10px;
}
.mark-stats-heading {
    margin: 0 0 8px;
    font-size: 1.1em;
}
.mark-stats-group {
    margin-bottom: 12px;
    margin-left: 10px;
}
.mark-stats-divider {
    border: 0;
    border-top: 1px solid var(--standard-light-gray);
    margin: 14px 0;
}
</style>

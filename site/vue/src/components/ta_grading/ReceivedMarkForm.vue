<script setup lang="ts">
import Popup from '../Popup.vue';

export interface MarkStats {
    section_submitter_count: string;
    total_submitter_count: string;
    section_graded_component_count: string;
    total_graded_component_count: string;
    section_total_component_count: string;
    total_total_component_count: string;
    submitter_ids: string[];
    submitter_anon_ids: Record<string, string>;
}

export interface StudentLink {
    name: string;
    url: string;
}

defineProps<{
    show: boolean;
    componentTitle: string;
    markTitle: string;
    stats: MarkStats | null;
    studentLinks: StudentLink[];
}>();

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
      <strong># of submitters with mark: </strong>
      <span data-testid="section-submitter-count">{{ stats?.section_submitter_count ?? '0' }}</span>
      (<span data-testid="total-submitter-count">{{ stats?.total_submitter_count ?? '0' }}</span>)
      <br>
      <strong># of graded components: </strong>
      <span data-testid="section-graded-component-count">{{ stats?.section_graded_component_count ?? '0' }}</span>
      (<span data-testid="total-graded-component-count">{{ stats?.total_graded_component_count ?? '0' }}</span>)
      <br>
      <strong># of total components: </strong>
      <span data-testid="section-total-component-count">{{ stats?.section_total_component_count ?? '0' }}</span>
      (<span data-testid="total-total-component-count">{{ stats?.total_total_component_count ?? '0' }}</span>)
      <br>
      <br>
      <strong>Students:</strong>
      <br>
      <span data-testid="student-names">
        <template v-if="studentLinks.length > 0">
          <span
            v-for="(student, index) in studentLinks"
            :key="student.name"
          >
            <a :href="student.url">{{ student.name }}</a><span
              v-if="index < studentLinks.length - 1"
              :key="`comma-${index}`"
            >, </span>
          </span>
        </template>
        <template v-else>
          <br>
        </template>
      </span>
    </div>
  </Popup>
</template>

<style scoped>
.hidden-trigger {
    display: none;
}
</style>

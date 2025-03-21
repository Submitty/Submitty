<script setup lang="ts">
import { defineProps, ref } from 'vue';
const props = defineProps<{
    performanceWarning: boolean;
    submittyQueries: string[];
    courseQueries: string[];
}>();
const isVisible = ref(props.performanceWarning);
</script>

<template>
  <div
    v-if="isVisible"
    id="performance-warning-system-message"
    class="system-message warning"
    style="display: flex;"
  >
    <span id="performance-warning-system-message-text">
      Developer Warning: Excessive or duplicate database queries observed: {{ submittyQueries.length + courseQueries.length }} queries executed. Click "Show Page Details" at the bottom of the page for more info.
    </span>
    <i
      id="close-performance-warning-system-message"
      class="fa fa-times"
      style="padding: 4px; cursor: pointer;"
      @click="isVisible= false"
    />
  </div>
</template>

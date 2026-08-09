<script setup lang="ts">
import type { MarkConflictResolution, MarkInfo } from '@/types/MarkConflict';

withDefaults(
    defineProps<{
    // The mark version to display, or null to show the deleted message
        mark: MarkInfo | null;
        // Message shown in place of the mark info when `mark` is null
        deletedMessage?: string;
        // Label for the resolution button
        buttonLabel: string;
        // Accessible name for the resolution button
        buttonTitle: string;
        // Bootstrap button style: 'default' or 'primary'
        buttonStyle?: 'default' | 'primary';
        // data-testid prefix for the row, info span, and button
        testid: string;
        // Which version this row resolves to when its button is clicked
        resolution: MarkConflictResolution;
    }>(), {
        deletedMessage: '',
        buttonStyle: 'primary',
    });

const emit = defineEmits<{
    resolve: [resolution: MarkConflictResolution];
}>();
</script>

<template>
  <div
    class="row mark-resolve"
    :class="`mark-resolve-${resolution}`"
    :data-testid="testid"
  >
    <template v-if="mark">
      <span
        class="col"
        :data-testid="`${testid}-info`"
      >
        ({{ mark.points }}) {{ mark.title ?? '' }}
        <template v-if="mark.publish">
          -- <i>Show mark to all students</i>
        </template>
      </span>
    </template>
    <template v-else>
      <span
        class="col mark-deleted-message"
        :data-testid="`${testid}-deleted`"
      >{{ deletedMessage }}</span>
    </template>
    <span class="col-no-gutters button-container">
      <input
        type="button"
        class="btn"
        :class="`btn-${buttonStyle}`"
        :value="buttonLabel"
        :title="buttonTitle"
        :data-testid="`${testid}-btn`"
        @click="emit('resolve', resolution)"
      >
    </span>
  </div>
</template>

<style scoped>
.mark-resolve span {
    padding: 3px;
    border-width: 2px;
    margin: 0;
    display: inline-flex;
    align-items: center;
}

.mark-resolve .button-container {
    width: 150px;
}

.mark-resolve .button-container input {
    width: 100%;
}
</style>

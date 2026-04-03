<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    taScore: number;
    taMax: number;
    activeSameAsGraded: boolean;
}

const props = defineProps<Props>();

const badgeClass = computed(() => {
    if (!props.activeSameAsGraded) {
        return 'gray-background';
    }
    if (props.taScore >= props.taMax) {
        return 'green-background';
    }
    if (props.taScore > props.taMax * 0.5) {
        return 'yellow-background';
    }
    return 'red-background';
});

const displayText = computed(() => {
    return `${props.taScore} / ${props.taMax}`;
});

const shouldShow = computed(() => {
    return props.taMax > 0 || props.taScore < 0;
});
</script>

<template>
  <div
    class="box submission-page-total-header"
    data-testid="ta-total-score"
  >
    <div
      class="box-title-total key_to_click"
      tabindex="0"
    >
      <span
        v-if="shouldShow"
        class="badge"
        :class="badgeClass"
        data-testid="score-pill-badge"
      >
        {{ displayText }}
      </span>
      <div
        v-else
        class="no-badge"
      />
      <h4>TA / Instructor Grading Total</h4>
    </div>
  </div>
</template>

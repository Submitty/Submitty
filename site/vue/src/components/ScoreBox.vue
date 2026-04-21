<script setup lang="ts">
import Badge from './Badge.vue';

interface Props {
    taScore: number;
    taMax: number;
    activeSameAsGraded: boolean;
}

const props = defineProps<Props>();
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
      <Badge
        :earned="props.taScore"
        :max="props.taMax"
        :extra-credit="false"
        :active-same-as-graded="props.activeSameAsGraded"
      />
      <h4>TA / Instructor Grading Total</h4>
    </div>
  </div>
</template>

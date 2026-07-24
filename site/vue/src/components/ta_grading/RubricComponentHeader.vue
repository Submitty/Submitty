<script setup lang="ts">
const props = defineProps<{
    totalScore: number | null;
    maxValue: number;
}>();

function getBadgeStyle(earned: number | null, total: number): string {
    if (earned === null || total === undefined) {
        return '';
    }
    if (total === 0 && earned === 0) {
        return '';
    }
    const percent = earned / total;
    if (percent < 0.5) {
        return 'red-background';
    }
    if (percent < 1) {
        return 'yellow-background';
    }
    return 'green-background';
}

const badgeClass = getBadgeStyle(props.totalScore, props.maxValue);

// Round to full integers to match the badge's pre-refactor visual style
const displayScore = props.totalScore !== null
    ? Math.round(props.totalScore).toString()
    : '\u2014';
const displayMax = Math.round(props.maxValue).toString();
</script>

<template>
  <strong
    id="grading_total"
    data-testid="grading-total"
    class="badge"
    :class="badgeClass"
  >
    {{ displayScore }} / {{ displayMax }}
  </strong>
</template>

<script setup lang="ts">
const props = defineProps<{
    totalScore: number | null;
    maxValue: number;
}>();

function getBadgeStyle(earned: number | null, total: number): string {
    if (earned === null) {
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

function formatScore(value: number): string {
    return (Math.round(value * 1000) / 1000).toString();
}

const displayScore = props.totalScore !== null
    ? formatScore(props.totalScore)
    : '\u2212';
const displayMax = formatScore(props.maxValue);
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

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    earned: number;
    max: number;
    extraCredit: boolean;
    activeSameAsGraded: boolean;
}

const props = defineProps<Props>();

const shouldShow = computed(() => {
    if (props.extraCredit) {
        return true;
    }
    if (props.max > 0) {
        return true;
    }
    return props.earned < 0;
});

const badgeClass = computed(() => {
    if (!props.activeSameAsGraded) {
        return 'gray-background';
    }
    if (props.extraCredit) {
        return props.earned === 0 ? 'gray-background' : 'green-background';
    }
    if (props.earned < 0) {
        return props.earned < 0.5 * props.max ? 'red-background' : 'yellow-background';
    }
    if (props.earned >= props.max) {
        return 'green-background';
    }
    if (props.earned > props.max * 0.5) {
        return 'yellow-background';
    }
    return 'red-background';
});

const displayText = computed(() => {
    if (props.extraCredit) {
        return `+${props.earned}`;
    }

    if (props.earned < 0) {
        if (props.max !== 0) {
            return `-${Math.abs(props.earned)} / ${props.max}`;
        }
        return `-${Math.abs(props.earned)}`;
    }

    return `${props.earned} / ${props.max}`;
});
</script>

<template>
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
</template>

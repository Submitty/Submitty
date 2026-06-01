<script setup lang="ts">
import { onMounted } from 'vue';

/**
 * Rubric Component - TAGrading Rubric Body
 *
 * This component renders the rubric body for TA grading.
 * rn it acts as a mount point for the existing Twig.js-rendered content.
 * The RubricPanel component handles the initial data fetch and DOM rendering.
 * This component will gradually take over rendering responsibilities.
 */

interface Props {
    gradeableId: string;
    anonId: string;
    displayVersion: number;
    allowCustomMarks: boolean;
    isPeerGrader: boolean;
    graderId: string;
    canVerify: boolean;
}

const props = defineProps<Props>();

// Expose reload method for parent components
defineExpose({
    reload: async () => {
        await (window as any).reloadGradingRubric?.(props.gradeableId, props.anonId);
    },
});
</script>

<template>
    <!--
      Passive mount point. Content is injected into #grading-box by
      the legacy reloadGradingRubric function (called from RubricPanel).
      This component will gradually own the rendering.
    -->
    <div class="rubric-body-container" data-testid="rubric-body"></div>
</template>

<style scoped>
.rubric-body-container {
    width: 100%;
}
</style>

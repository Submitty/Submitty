<script setup lang="ts">
import { onMounted, ref } from 'vue';

const props = defineProps<{
    gradeableId: string;
    anonId: string;
    isTaGrading: boolean;
    hasActiveVersion: boolean;
    hasSubmission: boolean;
    hasOverriddenGrades: boolean;
    versionConflict: boolean;
    isWithdrawnStudent: boolean;
    showVerifyAll: boolean;
    showClearConflicts: boolean;
    showSilentEdit: boolean;
    isPeerGrader: boolean;
    displayVersion: number;
    graderId: string;
    verifierId: string;
    canVerify: boolean;
    allowCustomMarks: boolean;
}>();

const containerRef = ref<HTMLElement | null>(null);

onMounted(async () =>{
    await (window as any).loadTemplates?.();
    await (window as any).reloadGradingRubric?.(props.gradeableId, props.anonId);
})
</script>

<template>
    <div id="rubric-vue-shell">
        <div id="grading-box" ref="containerRef"></div>
    </div>
</template>

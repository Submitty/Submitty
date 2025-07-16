<script setup lang="ts">
import TaGradingToolbar from '@/components/ta_grading/TaGradingToolbar.vue';
import { initToolbar } from '@/ts/ta-grading-layout';
import { onMounted } from 'vue';
const { homeUrl, prevStudentUrl, nextStudentUrl, progress } = defineProps<{
    homeUrl: string;
    prevStudentUrl: string;
    nextStudentUrl: string;
    progress: number;
}>();

onMounted(() => {
    initToolbar();
});
</script>

<template>
  <TaGradingToolbar
    :home-url="homeUrl"
    :prev-student-url="prevStudentUrl"
    :next-student-url="nextStudentUrl"
    :progress="progress"
  />
</template>

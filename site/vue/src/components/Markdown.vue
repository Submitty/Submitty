<script setup lang="ts">
import { computed } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

interface Props {
    content: string;
    testId?: string;
}

const props = defineProps<Props>();

const markdownToHtml = (markdown: string | null | undefined): string => {
    if (!markdown) {
        return '';
    }
    return marked(markdown, { async: false });
};

const htmlContent = computed(() => {
    const rawHtml = markdownToHtml(props.content);
    return DOMPurify.sanitize(rawHtml);
});
</script>

<template>
  <div
    class="markdown"
    :data-testid="testId"
    v-html="htmlContent"
  />
</template>

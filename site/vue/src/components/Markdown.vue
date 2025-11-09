<!-- eslint-disable vue/no-v-html -->
<script setup lang="ts">
import { computed } from 'vue';
import { Marked, type TokenizerExtension } from 'marked';
import { renderToString } from 'katex';
import DOMPurify from 'dompurify';

interface Props {
    content: string;
    testId?: string;
}

const props = defineProps<Props>();
const inlineLatex: (TokenizerExtension) = {
    name: 'inlineLatex',
    level: 'inline',
    start(src: string) {
        // Match any math delimiter
        return src.match(/\$\$|\\\[|\$|\\\(/)?.index ?? -1;
    },
    tokenizer(src: string) {
        // Match display and inline math
        const codeSpan = /^(?:\$\$([^$]+?)\$\$|\\\[([^\]]+?)\\\]|\$([^$]+?)\$|\\\(([^)]+?)\\\))/.exec(src);
        if (!codeSpan) {
            return;
        }
        let math = '';
        let displayMode = false;
        if (codeSpan[1] !== undefined) {
            math = codeSpan[1];
            displayMode = true;
        }
        else if (codeSpan[2] !== undefined) {
            math = codeSpan[2];
            displayMode = true;
        }
        else if (codeSpan[3] !== undefined) {
            math = codeSpan[3];
            displayMode = false;
        }
        else if (codeSpan[4] !== undefined) {
            math = codeSpan[4];
            displayMode = false;
        }
        return {
            type: 'html',
            raw: codeSpan[0],
            text: renderToString(math, { displayMode }),
        };
    },
};
const markdownToHtml = (markdown: string | null | undefined): string => {
    if (!markdown) {
        return '';
    }
    const marked = new Marked({ extensions: [inlineLatex] });
    return marked.parse(markdown, { async: false });
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

<style scoped>
.markdown :deep(.katex-display) {
  width: 100%;
  text-align: center;
  margin: 1em 0;
}
</style>

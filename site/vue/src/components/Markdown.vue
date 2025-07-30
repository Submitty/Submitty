<script setup lang="ts">
import { computed } from 'vue';
import { Marked, type TokenizerExtension } from 'marked';
import { renderToString } from 'katex';
import DOMPurify from 'dompurify';

interface Props {
    content: string;
    testId?: string;
}

const props = withDefaults(defineProps<Props>(), {
    testId: undefined,
});

const inlineLatex: (TokenizerExtension) = {
    name: 'inlineLatex',
    level: 'inline',
    start(src: string) {
        return src.match(/\$(?!\$)/)?.index || -1;
    },
    tokenizer(src: string) {
        const codeSpan = /^(?:\$(.+?)\$|\\\((.+?)\\\))/.exec(src);
        if (!codeSpan) {
            return;
        }
        return {
            type: 'html',
            raw: codeSpan[0],
            text: renderToString(codeSpan[1] || codeSpan[2]),
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

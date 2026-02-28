<script setup lang="ts">
import { computed } from 'vue';
import { Marked, type TokenizerAndRendererExtension } from 'marked';
import { renderToString } from 'katex';
import DOMPurify from 'dompurify';

interface Props {
    content: string;
    testId?: string;
}

const props = defineProps<Props>();
const inlineLatex: TokenizerAndRendererExtension = {
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
            type: 'inlineLatex',
            raw: codeSpan[0],
            text: renderToString(math, { displayMode }),
        };
    },
    renderer(token) {
        return (token as unknown as { text: string }).text;
    },
};
const escapeHtml = (html: string): string => {
    return html.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
};
const markdownToHtml = (markdown: string | null | undefined): string => {
    if (!markdown) {
        return '';
    }
    const marked = new Marked({
        extensions: [inlineLatex],
        renderer: {
            html(token) {
                return escapeHtml(token.text);
            },
        },
    });
    return marked.parse(markdown, { async: false });
};

const htmlContent = computed(() => {
    const rawHtml = markdownToHtml(props.content);
    return DOMPurify.sanitize(rawHtml);
});
</script>

<template>
  <!-- eslint-disable vue/no-v-html -->
  <div
    class="markdown"
    :data-testid="testId"
    v-html="htmlContent"
  />
  <!-- eslint-enable vue/no-v-html -->
</template>

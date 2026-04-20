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
const forumBaseUrl: string = (window as Window & { forumThreadBaseUrl?: string }).forumThreadBaseUrl ?? '';

const threadRef: TokenizerExtension = {
    name: 'threadRef',
    level: 'inline',
    start(src: string) {
        return src.match(/#\d/)?.index ?? -1;
    },
    tokenizer(src: string) {
        const match = /^#(\d+)/.exec(src);
        if (!match) {
            return;
        }
        return {
            type: 'html',
            raw: match[0],
            text: forumBaseUrl
                ? `<a href="${forumBaseUrl}/threads/${match[1]}" class="forum-thread-ref">#${match[1]}</a>`
                : match[0],
        };
    },
};

const markdownToHtml = (markdown: string | null | undefined): string => {
    if (!markdown) {
        return '';
    }
    const marked = new Marked({ extensions: [inlineLatex, threadRef] });
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

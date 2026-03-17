<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue';
import { Marked, type TokenizerExtension, type RendererExtension } from 'marked';
import { renderToString } from 'katex';
import DOMPurify from 'dompurify';
import { buildCourseUrl } from '../../../ts/utils/server';

interface Props {
    content: string;
    testId?: string;
}

const props = defineProps<Props>();

// Cache for resolved thread titles: id -> title or null
const threadTitleCache = ref<Record<number, string | null>>({});
const resolvedContent = ref('');

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

const threadReference: (TokenizerExtension & RendererExtension) = {
    name: 'threadReference',
    level: 'inline',
    start(src: string) {
        return src.match(/(?:^|[\s(])#\d/)?.index ?? -1;
    },
    tokenizer(src: string) {
        const match = /^(?:^|[\s(])?(#(\d+))/.exec(src);
        if (!match) {
            return;
        }
        const prefix = match[0].substring(0, match[0].indexOf('#'));
        return {
            type: 'threadReference',
            raw: match[0],
            threadId: parseInt(match[2], 10),
            prefix: prefix,
        };
    },
    renderer(token) {
        const id = token.threadId as number;
        const prefix = token.prefix as string;
        const title = threadTitleCache.value[id];
        if (title !== undefined && title !== null) {
            const url = `${buildCourseUrl(['forum', 'threads', String(id)])}`;
            const escapedTitle = title.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            return `${prefix}<a href="${url}" class="thread-reference" title="${escapedTitle}">#${id}</a>`;
        }
        return `${prefix}#${id}`;
    },
};

function extractThreadIds(text: string): number[] {
    const regex = /(?:^|[\s(])#(\d+)/g;
    const ids: number[] = [];
    let match;
    while ((match = regex.exec(text)) !== null) {
        const id = parseInt(match[1], 10);
        if (id > 0 && !ids.includes(id)) {
            ids.push(id);
        }
    }
    return ids;
}

async function resolveThreadIds(ids: number[]): Promise<void> {
    const unresolvedIds = ids.filter((id) => !(id in threadTitleCache.value));
    if (unresolvedIds.length === 0) {
        return;
    }
    try {
        const url = `${buildCourseUrl(['forum', 'threads', 'resolve'])}?ids=${unresolvedIds.join(',')}`;
        const response = await fetch(url);
        const json = await response.json();
        if (json.status === 'success') {
            for (const id of unresolvedIds) {
                threadTitleCache.value[id] = json.data[id] ?? null;
            }
        }
    }
    catch {
        for (const id of unresolvedIds) {
            threadTitleCache.value[id] = null;
        }
    }
}

function renderMarkdown(): string {
    if (!props.content) {
        return '';
    }
    const marked = new Marked({ extensions: [inlineLatex, threadReference] });
    const rawHtml = marked.parse(props.content, { async: false });
    return DOMPurify.sanitize(rawHtml, {
        ADD_ATTR: ['title'],
    });
}

async function updateContent(): Promise<void> {
    const ids = extractThreadIds(props.content ?? '');
    if (ids.length > 0) {
        await resolveThreadIds(ids);
    }
    resolvedContent.value = renderMarkdown();
}

watch(() => props.content, () => {
    updateContent();
});

onMounted(() => {
    updateContent();
});
</script>

<template>
  <!-- eslint-disable vue/no-v-html -->
  <div
    class="markdown"
    :data-testid="testId"
    v-html="resolvedContent"
  />
  <!-- eslint-enable vue/no-v-html -->
</template>

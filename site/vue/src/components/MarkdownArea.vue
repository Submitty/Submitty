<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import Markdown from './Markdown.vue';

declare global {
    interface Window {
        thread_list?: Array<{ id?: number | string; title?: string } | string>;
    }
}

interface JQueryLike {
    autocomplete: (options?: unknown) => JQueryLike;
    data: (key: string) => unknown;
    parent: () => JQueryLike;
}

interface WindowWithJQuery extends Window {
    $: ((element: unknown) => JQueryLike) & {
        fn: {
            autocomplete?: unknown;
        };
    };
}

interface Props {
    markdownAreaId: string;
    markdownAreaValue: string;
    markdownStatusId?: string;
    class?: string;
    dataPreviousComment?: string;
    initializePreview?: boolean;
    markdownAreaName?: string;
    markdownHeaderId?: string | null;
    maxHeight?: string;
    minHeight?: string;
    noMaxlength?: boolean; // If true, sets maxlength to 524288
    placeholder?: string;
    previewDivId?: string | null;
    renderHeader?: boolean;
    rootClass?: string;
    textareaMaxlength?: string | number;
    required?: boolean;
    isPreviewLoading?: boolean;
    textareaOnKeyup?: string;
    textareaOnkeydown?: string;
    textareaOnPaste?: string;
    textareaOnChange?: string;
    textareaOnInput?: string;
    otherTextareaAttributes?: string;
    toggleButtonId?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'change': [event: Event];
    'keyup': [event: Event];
    'paste': [event: Event];
    'keydown': [event: Event];
    'preview': [value: string];
    'add-markdown': [
        value: { type: string; textarea: HTMLTextAreaElement | null },
    ];
}>();

const textareaRef = ref<HTMLTextAreaElement | null>(null);
const mode = ref('edit');
const content = ref(props.markdownAreaValue);
const isLoadingPreview = ref(false);

watch(content, (newValue) => {
    emit('update:modelValue', newValue ?? '');
});

watch(
    () => props.markdownAreaValue,
    (newValue) => {
        content.value = newValue;
    },
);

const maxLength = computed(() => {
    return props.noMaxlength ? '524288' : props.textareaMaxlength;
});

const previewStyle = computed(() => ({
    minHeight: props.minHeight,
    maxHeight: props.maxHeight,
}));

function setMode(newMode: 'edit' | 'preview') {
    mode.value = newMode;
    if (newMode === 'preview') {
        emit('preview', content.value ?? '');
    }
}

function addMarkdown(type: string) {
    const textarea = textareaRef.value;
    if (!textarea) {
        return;
    }

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);

    let insertText = '';
    let cursorOffset = 0;

    switch (type) {
        case 'bold':
            insertText = `**${selectedText}**`;
            cursorOffset = selectedText ? insertText.length : 2;
            break;
        case 'italic':
            insertText = `*${selectedText}*`;
            cursorOffset = selectedText ? insertText.length : 1;
            break;
        case 'code':
            insertText = `\`${selectedText}\``;
            cursorOffset = selectedText ? insertText.length : 1;
            break;
        case 'link':
            insertText = `[${selectedText || 'link text'}](url)`;
            cursorOffset = selectedText
                ? insertText.length - 4
                : insertText.length - 4;
            break;
        case 'blockquote': {
            const lines = selectedText.split('\n');
            insertText = lines.map((line) => `> ${line}`).join('\n');
            cursorOffset = insertText.length;
            break;
        }
        default:
            return;
    }

    // Insert the text
    const newValue
        = textarea.value.substring(0, start)
            + insertText
            + textarea.value.substring(end);
    content.value = newValue;

    // Set cursor position
    textarea.focus();
    const newCursorPos = selectedText
        ? start + cursorOffset
        : start + cursorOffset;
    setTimeout(() => {
        textarea.setSelectionRange(newCursorPos, newCursorPos);
    }, 0);

    emit('add-markdown', { type, textarea: textareaRef.value });
}

function handleKeyup(event: Event) {
    emit('keyup', event);

    // Call global function if specified
    if (props.textareaOnKeyup && window[props.textareaOnKeyup as keyof Window]) {
        (window[props.textareaOnKeyup as keyof Window] as (el: HTMLTextAreaElement | null) => void).call(
            event.target,
            textareaRef.value,
        );
    }

    // FIX: Completely exit if we are in the Normal editor
    if (!showHeader.value) {
        return;
    }

    // --- Autocomplete Trigger ---
    const textarea = textareaRef.value;
    const jq = (window as unknown as WindowWithJQuery).$;
    if (textarea && jq && jq.fn.autocomplete) {
        const e = event as KeyboardEvent;
        const caret = textarea.selectionStart;
        const text = textarea.value.substring(0, caret);

        const isTypingNumber = e.key.length === 1 && /[0-9]/.test(e.key);
        const isHittingBackspace = e.key === 'Backspace';
        const isAttachedToHash = /#\d*$/.test(text);

        if (e.key === '#' || (isAttachedToHash && (isTypingNumber || isHittingBackspace))) {
            jq(textarea).autocomplete('search', '');
        }
        else if (e.key === ' ' || e.key === 'Escape' || !isAttachedToHash) {
            jq(textarea).autocomplete('close');
        }
    }
}

function handleKeydown(event: Event) {
    emit('keydown', event);

    // Call global function if specified
    if (
        props.textareaOnkeydown
        && window[props.textareaOnkeydown as keyof Window]
    ) {
        (window[props.textareaOnkeydown as keyof Window] as (el: HTMLTextAreaElement | null) => void).call(
            event.target,
            textareaRef.value,
        );
    }
}

function handlePaste(event: Event) {
    emit('paste', event);

    // Call global function if specified
    if (
        props.textareaOnPaste
        && window[props.textareaOnPaste as keyof Window]
    ) {
        (window[props.textareaOnPaste as keyof Window] as (el: HTMLTextAreaElement | null) => void).call(
            event.target,
            textareaRef.value,
        );
    }
}

function handleChange(event: Event) {
    emit('change', event);

    // Call global function if specified
    if (
        props.textareaOnChange
        && window[props.textareaOnChange as keyof Window]
    ) {
        (window[props.textareaOnChange as keyof Window] as (el: HTMLTextAreaElement | null) => void).call(
            event.target,
            textareaRef.value,
        );
    }
}

function handleInput(event: Event) {
    if (
        props.textareaOnInput
        && window[props.textareaOnInput as keyof Window]
    ) {
        (window[props.textareaOnInput as keyof Window] as (el: HTMLTextAreaElement | null) => void).call(
            event.target,
            textareaRef.value,
        );
    }
}

onMounted(() => {
    if (props.initializePreview) {
        setMode('preview');
    }
});

const showHeader = ref(!!props.renderHeader);
function toggleMarkdown() {
    if (props.markdownStatusId) {
        const markdownStatusElement = document.getElementById(props.markdownStatusId) as HTMLInputElement;
        if (markdownStatusElement) {
            markdownStatusElement.value = +markdownStatusElement.value ? '0' : '1';
            syncMarkdownToggle();
        }
    }
}

watch(showHeader, (isMarkdownEnabled) => {
    const jq = (window as unknown as WindowWithJQuery).$;
    if (textareaRef.value && jq && jq.fn.autocomplete) {
        jq(textareaRef.value).autocomplete(isMarkdownEnabled ? 'enable' : 'disable');
    }
});

function syncMarkdownToggle() {
    if (props.markdownStatusId) {
        const markdownStatusElement = document.getElementById(props.markdownStatusId) as HTMLInputElement;
        if (markdownStatusElement) {
            const status = markdownStatusElement.value === '1';
            showHeader.value = status;
        }
    }
}

function normalizeThreadTitle(rawTitle: string, id: number): string {
    let title = rawTitle.trim();
    // Strip common prefixed id formats from list data to avoid duplicate id display.
    title = title.replace(new RegExp(`^\\(${id}\\)\\s*`), '');
    title = title.replace(new RegExp(`^#${id}\\s*`), '');
    return title.trim();
}

function parseThreadListEntry(entry: { id?: number | string; title?: string } | string): { id: number; title: string } | null {
    if (typeof entry === 'string') {
        const idMatch = entry.match(/#(\d+)/);
        if (!idMatch) {
            return null;
        }
        const id = parseInt(idMatch[1] ?? '', 10);
        if (Number.isNaN(id) || id <= 0) {
            return null;
        }
        const title = normalizeThreadTitle(entry.replace(/<\s*#\d+\s*>/g, ''), id);
        return { id, title };
    }

    const parsedId = parseInt(String(entry.id ?? ''), 10);
    if (Number.isNaN(parsedId) || parsedId <= 0) {
        return null;
    }
    return {
        id: parsedId,
        title: normalizeThreadTitle(String(entry.title ?? ''), parsedId),
    };
}

function getThreadSource(): Array<{ label: string; value: string }> {
    const normalizedThreads: Array<{ id: number; title: string }> = [];
    const globalList = (window as Window).thread_list;

    if (Array.isArray(globalList)) {
        for (const entry of globalList) {
            const parsed = parseThreadListEntry(entry);
            if (parsed !== null) {
                normalizedThreads.push(parsed);
            }
        }
    }

    // Always merge current sidebar threads so new threads show without refresh.
    const threadLinks = document.querySelectorAll<HTMLAnchorElement>('.thread_box_link[data-thread_id]');
    threadLinks.forEach((link) => {
        const id = parseInt(link.dataset.thread_id ?? '', 10);
        if (!Number.isNaN(id) && id > 0) {
            normalizedThreads.push({
                id,
                title: normalizeThreadTitle(link.dataset.thread_title ?? '', id),
            });
        }
    });

    const seen = new Set<number>();
    return normalizedThreads
        .filter((thread) => {
            if (seen.has(thread.id)) {
                return false;
            }
            seen.add(thread.id);
            return true;
        })
        .map((thread) => ({
            label: `#${thread.id} ${thread.title}`.trim(),
            value: `#${thread.id}`,
        }));
}

function destroyAutocomplete(): void {
    const jq = (window as unknown as WindowWithJQuery).$;
    const textarea = textareaRef.value;
    if (!jq || !jq.fn.autocomplete || !textarea) {
        return;
    }
    if (jq(textarea).data('ui-autocomplete')) {
        jq(textarea).autocomplete('destroy');
    }
}

function initializeAutocomplete(): void {
    const jq = (window as unknown as WindowWithJQuery).$;
    const textarea = textareaRef.value;
    if (!jq || !jq.fn.autocomplete || !textarea) {
        return;
    }

    destroyAutocomplete();
    jq(textarea).autocomplete({
        appendTo: jq(textarea).parent(),
        source: function (_request: unknown, response: (items: Array<{ label: string; value: string }>) => void) {
            if (!showHeader.value) {
                response([]);
                return;
            }
            const localTextarea = textareaRef.value;
            if (!localTextarea) {
                response([]);
                return;
            }

            const caret = localTextarea.selectionStart;
            const textToCaret = localTextarea.value.substring(0, caret);
            const match = textToCaret.match(/#(\d*)$/);
            if (!match) {
                response([]);
                return;
            }

            const term = match[1] || '';
            const filtered = getThreadSource().filter((item) =>
                item.value.startsWith(`#${term}`) || item.label.toLowerCase().includes(term.toLowerCase()),
            );
            response(filtered);
        },
        minLength: 0,
        position: { my: 'left top', at: 'left bottom+5' },
        select: function (this: unknown, _event: Event, ui: { item: { value: string } }) {
            const localTextarea = textareaRef.value;
            if (!localTextarea) {
                return false;
            }

            const caret = localTextarea.selectionStart;
            const textToCaret = localTextarea.value.substring(0, caret);
            const lastHashIndex = textToCaret.lastIndexOf('#');

            if (lastHashIndex !== -1) {
                const before = localTextarea.value.substring(0, lastHashIndex);
                const after = localTextarea.value.substring(caret);
                localTextarea.value = `${before + ui.item.value} ${after}`;

                const newPos = before.length + ui.item.value.length + 1;
                localTextarea.setSelectionRange(newPos, newPos);
                localTextarea.focus();
                localTextarea.dispatchEvent(new Event('input', { bubbles: true }));
            }

            jq(this).autocomplete('close');
            return false;
        },
    });
    jq(textarea).autocomplete(showHeader.value ? 'enable' : 'disable');
}

onMounted(() => {
    syncMarkdownToggle();
    initializeAutocomplete();
});

watch(textareaRef, () => {
    void nextTick(() => {
        initializeAutocomplete();
    });
});

onBeforeUnmount(() => {
    destroyAutocomplete();
});
</script>

<template>
  <div
    v-if="markdownStatusId"
    class="button-row"
  >
    <div
      :id="toggleButtonId"
      role="button"
      class="markdown-toggle key_to_click"
      :class="{ 'markdown-active': showHeader, 'markdown-inactive': !showHeader }"
      tabindex="0"
      title="Render markdown"
      @click="toggleMarkdown"
    >
      <i class="fab fa-markdown fa-2x" />
    </div>
    <a
      target="_blank"
      href="https://submitty.org/student/discussion_forum#formatting-a-post-using-markdown/"
      aria-label="Markdown Info"
    ><i
      style="font-style:normal;"
      class="far fa-question-circle disabled"
    /></a>
  </div>
  <div
    :class="[rootClass]"
    class="markdown-area fill-available"
    @click="syncMarkdownToggle"
  >
    <div
      v-if="showHeader"
      :id="markdownHeaderId ?? undefined"
      class="markdown-area-header"
      :data-mode="mode"
    >
      <div
        class="markdown-mode-buttons"
      >
        <button
          title="Edit Markdown"
          type="button"
          class="markdown-mode-tab markdown-write-mode"
          :class="{ active: mode === 'edit' }"
          data-testid="markdown-mode-tab-write"
          @click="setMode('edit')"
        >
          Write
        </button>
        <button
          title="Preview Markdown"
          type="button"
          class="markdown-mode-tab markdown-preview-mode"
          :class="{ active: mode === 'preview' }"
          tabindex="0"
          :data-initialize-preview="initializePreview"
          data-testid="markdown-mode-tab-preview"
          @click="setMode('preview')"
        >
          Preview
        </button>
      </div>
      <div
        v-if="mode === 'edit'"
        class="markdown-area-toolbar"
      >
        <a
          target="_blank"
          href="https://submitty.org/student/communication/markdown"
        >
          <i
            style="font-style: normal"
            class="fa-question-circle"
          />
        </a>
        <button
          type="button"
          title="Insert a link"
          class="btn btn-default btn-markdown btn-markdown-link"
          tabindex="0"
          @click="addMarkdown('link')"
        >
          Link <i class="fas fa-link fa-1x" />
        </button>
        <button
          title="Insert a code segment"
          type="button"
          class="btn btn-default btn-markdown btn-markdown-code"
          tabindex="0"
          @click="addMarkdown('code')"
        >
          Code <i class="fas fa-code fa-1x" />
        </button>
        <button
          title="Insert bold text"
          type="button"
          class="btn btn-default btn-markdown btn-markdown-bold"
          tabindex="0"
          @click="addMarkdown('bold')"
        >
          Bold <i class="fas fa-bold fa-1x" />
        </button>
        <button
          title="Insert italic text"
          type="button"
          class="btn btn-default btn-markdown btn-markdown-italic"
          tabindex="0"
          @click="addMarkdown('italic')"
        >
          Italics <i class="fas fa-italic fa-1x" />
        </button>
        <button
          title="Insert blockquote text"
          type="button"
          class="btn btn-default btn-markdown btn-markdown-blockquote"
          tabindex="0"
          @click="addMarkdown('blockquote')"
        >
          Blockquote <i class="fas fa-quote-left fa-1x" />
        </button>
      </div>
    </div>
    <div class="markdown-area-body">
      <div
        v-if="isPreviewLoading || isLoadingPreview"
        class="markdown-preview-load-spinner"
      />
      <label
        :for="markdownAreaId"
        tabindex="-1"
        class="screen-reader"
      >{{ markdownAreaId }}</label>
      <div style="position:relative;">
        <textarea
          v-show="mode === 'edit'"
          :id="markdownAreaId"
          ref="textareaRef"
          v-model="content"
          :data-testid="markdownAreaId"
          class="markdown-textarea fill-available"
          :class="[props.class]"
          :name="markdownAreaName"
          :placeholder="placeholder"
          rows="10"
          cols="30"
          :maxlength="maxLength"
          :required="required"
          :data-previous-comment="dataPreviousComment"
          @keyup="handleKeyup"
          @keydown="handleKeydown"
          @paste="handlePaste"
          @change="handleChange"
          @input="handleInput"
        />
        <!-- jQuery UI autocomplete handles the dropdown, nothing to render here -->
      </div>
      <div
        v-if="mode === 'preview'"
        :id="previewDivId ?? undefined"
        class="fill-available markdown-preview"
        :style="previewStyle"
      >
        <Markdown :content="content" />
      </div>
    </div>
  </div>
</template>
<style>
.markdown-area .ui-autocomplete.ui-widget-content {
  position: absolute;
  max-height: 250px;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 0;
  margin: 0;
    background-color: rgb(255 255 255);
    border: 1px solid rgb(192 192 192);
  border-radius: 4px;
    box-shadow: 0 4px 12px rgb(0 0 0 / 15%);
    z-index: 1000;
  list-style: none;
}

.markdown-area .ui-autocomplete .ui-menu-item-wrapper {
  padding: 8px 12px;
  cursor: pointer;
  font-size: 14px;
    color: rgb(51 51 51);
  transition: background-color 0.1s ease;
}

.markdown-area .ui-autocomplete .ui-menu-item-wrapper:hover,
.markdown-area .ui-autocomplete .ui-state-active {
    background-color: rgb(0 123 255);
    color: rgb(255 255 255);
    border: none;
    margin: 0;
}

[data-theme="dark"] .markdown-area .ui-autocomplete.ui-widget-content {
    background-color: rgb(43 43 43);
    border: 1px solid rgb(68 68 68);
    box-shadow: 0 4px 12px rgb(0 0 0 / 50%);
}

[data-theme="dark"] .markdown-area .ui-autocomplete .ui-menu-item-wrapper {
    color: rgb(224 224 224);
}

[data-theme="dark"] .markdown-area .ui-autocomplete .ui-menu-item-wrapper:hover,
[data-theme="dark"] .markdown-area .ui-autocomplete .ui-state-active {
    background-color: rgb(64 64 64);
    color: rgb(255 255 255);
}
</style>

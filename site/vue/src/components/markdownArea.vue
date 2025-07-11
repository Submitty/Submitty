<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';

interface Props {
    markdownAreaId: string;
    markdownAreaValue: string;
    customClass?: string;
    dataPreviousComment?: string;
    initializePreview?: boolean;
    markdownAreaName?: string;
    markdownHeaderId?: string | null;
    maxHeight?: string;
    minHeight?: string;
    noMaxlength?: boolean;
    placeholder?: string;
    previewDivId?: string | null;
    renderHeader?: boolean;
    rootClass?: string;
    textareaMaxlength?: string | number;
    required?: boolean;
    isPreviewLoading?: boolean;
    onKeyupCallback?: string;
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

async function setMode(newMode: 'edit' | 'preview') {
    mode.value = newMode;
    if (newMode === 'preview') {
        emit('preview', content.value ?? '');
        await fetchPreviewContent();
    }
}

const previewContent = ref('');

async function fetchPreviewContent() {
    if (!content.value) {
        previewContent.value = '';
        return;
    }

    isLoadingPreview.value = true;
    try {
        const response = await fetch(window.buildUrl(['markdown']), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                content: content.value,
                csrf_token: window.csrfToken,
            }),
        });

        if (response.ok) {
            const html = await response.text();
            previewContent.value = html;
        }
        else {
            console.error('Failed to fetch markdown preview');
            previewContent.value = 'Error loading preview';
        }
    }
    catch (error) {
        console.error('Error fetching markdown preview:', error);
        previewContent.value = 'Error loading preview';
    }
    finally {
        isLoadingPreview.value = false;
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
    console.log(props.onKeyupCallback);

    // Call global function if specified
    if (
        props.onKeyupCallback
        && window[props.onKeyupCallback as keyof Window]
    ) {
        console.log(`Calling global function: ${props.onKeyupCallback}`);
        (window[props.onKeyupCallback as keyof Window] as () => void).call(
            event.target,
        );
    }
}

onMounted(async () => {
    if (props.initializePreview) {
        await setMode('preview');
    }
});
</script>

<template>
  <div
    class="markdown-area fill-available"
    :class="[rootClass]"
  >
    <div
      v-if="renderHeader"
      :id="markdownHeaderId ?? undefined"
      class="markdown-area-header"
      :data-mode="mode"
    >
      <div class="markdown-mode-buttons">
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
      <div class="markdown-area-toolbar">
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
      >
        <div />
        <div />
        <div />
        <div />
      </div>
      <label
        :for="markdownAreaId"
        tabindex="-1"
        class="screen-reader"
      >{{ markdownAreaId }}</label>
      <textarea
        v-show="mode === 'edit'"
        :id="markdownAreaId"
        ref="textareaRef"
        v-model="content"
        :data-testid="markdownAreaId"
        class="markdown-textarea fill-available"
        :class="[customClass]"
        :name="markdownAreaName"
        :placeholder="placeholder"
        rows="10"
        cols="30"
        :maxlength="maxLength"
        :required="required"
        :data-previous-comment="dataPreviousComment"
        @change="$emit('change', $event)"
        @keyup="handleKeyup"
        @paste="$emit('paste', $event)"
        @keydown="$emit('keydown', $event)"
      />
      <!-- eslint-disable vue/no-v-html -->
      <div
        v-show="mode === 'preview'"
        :id="previewDivId ?? undefined"
        class="fill-available markdown-preview"
        :style="previewStyle"
        v-html="previewContent"
      />
    </div>
  </div>
</template>

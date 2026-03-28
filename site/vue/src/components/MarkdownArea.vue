<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import Markdown from './Markdown.vue';
import { buildCourseUrl } from '../../../ts/utils/server';

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
    if (
        props.textareaOnKeyup
        && window[props.textareaOnKeyup as keyof Window]
    ) {
        (window[props.textareaOnKeyup as keyof Window] as (el: HTMLTextAreaElement | null) => void).call(
            event.target,
            textareaRef.value,
        );
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

function syncMarkdownToggle() {
    if (props.markdownStatusId) {
        const markdownStatusElement = document.getElementById(props.markdownStatusId) as HTMLInputElement;
        if (markdownStatusElement) {
            const status = markdownStatusElement.value === '1';
            showHeader.value = status;
        }
    }
}

// Thread autocomplete state
interface ThreadSuggestion {
    id: number;
    title: string;
}
const showAutocomplete = ref(false);
const autocompleteItems = ref<ThreadSuggestion[]>([]);
const autocompleteIndex = ref(0);
const autocompleteQuery = ref('');
const autocompleteStartPos = ref(0);
const autocompleteDropdownRef = ref<HTMLDivElement | null>(null);
let autocompleteDebounce: ReturnType<typeof setTimeout> | null = null;

async function fetchThreadSuggestions(query: string): Promise<void> {
    try {
        const url = `${buildCourseUrl(['forum', 'threads', 'search'])}?q=${encodeURIComponent(query)}`;
        const response = await fetch(url);
        const json = await response.json();
        if (json.status === 'success') {
            autocompleteItems.value = json.data as ThreadSuggestion[];
        }
    }
    catch {
        autocompleteItems.value = [];
    }
}

function checkAutocomplete(): void {
    console.log('checkAutocomplete called');
    // Only show autocomplete in markdown mode (edit mode)    
    if (mode.value !== 'edit' || !showHeader.value) {
      closeAutocomplete();
      return;
    }
    const textarea = textareaRef.value;
    if (!textarea) {
      return;
    }
    const cursorPos = textarea.selectionStart;
    const text = textarea.value.substring(0, cursorPos);

    // Find # that triggers autocomplete: must be at start of text or preceded by whitespace/punctuation
    const match = /(?:^|[\s(])#(\d*)$/.exec(text);
    if (match) {
      autocompleteStartPos.value = cursorPos - match[1].length - 1; // position of #
      autocompleteQuery.value = match[1];
      autocompleteIndex.value = 0;
      showAutocomplete.value = true;

      if (autocompleteDebounce) {
        clearTimeout(autocompleteDebounce);
      }
      autocompleteDebounce = setTimeout(() => {
        fetchThreadSuggestions(match[1]);
      }, 200);
    }
    else {
      closeAutocomplete();
    }
}

function closeAutocomplete(): void {
    showAutocomplete.value = false;
    autocompleteItems.value = [];
    autocompleteQuery.value = '';
    if (autocompleteDebounce) {
        clearTimeout(autocompleteDebounce);
        autocompleteDebounce = null;
    }
}

function selectAutocompleteSuggestion(suggestion: ThreadSuggestion): void {
    const textarea = textareaRef.value;
    if (!textarea) {
        return;
    }
    const before = textarea.value.substring(0, autocompleteStartPos.value);
    const after = textarea.value.substring(textarea.selectionStart);
    const insertion = `#${suggestion.id} `;
    content.value = before + insertion + after;
    closeAutocomplete();

    nextTick(() => {
        const newPos = before.length + insertion.length;
        textarea.focus();
        textarea.setSelectionRange(newPos, newPos);
    });
}

function handleAutocompleteKeydown(event: KeyboardEvent): void {
    if (!showAutocomplete.value || autocompleteItems.value.length === 0) {
        return;
    }
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        autocompleteIndex.value = (autocompleteIndex.value + 1) % autocompleteItems.value.length;
    }
    else if (event.key === 'ArrowUp') {
        event.preventDefault();
        autocompleteIndex.value = (autocompleteIndex.value - 1 + autocompleteItems.value.length) % autocompleteItems.value.length;
    }
    else if (event.key === 'Enter' || event.key === 'Tab') {
        event.preventDefault();
        selectAutocompleteSuggestion(autocompleteItems.value[autocompleteIndex.value]);
    }
    else if (event.key === 'Escape') {
        event.preventDefault();
        closeAutocomplete();
    }
}

function handleDocumentClick(event: MouseEvent): void {
    if (autocompleteDropdownRef.value && !autocompleteDropdownRef.value.contains(event.target as Node)) {
        closeAutocomplete();
    }
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    if (autocompleteDebounce) {
        clearTimeout(autocompleteDebounce);
    }
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
      <div class="thread-autocomplete-wrapper">
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
          @keydown="(e: KeyboardEvent) => { handleAutocompleteKeydown(e); handleKeydown(e); }"
          @paste="handlePaste"
          @change="handleChange"
          @input="(e: Event) => { checkAutocomplete(); handleInput(e); }"
        />
        <div
          v-if="showAutocomplete && autocompleteItems.length > 0"
          ref="autocompleteDropdownRef"
          class="thread-autocomplete-dropdown"
          data-testid="thread-autocomplete-dropdown"
        >
          <div
            v-for="(item, index) in autocompleteItems"
            :key="item.id"
            class="thread-autocomplete-item"
            :class="{ 'thread-autocomplete-item-active': index === autocompleteIndex }"
            :data-testid="`thread-autocomplete-item-${item.id}`"
            @mousedown.prevent="selectAutocompleteSuggestion(item)"
            @mouseenter="autocompleteIndex = index"
          >
            <span class="thread-autocomplete-id">#{{ item.id }}</span>
            <span class="thread-autocomplete-title">{{ item.title }}</span>
          </div>
        </div>
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
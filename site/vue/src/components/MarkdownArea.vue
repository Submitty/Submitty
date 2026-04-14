<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import Markdown from './Markdown.vue';

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
const kebabMenuRef = ref<HTMLElement | null>(null);
const kebabButtonRef = ref<HTMLButtonElement | null>(null);
const toolbarRef = ref<HTMLElement | null>(null);
const mode = ref('edit');
const content = ref(props.markdownAreaValue);
const isLoadingPreview = ref(false);
const toolbarWidth = ref(Number.POSITIVE_INFINITY);
let toolbarResizeObserver: ResizeObserver | null = null;

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
const isKebabOpen = ref(false);

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

function handleMarkdownAction(type: string) {
    addMarkdown(type);
  closeKebabMenu(true);
}

const kebabActions = computed(() => {
  const actions = new Set<string>();

  // Move right-most actions into kebab progressively as width shrinks.
  if (toolbarWidth.value <= 740) {
    actions.add('blockquote');
  }
  if (toolbarWidth.value <= 680) {
    actions.add('italic');
  }
  if (toolbarWidth.value <= 620) {
    actions.add('bold');
  }
  if (toolbarWidth.value <= 560) {
    actions.add('code');
  }
  if (toolbarWidth.value <= 500) {
    actions.add('link');
  }

  return actions;
});

const hasKebabActions = computed(() => kebabActions.value.size > 0);

function isActionInToolbar(action: string) {
  return !kebabActions.value.has(action);
}

function isActionInKebab(action: string) {
  return kebabActions.value.has(action);
}

function getKebabItems() {
  if (!kebabMenuRef.value) {
    return [] as HTMLButtonElement[];
  }
  return Array.from(kebabMenuRef.value.querySelectorAll('.kebab-item')) as HTMLButtonElement[];
}

function focusKebabItem(index: number) {
  const items = getKebabItems();
  if (!items.length) {
    return;
  }
  const boundedIndex = Math.max(0, Math.min(index, items.length - 1));
  const item = items[boundedIndex];
  if (item) {
    item.focus();
  }
}

function openKebabMenu(focusFirst = false) {
  isKebabOpen.value = true;
  if (focusFirst) {
    requestAnimationFrame(() => {
      focusKebabItem(0);
    });
  }
}

function closeKebabMenu(focusButton = false) {
  isKebabOpen.value = false;
  if (focusButton) {
    requestAnimationFrame(() => {
      kebabButtonRef.value?.focus();
    });
  }
}

function toggleKebabMenu(event: Event) {
  event.stopPropagation();
  if (isKebabOpen.value) {
    closeKebabMenu();
  }
  else {
    openKebabMenu(false);
  }
}

function handleKebabButtonKeydown(event: KeyboardEvent) {
  if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    openKebabMenu(true);
  }
  else if (event.key === 'Escape') {
    event.preventDefault();
    closeKebabMenu(true);
  }
}

function handleKebabMenuKeydown(event: KeyboardEvent) {
  if (!isKebabOpen.value) {
    return;
  }

  const items = getKebabItems();
  if (!items.length) {
    return;
  }

  const currentIndex = items.findIndex((item) => item === document.activeElement);

  if (event.key === 'Escape') {
    event.preventDefault();
    closeKebabMenu(true);
    return;
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault();
    const nextIndex = currentIndex < 0 ? 0 : (currentIndex + 1) % items.length;
    focusKebabItem(nextIndex);
    return;
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault();
    const prevIndex = currentIndex < 0 ? items.length - 1 : (currentIndex - 1 + items.length) % items.length;
    focusKebabItem(prevIndex);
    return;
  }

  if (event.key === 'Home') {
    event.preventDefault();
    focusKebabItem(0);
    return;
  }

  if (event.key === 'End') {
    event.preventDefault();
    focusKebabItem(items.length - 1);
  }
}

function closeKebabOnOutsideClick(event: Event) {
  if (!isKebabOpen.value) {
    return;
  }

  const target = event.target as Node | null;
  if (kebabMenuRef.value && target && !kebabMenuRef.value.contains(target)) {
    closeKebabMenu();
  }
}

function closeKebabOnEscape(event: KeyboardEvent) {
  if (event.key === 'Escape' && isKebabOpen.value) {
    closeKebabMenu(true);
  }
}

onMounted(() => {
  document.addEventListener('click', closeKebabOnOutsideClick);
  document.addEventListener('keydown', closeKebabOnEscape);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', closeKebabOnOutsideClick);
  document.removeEventListener('keydown', closeKebabOnEscape);
  toolbarResizeObserver?.disconnect();
  toolbarResizeObserver = null;
});

watch(hasKebabActions, (hasItems) => {
  if (!hasItems && isKebabOpen.value) {
    closeKebabMenu(false);
  }
});

onMounted(() => {
  if (!toolbarRef.value) {
    return;
  }

  const updateToolbarWidth = () => {
    if (toolbarRef.value) {
      toolbarWidth.value = toolbarRef.value.clientWidth;
    }
  };

  updateToolbarWidth();
  toolbarResizeObserver = new ResizeObserver(updateToolbarWidth);
  toolbarResizeObserver.observe(toolbarRef.value);
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

      <div
        v-if="mode === 'edit'"
        ref="toolbarRef"
        class="markdown-area-toolbar"
      >
        <a
          target="_blank"
          href="https://submitty.org/student/communication/markdown"
          class="markdown-help-icon"
        >
          <i
            style="font-style: normal"
            class="fa-question-circle"
          />
        </a>
        <button
          v-if="isActionInToolbar('link')"
          type="button"
          title="Insert a link"
          class="btn btn-default btn-markdown btn-markdown-link"
          tabindex="0"
          @click="addMarkdown('link')"
        >
          <span class="md-btn-text">Link </span><i class="fas fa-link fa-1x" />
        </button>
        <button
          v-if="isActionInToolbar('code')"
          type="button"
          title="Insert a code segment"
          class="btn btn-default btn-markdown btn-markdown-code"
          tabindex="0"
          @click="addMarkdown('code')"
        >
          <span class="md-btn-text">Code </span><i class="fas fa-code fa-1x" />
        </button>
        <button
          v-if="isActionInToolbar('bold')"
          type="button"
          title="Insert bold text"
          class="btn btn-default btn-markdown btn-markdown-bold"
          tabindex="0"
          @click="addMarkdown('bold')"
        >
          <span class="md-btn-text">Bold </span><i class="fas fa-bold fa-1x" />
        </button>
        <button
          v-if="isActionInToolbar('italic')"
          type="button"
          title="Insert italic text"
          class="btn btn-default btn-markdown btn-markdown-italic"
          tabindex="0"
          @click="addMarkdown('italic')"
        >
          <span class="md-btn-text">Italics </span><i class="fas fa-italic fa-1x" />
        </button>
        <button
          v-if="isActionInToolbar('blockquote')"
          type="button"
          title="Insert blockquote text"
          class="btn btn-default btn-markdown btn-markdown-blockquote"
          tabindex="0"
          @click="addMarkdown('blockquote')"
        >
          <span class="md-btn-text">Blockquote </span><i class="fas fa-quote-left fa-1x" />
        </button>
        
        <!-- Kebab menu for narrow screens -->
        <div
          v-if="hasKebabActions"
          ref="kebabMenuRef"
          class="markdown-kebab-menu"
        >
          <button
            ref="kebabButtonRef"
            type="button"
            class="btn btn-default btn-kebab"
            tabindex="0"
            title="More options"
            @click="toggleKebabMenu"
            @keydown="handleKebabButtonKeydown"
          >
            <i class="fas fa-ellipsis-v" />
          </button>
          <div
            v-if="isKebabOpen"
            class="kebab-dropdown"
            @click.stop
            @keydown="handleKebabMenuKeydown"
          >
            <button
              v-if="isActionInKebab('bold')"
              type="button"
              class="kebab-item"
              tabindex="0"
              @click="handleMarkdownAction('bold')"
            >
              <i class="fas fa-bold" /> Bold
            </button>
            <button
              v-if="isActionInKebab('italic')"
              type="button"
              class="kebab-item"
              tabindex="0"
              @click="handleMarkdownAction('italic')"
            >
              <i class="fas fa-italic" /> Italic
            </button>
            <button
              v-if="isActionInKebab('blockquote')"
              type="button"
              class="kebab-item"
              tabindex="0"
              @click="handleMarkdownAction('blockquote')"
            >
              <i class="fas fa-quote-left" /> Quote
            </button>
            <button
              v-if="isActionInKebab('code')"
              type="button"
              class="kebab-item"
              tabindex="0"
              @click="handleMarkdownAction('code')"
            >
              <i class="fas fa-code" /> Code
            </button>
            <button
              v-if="isActionInKebab('link')"
              type="button"
              class="kebab-item"
              tabindex="0"
              @click="handleMarkdownAction('link')"
            >
              <i class="fas fa-link" /> Link
            </button>
          </div>
        </div>
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
.markdown-area {
  container-type: inline-size;
  container-name: markdownarea;
  width: 100%;
}

.markdown-area-header {
  display: flex;
  justify-content: space-between; 
  align-items: flex-end; 
  flex-wrap: nowrap; 
  width: 100%;
  overflow: visible;
  gap: 8px;
}

.markdown-mode-buttons {
  display: inline-flex;
  flex-wrap: nowrap;
  flex: 0 0 auto;
  min-width: max-content;
}

.markdown-mode-tab {
  white-space: nowrap;
  flex: 0 0 auto;
}

.markdown-area-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: nowrap;
  justify-content: flex-end;
  flex: 1 1 auto;
  min-width: 0;
  overflow: visible;
  gap: 4px;
  padding-bottom: 2px;
}

.markdown-help-icon {
  margin-right: 4px;
}

.markdown-kebab-menu {
  position: relative;
  display: inline-flex;
  align-items: center;
  flex: 0 0 auto;
}

.btn-kebab {
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #555 !important;
  border-radius: 4px !important;
  background: #3a3a3a !important;
  color: #e8e8e8 !important;
  padding: 0 !important;
}

.btn-kebab:hover,
.btn-kebab:focus {
  background: #4b4b4b !important;
  border-color: #666 !important;
}

.kebab-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  background: #3a3a3a;
  border: 1px solid #555;
  border-radius: 4px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.16);
  z-index: 1000;
  min-width: 200px;
  padding: 4px;
}

.kebab-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 38px;
  padding: 8px 10px;
  background: none;
  border: 1px solid transparent;
  border-radius: 3px;
  cursor: pointer;
  text-align: left;
  font-size: 0.95rem;
  color: #e8e8e8;
  transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
}

.kebab-item:hover {
  background-color: #4b4b4b;
}

.kebab-item i {
  width: 16px;
  text-align: center;
  color: #d0d0d0;
}

.kebab-item:focus-visible {
  border-color: #337ab7;
  outline: 2px solid #337ab7;
  outline-offset: 0;
}

@container markdownarea (max-width: 650px) {
  .markdown-help-icon {
    display: none;
  }
}

@container markdownarea (max-width: 300px) {
  .markdown-help-icon {
    display: none;
    margin-right: 0;
  }
}
</style>
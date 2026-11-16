<script setup lang="ts">
import { ref, watch } from 'vue';
import Autocomplete from './Autocomplete.vue';

interface AutocompleteItem {
    label: string;
    value: string;
}

interface Props {
    /* Reference to the textarea element */
    textareaRef: HTMLTextAreaElement | null;
    /* Whether thread autocomplete is enabled */
    enabled: boolean;
}

interface Emits {
    /* Emitted when a thread is selected and inserted */
    'thread-inserted': [value: string];
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// State management
const isOpen = ref(false);
const filteredItems = ref<AutocompleteItem[]>([]);

// Collect all available threads
type ThreadListEntry = { id?: number | string; title?: string } | string;

function getThreadSource(): AutocompleteItem[] {
    const normalizedThreads: Array<{ id: number; title: string }> = [];

    // Get threads from global list (server-provided)
    const globalList = (window as Window & { thread_list?: ThreadListEntry[] }).thread_list;
    if (Array.isArray(globalList)) {
        for (const entry of globalList) {
            const parsed = parseThreadListEntry(entry);
            if (parsed !== null) {
                normalizedThreads.push(parsed);
            }
        }
    }

    // Merge with threads from sidebar (always fresh from DOM)
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

    // Remove duplicates
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

function normalizeThreadTitle(rawTitle: string, id: number): string {
    let title = rawTitle.trim();
    // Strip common prefixed id formats
    title = title.replace(new RegExp(`^\\(${id}\\)\\s*`), '');
    title = title.replace(new RegExp(`^#${id}\\s*`), '');
    return title.trim();
}

// Handles both string format "#123 Title" and object format { id: 123, title: "Title" }

function parseThreadListEntry(
    entry: { id?: number | string; title?: string } | string,
): { id: number; title: string } | null {
    // Handle string format (e.g., "#123 Thread Title" or "<#123>")
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

    // Handle object format
    const parsedId = parseInt(String(entry.id ?? ''), 10);
    if (Number.isNaN(parsedId) || parsedId <= 0) {
        return null;
    }
    return {
        id: parsedId,
        title: normalizeThreadTitle(String(entry.title ?? ''), parsedId),
    };
}

// Matches threads based on typed number after "#"

function performSearch(): void {
    if (!props.textareaRef) {
        filteredItems.value = [];
        return;
    }

    const caret = props.textareaRef.selectionStart;
    const textToCaret = props.textareaRef.value.substring(0, caret);

    // Match pattern: "#" followed by optional digits
    const match = textToCaret.match(/#(\d*)$/);
    if (!match) {
        filteredItems.value = [];
        return;
    }

    // Get the typed number (could be empty if just typed "#")
    const term = match[1] || '';
    const allThreads = getThreadSource();

    // Filter: show threads that match the typed number or title
    filteredItems.value = allThreads.filter(
        (item) =>
            item.value.startsWith(`#${term}`)
            || item.label.toLowerCase().includes(term.toLowerCase()),
    );
}

// Key event handler for textarea input to trigger autocomplete logic

function handleTextareaKeyup(event: KeyboardEvent): void {
    if (!props.enabled || !props.textareaRef) {
        return;
    }

    const caret = props.textareaRef.selectionStart;
    const text = props.textareaRef.value.substring(0, caret);

    const isTypingNumber = event.key.length === 1 && /[0-9]/.test(event.key);
    const isHittingBackspace = event.key === 'Backspace';
    const isAttachedToHash = /#\d*$/.test(text);

    // Show dropdown when starting mention or typing numbers/backspace
    if (event.key === '#' || (isAttachedToHash && (isTypingNumber || isHittingBackspace))) {
        performSearch();
        isOpen.value = true;
    }
    // Hide dropdown when finishing mention or pressing escape
    else if (event.key === ' ' || event.key === 'Escape' || !isAttachedToHash) {
        isOpen.value = false;
    }
}

// put the selected thread into the textarea at the correct position, replacing the typed text

function handleThreadSelect(item: AutocompleteItem): void {
    const textarea = props.textareaRef;
    if (!textarea) {
        return;
    }

    const caret = textarea.selectionStart;
    const textToCaret = textarea.value.substring(0, caret);
    const lastHashIndex = textToCaret.lastIndexOf('#');

    if (lastHashIndex !== -1) {
        // Get text before "#" and after current cursor
        const before = textarea.value.substring(0, lastHashIndex);
        const after = textarea.value.substring(caret);

        // Insert selected value with trailing space
        textarea.value = `${before + item.value} ${after}`;

        // Position cursor after the inserted text and space
        const newPos = before.length + item.value.length + 1;
        textarea.setSelectionRange(newPos, newPos);
        textarea.focus();

        // Dispatch input event so parent knows textarea changed
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    isOpen.value = false;
    emit('thread-inserted', item.value);
}

// Close dropdown when autocomplete is disabled

watch(
    () => props.enabled,
    (enabled) => {
        if (!enabled) {
            isOpen.value = false;
        }
    },
);

// Expose methods for parent component to call
defineExpose({
    handleTextareaKeyup,
});
</script>

<template>
  <!-- autocomplete component handles this -->
  <Autocomplete
    data-testid="thread-autocomplete"
    model-value=""
    :items="filteredItems"
    :is-open="isOpen"
    @select="handleThreadSelect"
    @update:is-open="isOpen = $event"
  >
    <!-- Empty slot - trigger element (textarea) is managed by parent (MarkdownArea) -->
  </Autocomplete>
</template>

<style scoped>
    /* autocomplete component handles this */
</style>

<script setup lang="ts">
import { ref, onMounted } from 'vue';

interface ForumCategory {
    id: number;
    description: string;
    visibleDate: string | null;
    diff?: number;
}

interface Props {
    categories: ForumCategory[];
}

interface ForumWindow {
    selectedCategoryIds: typeof selectedCategoryIds;
    selectedThreadStatuses: typeof selectedThreadStatuses;
    selectedUnreadChecked: typeof unreadChecked;
    checkUnread: () => boolean;
    clearForumFilter: () => boolean;
    getFilterState: () => Record<string, string | number[]>;
    saveFilterState: () => void;
    updateClearFilterButton?: () => void;
    updateThreads?: (loadFirstPage: boolean, successCallback: unknown) => void;
    $: (el: HTMLElement) => { data: (key: string, value?: string) => unknown };
}

const props = defineProps<Props>();

const isVisibleCategory = (category: ForumCategory): boolean => {
    return category.visibleDate === null || (Number(category.diff ?? 0) >= 0);
};

// Reactive filter state
const selectedCategoryIds = ref<number[]>([]);
const selectedThreadStatuses = ref<number[]>([]);
const unreadChecked = ref(false);

const forumWindow = window as unknown as ForumWindow;

// TODO: Remove these window property assignments once forum.js is fully migrated to Vue.
// These are temporary bridges so the legacy jQuery code can read Vue's reactive state.
forumWindow.selectedCategoryIds = selectedCategoryIds;
forumWindow.selectedThreadStatuses = selectedThreadStatuses;
forumWindow.selectedUnreadChecked = unreadChecked;

// TODO: Remove this bridge once forum.js is fully migrated to Vue.
// Replaces the legacy checkUnread — reads from Vue state instead of DOM.
forumWindow.checkUnread = () => {
    const clearBtn = document.getElementById('clear_filter_button');
    if (unreadChecked.value) {
        if (clearBtn) {
            clearBtn.style.visibility = 'visible';
        }
        return true;
    }
    return false;
};

// TODO: Remove this bridge once forum.js is fully migrated to Vue.
// Vue owns clearForumFilter — resets all reactive state and DOM, then the caller
// (Twig's onclick or updateClearFilterButton) calls updateThreads after.
forumWindow.clearForumFilter = () => {
    // Reset unread
    if (unreadChecked.value) {
        unreadChecked.value = false;
        const checkbox = document.getElementById('unread') as HTMLInputElement | null;
        if (checkbox) {
            checkbox.checked = false;
        }
    }

    // Reset search input
    const searchInput = document.getElementById('search-content') as HTMLInputElement | null;
    if (searchInput) {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('change'));
    }

    // Reset DOM button classes and jQuery .data() cache for category/status buttons
    document.querySelectorAll('#thread_category button, #thread_status_select button').forEach((el) => {
        const btn = el as HTMLElement;
        btn.dataset.btnSelected = 'false';
        btn.classList.remove('filter-active');
        btn.classList.add('filter-inactive');
        const $btn = forumWindow.$(btn);
        if ($btn.data) {
            $btn.data('btn-selected', 'false');
        }
    });

    // Reset Vue reactive state
    selectedCategoryIds.value = [];
    selectedThreadStatuses.value = [];
    unreadChecked.value = false;

    // Hide clear button
    const clearBtn = document.getElementById('clear_filter_button');
    if (clearBtn) {
        clearBtn.style.visibility = 'hidden';
    }

    return false;
};

// TODO: Remove these bridges once forum.js is fully migrated to Vue.
// Vue owns saveFilterState/getFilterState — reads from reactive refs for filter
// state, then pushes to history for browser back/forward navigation support.
// The search input value is still read from DOM (owned by legacy Twig/jQuery).
forumWindow.getFilterState = () => {
    return {
        'categories': [...selectedCategoryIds.value],
        'thread-status': [...selectedThreadStatuses.value],
        'search-content': (document.getElementById('search-content') as HTMLInputElement)?.value ?? '',
    };
};

forumWindow.saveFilterState = () => {
    history.pushState(forumWindow.getFilterState(), '');
};

// Initialise state from DOM on mount
function readInitialStateFromDOM(): void {
    const cats: number[] = [];
    document.querySelectorAll('#thread_category button').forEach((btn) => {
        if ((btn as HTMLElement).dataset.btnSelected === 'true') {
            const id = parseInt((btn as HTMLElement).dataset.cat_id ?? '', 10);
            if (!isNaN(id)) {
                cats.push(id);
            }
        }
    });
    selectedCategoryIds.value = cats;

    const statuses: number[] = [];
    document.querySelectorAll('#thread_status_select button').forEach((btn) => {
        if ((btn as HTMLElement).dataset.btnSelected === 'true') {
            const id = parseInt((btn as HTMLElement).dataset.sel_id ?? '', 10);
            if (!isNaN(id)) {
                statuses.push(id);
            }
        }
    });
    selectedThreadStatuses.value = statuses;

    // Read initial unread checkbox state (was set by Twig earlier)
    const unreadEl = document.getElementById('unread') as HTMLInputElement | null;
    if (unreadEl) {
        unreadChecked.value = unreadEl.checked;
    }
}

onMounted(() => {
    readInitialStateFromDOM();
});

function toggleFilterButton(event: MouseEvent, catId?: number, statusSelId?: number): void {
    const btn = event.currentTarget as HTMLElement;
    const $btn = forumWindow.$(btn);
    const current = btn.dataset.btnSelected;

    if (current === 'true') {
        btn.dataset.btnSelected = 'false';
        btn.classList.remove('filter-active');
        btn.classList.add('filter-inactive');
        $btn.data('btn-selected', 'false');
        if (catId !== undefined) {
            selectedCategoryIds.value = selectedCategoryIds.value.filter((id) => id !== catId);
        }
        if (statusSelId !== undefined) {
            selectedThreadStatuses.value = selectedThreadStatuses.value.filter((id) => id !== statusSelId);
        }
    }
    else {
        btn.dataset.btnSelected = 'true';
        btn.classList.remove('filter-inactive');
        btn.classList.add('filter-active');
        $btn.data('btn-selected', 'true');
        if (catId !== undefined) {
            selectedCategoryIds.value = [...selectedCategoryIds.value, catId];
        }
        if (statusSelId !== undefined) {
            selectedThreadStatuses.value = [...selectedThreadStatuses.value, statusSelId];
        }
    }
    if (forumWindow.updateClearFilterButton) {
        forumWindow.updateClearFilterButton();
    }
    if (forumWindow.updateThreads) {
        forumWindow.updateThreads(true, forumWindow.saveFilterState);
    }
}

function toggleUnread(): void {
    unreadChecked.value = !unreadChecked.value;
    const checkbox = document.getElementById('unread') as HTMLInputElement | null;
    if (checkbox) {
        checkbox.checked = unreadChecked.value;
    }
    if (forumWindow.updateThreads) {
        forumWindow.updateThreads(true, forumWindow.saveFilterState);
    }
    if (forumWindow.checkUnread) {
        forumWindow.checkUnread();
    }
}
</script>

<template>
  <div
    id="forum_filter_bar"
    data-testid="forum-filter-bar"
  >
    <button
      id="filter_unread_btn"
      class="btn btn-sm btn-default inline-block"
      :class="[unreadChecked ? 'filter-active' : 'filter-inactive']"
      data-testid="filter-unread-label"
      @click="toggleUnread"
    >
      Unread Only
    </button>
    <input
      id="unread"
      name="unread"
      type="checkbox"
      data-ays-ignore="true"
      data-testid="filter-unread-checkbox"
      hidden
    />

    <div
      id="thread_category"
      aria-label="Select thread category"
      class="inline-block"
      data-ays-ignore="true"
      data-testid="thread-category-filter"
    >
      <button
        v-for="category in props.categories.filter(isVisibleCategory)"
        :id="`categoryid_${category.id}`"
        :key="category.id"
        :data-cat_id="String(category.id)"
        class="btn btn-sm filter-inactive"
        data-btn-selected="false"
        type="button"
        :data-testid="`thread-category-${category.id}`"
        @mousedown.prevent="toggleFilterButton($event, category.id, undefined)"
      >
        {{ category.description }}
      </button>
    </div>

    <div
      id="thread_status_select"
      aria-label="Select thread status"
      class="inline-block"
      data-ays-ignore="true"
      data-testid="thread-status-filter"
    >
      <button
        class="btn btn-sm btn-default inline-block filter-inactive"
        data-btn-selected="false"
        data-sel_id="0"
        type="button"
        data-testid="thread-status-comment"
        @mousedown.prevent="toggleFilterButton($event, undefined, 0)"
      >
        Comment
      </button>
      <button
        class="btn btn-sm btn-default inline-block filter-inactive"
        data-btn-selected="false"
        data-sel_id="-1"
        type="button"
        data-testid="thread-status-unresolved"
        @mousedown.prevent="toggleFilterButton($event, undefined, -1)"
      >
        Unresolved
      </button>
      <button
        class="btn btn-sm btn-default inline-block filter-inactive"
        data-btn-selected="false"
        data-sel_id="1"
        type="button"
        data-testid="thread-status-resolved"
        @mousedown.prevent="toggleFilterButton($event, undefined, 1)"
      >
        Resolved
      </button>
    </div>
  </div>
</template>

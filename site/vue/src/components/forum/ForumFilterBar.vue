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

const props = defineProps<Props>();

const isVisibleCategory = (category: ForumCategory): boolean => {
    return category.visibleDate === null || (Number(category.diff ?? 0) >= 0);
};

//Reactive filter state
const selectedCategoryIds = ref<number[]>([]);
const selectedThreadStatuses = ref<number[]>([]);
const unreadChecked = ref(false);

// Expose on window so legacy JS can read filter values.
// The Vue component now owns the single source of truth.
(window as any).selectedCategoryIds = selectedCategoryIds;
(window as any).selectedThreadStatuses = selectedThreadStatuses;
(window as any).selectedUnreadChecked = unreadChecked;

// Replace the legacy checkUnread — reads from Vue state instead of DOM.
(window as any).checkUnread = () => {
    const clearBtn = document.getElementById('clear_filter_button');
    if (unreadChecked.value) {
        if (clearBtn) clearBtn.style.visibility = 'visible';
        return true;
    }
    return false;
};

// Vue owns clearForumFilter — resets all reactive state and DOM, then the caller
// (Twig's onclick or updateClearFilterButton) calls updateThreads after.
(window as any).clearForumFilter = () => {
    // Reset unread
    if (unreadChecked.value) {
        unreadChecked.value = false;
        const checkbox = document.getElementById('unread') as HTMLInputElement | null;
        if (checkbox) checkbox.checked = false;
    }

    // Reset search input 
    const searchInput = document.getElementById('search-content') as HTMLInputElement | null;
    if (searchInput) {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('change'));
    }

    // Reset DOM button classes and jQuery .data() cache for category/status buttons
    document.querySelectorAll('#thread_category button, #thread_status_select button').forEach(btn => {
        const el = btn as HTMLElement;
        el.dataset.btnSelected = 'false';
        el.classList.remove('filter-active');
        el.classList.add('filter-inactive');
        const $btn = (window as any).$(el);
        if ($btn && $btn.data) $btn.data('btn-selected', 'false');
    });

    // Reset Vue reactive state
    selectedCategoryIds.value = [];
    selectedThreadStatuses.value = [];
    unreadChecked.value = false;

    // Hide clear button
    const clearBtn = document.getElementById('clear_filter_button');
    if (clearBtn) clearBtn.style.visibility = 'hidden';

    return false;
};

// Vue owns saveFilterState/getFilterState — reads from reactive refs for filter
// state, then pushes to history for browser back/forward navigation support.
// The search input value is still read from DOM (owned by legacy Twig/jQuery).
(window as any).getFilterState = () => {
    return {
        'categories': selectedCategoryIds.value,
        'thread-status': selectedThreadStatuses.value,
        'search-content': (document.getElementById('search-content') as HTMLInputElement)?.value ?? '',
    };
};

(window as any).saveFilterState = () => {
    history.pushState((window as any).getFilterState(), '');
};

//Initialise state from DOM on mount
function readInitialStateFromDOM(): void {
    const cats: number[] = [];
    document.querySelectorAll('#thread_category button').forEach(btn => {
        if ((btn as HTMLElement).dataset.btnSelected === 'true') {
            const id = parseInt((btn as HTMLElement).dataset.cat_id ?? '', 10);
            if (!isNaN(id)) cats.push(id);
        }
    });
    selectedCategoryIds.value = cats;

    const statuses: number[] = [];
    document.querySelectorAll('#thread_status_select button').forEach(btn => {
        if ((btn as HTMLElement).dataset.btnSelected === 'true') {
            const id = parseInt((btn as HTMLElement).dataset.sel_id ?? '', 10);
            if (!isNaN(id)) statuses.push(id);
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
    const $btn = (window as any).$(btn);
    const current = btn.dataset.btnSelected;

    if (current === 'true') {
        btn.dataset.btnSelected = 'false';
        btn.classList.remove('filter-active');
        btn.classList.add('filter-inactive');
        $btn.data('btn-selected', 'false');
        if (catId !== undefined) {
            selectedCategoryIds.value = selectedCategoryIds.value.filter(id => id !== catId);
        }
        if (statusSelId !== undefined) {
            selectedThreadStatuses.value = selectedThreadStatuses.value.filter(id => id !== statusSelId);
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
    (window as any).updateClearFilterButton?.();
    (window as any).updateThreads?.(true, (window as any).saveFilterState);
}

function toggleUnread(): void {
    unreadChecked.value = !unreadChecked.value;
    const checkbox = document.getElementById('unread') as HTMLInputElement | null;
    if (checkbox) {
        checkbox.checked = unreadChecked.value;
    }
    (window as any).updateThreads?.(true, (window as any).saveFilterState);
    (window as any).checkUnread?.();
}
</script>

<template>
  <div
    id="forum_filter_bar"
    data-testid="forum-filter-bar"
  >
    <button
      id="filter_unread_btn"
      :class="['btn btn-sm btn-default inline-block', unreadChecked ? 'filter-active' : 'filter-inactive']"
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

<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from 'vue';

declare global {
    interface Window {
        Cookies?: { get: (key: string) => string | undefined; set: (key: string, value: string, options?: { path?: string }) => void };
    }
}

const onClick = () => {
    toggleAllSections();
};

const detailsTableSelector = '#details-table';
let detailsTableEl: Element | null = null;

const handleDetailsTableClick = (event: Event) => {
    const target = event.target as Element | null;
    const header = target?.closest('.details-info-header');
    if (header) {
        toggleSection(header);
    }
};

const collapsedSections = ref<string[]>([]);

const toggleLabel = computed(() =>
        collapsedSections.value.length > 0 ? 'Expand All Sections' : 'Collapse All Sections',
);

onMounted(() => {
        collapsedSections.value = readCollapsedSections();
        if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', attachDetailsTableListener, { once: true });
        }
        else {
                attachDetailsTableListener();
        }
});

onUnmounted(() => {
    document.removeEventListener('DOMContentLoaded', attachDetailsTableListener);
    detailsTableEl?.removeEventListener('click', handleDetailsTableClick);
    detailsTableEl = null;
});

const attachDetailsTableListener = () => {
    const table = document.querySelector(detailsTableSelector);
    if (!table) {
        return;
    }
    detailsTableEl = table;
    table.addEventListener('click', handleDetailsTableClick);
};

const toggleSection = (header: Element) => {
    header.classList.toggle('panel-head-active');
    const id = header.getAttribute('data-section-id');
    const next = header.nextElementSibling as HTMLElement | null;
    if (next) {
        const isHidden = window.getComputedStyle(next).display === 'none';
        next.style.display = isHidden ? '' : 'none';
    }
    if (id) {
        const nextSet = new Set(collapsedSections.value);
                if (header.classList.contains('panel-head-active')) {
                        nextSet.delete(id);
        }
        else {
                        nextSet.add(id);
        }
        setCollapsedSections(Array.from(nextSet));
    }
};

const toggleAllSections = () => {
    if (collapsedSections.value.length === 0) {
        collapseAllSections();
    }
    else {
        expandAllSections();
    }
};

const getDetailsBasePath = (): string => {
    const table = document.getElementById('details-table');
    return table?.getAttribute('data-details-base-path') ?? '';
};

const setCollapsedSections = (ids: string[]) => {
    collapsedSections.value = ids;
    window.Cookies?.set('collapsed_sections', JSON.stringify(ids), { path: getDetailsBasePath() });
};

const collapseAllSections = () => {
    const headers = document.querySelectorAll('#details-table .details-info-header');
    const collapsedIds: string[] = [];

    headers.forEach((header) => {
        header.classList.remove('panel-head-active');
        const next = header.nextElementSibling as HTMLElement | null;
        if (next) {
            next.style.display = 'none';
        }
        const id = header.getAttribute('data-section-id');
        if (id) {
            collapsedIds.push(id);
        }
    });
    setCollapsedSections(collapsedIds);
};

const readCollapsedSections = (): string[] => {
    const raw = window.Cookies?.get('collapsed_sections') ?? '[]';
        try {
                return JSON.parse(raw) as string[];
    }
    catch {
                return [];
    }
};

const expandAllSections = () => {
    const headers = document.querySelectorAll('#details-table .details-info-header');

    headers.forEach((header) => {
        header.classList.add('panel-head-active');
        const next = header.nextElementSibling as HTMLElement | null;
        if (next) {
            next.style.display = '';
        }
    });
    setCollapsedSections([]);
};
</script>

<template>
  <button
    id="toggle-all-sections-btn"
    type="button"
    class="btn btn-primary"
    data-testid="toggle-all-sections"
    @click="onClick"
  >
        {{ toggleLabel }}
  </button>
</template>

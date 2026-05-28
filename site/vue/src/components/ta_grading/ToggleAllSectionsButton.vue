<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';

declare global {
    interface Window {
        toggleAllSections?: () => void;
        updateToggleButtonText?: () => void;
        CollapseAllSections?: () => void;
        UpdateCollapsedSections?: (ids: string[]) => void;
        UpdateToggleButtonText?: () => void;
        ExpandAllSections?: () => void;
        GetCollapsedSections?: () => string[];
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

onMounted(() => {
    updateToggleButtonText();
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

const MOBILE_BREAKPOINT = 951;

const toggleSection = (header: Element) => {
    header.classList.toggle('panel-head-active');
    const id = header.getAttribute('data-section-id');
    const next = header.nextElementSibling as HTMLElement | null;
    if (next) {
        const isHidden = window.getComputedStyle(next).display === 'none';
        next.style.display = isHidden ? '' : 'none';
    }
    if(id){
        const collapsed = getCollapsedSections();
        const nextSet = new Set(collapsed);
        if(header.classList.contains('panel-head-active')){
            nextSet.delete(id);
        }
        else {
            nextSet.add(id);
        }
        updateCollapsedSections(Array.from(nextSet));
        updateToggleButtonText();
    }
}

const toggleAllSections = () => {
    const collapsed = getCollapsedSections();

    if (collapsed.length === 0) {
        collapseAllSections();
    }
    else {
        expandAllSections();
    }
}

const getDetailsBasePath = (): string => {
    const table = document.getElementById('details-table');
    return table?.getAttribute('data-details-base-path') ?? '';
};

const updateCollapsedSections = (ids: string[]) => {
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
    updateCollapsedSections(collapsedIds);
    updateToggleButtonText();
};

const getCollapsedSections = (): string[] => {
    const raw = window.Cookies?.get('collapsed_sections') ?? '[]';
    try{
      return JSON.parse(raw) as string[];
    }
    catch {
      return [];  
    }
};

const updateToggleButtonText = () => {
    const collapsed = getCollapsedSections();
    const button = document.getElementById('toggle-all-sections-btn');
    if(!button) return;

    button.textContent = collapsed.length > 0 ? 'Expand All Sections' : 'Collapse All Sections';
}

const expandAllSections = () => {
    const headers = document.querySelectorAll('#details-table .details-info-header');

    headers.forEach((header) => {
        header.classList.add('panel-head-active');
        const next = header.nextElementSibling as HTMLElement | null;
        if (next) {
            next.style.display = '';
        }
    });
    updateCollapsedSections([]);
    updateToggleButtonText();
}
window.UpdateCollapsedSections = updateCollapsedSections;
window.CollapseAllSections = collapseAllSections;
window.ExpandAllSections = expandAllSections;
window.GetCollapsedSections = getCollapsedSections;
window.UpdateToggleButtonText = updateToggleButtonText;
</script>

<template>
  <button
    id="toggle-all-sections-btn"
    type="button"
    class="btn btn-primary"
    data-testid="toggle-all-sections"
    @click="onClick"
  >
    Collapse All Sections
  </button>
</template>

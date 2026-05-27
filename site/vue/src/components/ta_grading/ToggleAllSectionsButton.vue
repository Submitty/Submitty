<script setup lang="ts">
import { onMounted } from 'vue';

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
    window.toggleAllSections?.();
};

onMounted(() => {
    updateToggleButtonText?.();
});

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
    window.updateToggleButtonText?.();
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
    window.updateToggleButtonText?.();
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

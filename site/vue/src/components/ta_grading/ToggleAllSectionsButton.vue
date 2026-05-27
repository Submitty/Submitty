<script setup lang="ts">
import { onMounted } from 'vue';

declare global {
    interface Window {
        toggleAllSections?: () => void;
        updateToggleButtonText?: () => void;
        CollapseAllSections?: () => void;
        UpdateCollapsedSections?: (ids: string[]) => void;
        Cookies?: { set: (key: string, value: string, options?: { path?: string }) => void };
    }
}

const onClick = () => {
    window.toggleAllSections?.();
};

onMounted(() => {
    window.updateToggleButtonText?.();
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

window.UpdateCollapsedSections = updateCollapsedSections;
window.CollapseAllSections = collapseAllSections;
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

export function togglePanelSelectorModal(show: boolean) {
    window.dispatchEvent(new CustomEvent<boolean>('toggle-panel-modal', { detail: show }));
}

export function togglePanelSelectorModal(show: boolean) {
    const wrapper = document.getElementById('panels-selector-modal-wrapper');
    if (wrapper) {
        wrapper.style.display = show ? '' : 'none';
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const wrapper = document.getElementById('panels-selector-modal-wrapper');
        if (wrapper && wrapper.style.display !== 'none') {
            togglePanelSelectorModal(false);
        }
    }
});

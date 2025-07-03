declare global {
    interface Window {
        togglePanelSelectorModal: (show: boolean) => void;
    }
}

export function togglePanelSelectorModal(show: boolean) {
    if (show) {
        $('#panels-selector-modal').show();
    }
    else {
        $('#panels-selector-modal').hide();
    }
}

window.togglePanelSelectorModal = togglePanelSelectorModal;

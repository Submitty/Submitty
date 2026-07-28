import { updateVueComponent } from './utils/vue';

export function togglePanelSelectorModal(show: boolean) {
    updateVueComponent('.panel-selector-modal-mount', { visible: show });
}

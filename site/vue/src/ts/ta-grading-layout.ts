import { initializeResizablePanels } from '../../../ts/resizable-panels';
import { leftSelector, saveResizedColsDimensions, verticalDragBarSelector } from '../../../ts/ta-grading-panels';

export type TaLayoutDet = {
    numOfPanelsEnabled: number;
    isFullScreenMode: boolean;
    isFullLeftColumnMode: boolean;
    currentOpenPanel: string | null;
    currentTwoPanels: Record<string, string | null>;
    currentActivePanels: Record<string, boolean>;
    dividedColName: string;
    leftPanelWidth: string;
    bottomPanelHeight: string;
    bottomFourPanelRightHeight: string;
};

// Tracks the layout of TA grading page
export const taLayoutDet: TaLayoutDet = {
    numOfPanelsEnabled: 1,
    isFullScreenMode: false,
    isFullLeftColumnMode: false,
    currentOpenPanel: 'autograding_results',
    currentTwoPanels: {
        leftTop: null,
        leftBottom: null,
        rightTop: null,
        rightBottom: null,
    },
    currentActivePanels: {
        leftTop: false,
        leftBottom: false,
        rightTop: false,
        rightBottom: false,
    },
    dividedColName: 'LEFT',
    leftPanelWidth: '50%',
    bottomPanelHeight: '50%',
    bottomFourPanelRightHeight: '50%',
};

// returns taLayoutDet object from LS, and if its not present returns empty object
function getSavedTaLayoutDetails() {
    const savedData = localStorage.getItem('taLayoutDetails');
    return savedData ? (JSON.parse(savedData) as TaLayoutDet) : {} as TaLayoutDet;
}

function saveTaLayoutDetails() {
    localStorage.setItem('taLayoutDetails', JSON.stringify(taLayoutDet));
}

export function toggleFullScreenMode() {
    Object.assign(taLayoutDet, getSavedTaLayoutDetails());
    $('main#main').toggleClass('full-screen-mode');
    $('#fullscreen-btn-cont').toggleClass('active');
    taLayoutDet.isFullScreenMode = $('main#main').hasClass('full-screen-mode');

    // update the dragging event for two panels
    initializeResizablePanels(
        leftSelector,
        verticalDragBarSelector,
        false,
        saveResizedColsDimensions,
    );
    // Save the taLayoutDetails in LS
    saveTaLayoutDetails();
}

export function initToolbar() {
    Object.assign(taLayoutDet, getSavedTaLayoutDetails());
    console.log('taLayoutDet:', taLayoutDet);
    if (taLayoutDet.isFullScreenMode) {
        toggleFullScreenMode();
    }
}

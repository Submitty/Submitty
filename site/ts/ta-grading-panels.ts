import { togglePanelSelectorModal } from './panel-selector-modal';
import { initializeResizablePanels } from './resizable-panels';

declare global {
    interface Window {
        updateThePanelsElements(panelsAvailabilityObj: Record<PanelElement, boolean>): void;
        changePanelsLayout (panelsCount: string | number, isLeftTaller: boolean, twoOnRight: boolean): void;
        exchangeTwoPanels(): void;
    }
}

// Panel elements info to be used for layout designs
type PanelElement =
    | 'autograding_results'
    | 'grading_rubric'
    | 'submission_browser'
    | 'solution_ta_notes'
    | 'student_info'
    | 'grade_inquiry_info'
    | 'discussion_browser'
    | 'peer_info'
    | 'notebook-view';
let panelElements: Array<{ str: PanelElement; icon: string }> = [
    { str: 'autograding_results', icon: '.grading_toolbar .fa-list' },
    { str: 'grading_rubric', icon: '.grading_toolbar .fa-edit' },
    {
        str: 'submission_browser',
        icon: 'grading_toolbar .fa-folder-open.icon-header',
    },
    { str: 'solution_ta_notes', icon: 'grading_toolbar .fa-check.icon-header' },
    { str: 'student_info', icon: '.grading_toolbar .fa-user' },
    { str: 'peer_info', icon: '.grading_toolbar .fa-users' },
    { str: 'discussion_browser', icon: '.grading_toolbar .fa-comment-alt' },
    { str: 'grade_inquiry_info', icon: '.grading_toolbar .grade_inquiry_icon' },
    { str: 'notebook-view', icon: '.grading_toolbar .fas fa-book-open' },
];
type TaLayoutDet = {
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
const taLayoutDet: TaLayoutDet = {
    numOfPanelsEnabled: 1,
    isFullScreenMode: false,
    isFullLeftColumnMode: false,
    currentOpenPanel: panelElements[0].str,
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

// Grading Panel header width
let maxHeaderWidth = 0;
// Navigation Toolbar Panel header width
let maxNavbarWidth = 0;

// Various Ta-grading page selector for DOM manipulation
const leftSelector = '.two-panel-item.two-panel-left';
const verticalDragBarSelector = '.two-panel-drag-bar';
const leftHorizDragBarSelector
    = '.panel-item-section-drag-bar.panel-item-left-drag';
const rightHorizDragBarSelector
    = '.panel-item-section-drag-bar.panel-item-right-drag';
const panelsBucket: Record<string, string> = {
    leftTopSelector: '.two-panel-item.two-panel-left .left-top',
    leftBottomSelector: '.two-panel-item.two-panel-left .left-bottom',
    rightTopSelector: '.two-panel-item.two-panel-right .right-top',
    rightBottomSelector: '.two-panel-item.two-panel-right .right-bottom',
};

// Width of mobile and Tablet screens width
const MOBILE_WIDTH = 540;

// tracks if the current display screen is mobile
let isMobileView = false;

// Only keep those panels which are available
window.updateThePanelsElements = function (panelsAvailabilityObj: Record<PanelElement, boolean>) {
    // Attach the isAvailable to the panel elements to manage them
    panelElements = panelElements.filter((panel) => {
        return !!panelsAvailabilityObj[panel.str];
    });
};

function checkNotebookScroll() {
    if (
        taLayoutDet.currentTwoPanels.leftTop === 'notebook-view'
        || taLayoutDet.currentTwoPanels.leftBottom === 'notebook-view'
        || taLayoutDet.currentTwoPanels.rightTop === 'notebook-view'
        || taLayoutDet.currentTwoPanels.rightBottom === 'notebook-view'
        || taLayoutDet.currentOpenPanel === 'notebook-view'
    ) {
        $('#notebook-view').scroll(delayedNotebookSave());
    }
    else {
        $('#notebook-view').off('scroll');
        localStorage.removeItem('ta-grading-notebook-view-scroll-id');
        localStorage.removeItem('ta-grading-notebook-view-scroll-item');
    }
}

function delayedNotebookSave() {
    let timer: NodeJS.Timeout | null;
    return function () {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(notebookScrollSave, 250);
    };
}

function notebookScrollLoad() {
    const notebookView = $('#notebook-view');
    if (notebookView.is(':visible')) {
        let elementID = localStorage.getItem(
            'ta-grading-notebook-view-scroll-id',
        );
        let element: JQuery | null = null;
        if (elementID === null) {
            elementID = localStorage.getItem(
                'ta-grading-notebook-view-scroll-item',
            );
            if (elementID !== null) {
                element = $(`[data-item-ref=${elementID}]`);
            }
        }
        else {
            element = $(`[data-non-item-ref=${elementID}]`);
        }
        if (element !== null) {
            if (element.length !== 0) {
                const elementOffset = element.offset();
                const notebookScrollTop = notebookView.scrollTop();
                const notebookViewOffset = notebookView.offset();
                if (elementOffset && notebookViewOffset && notebookScrollTop) {
                    notebookView.scrollTop(
                        elementOffset.top
                        - notebookViewOffset.top
                        + notebookScrollTop,
                    );
                }
            }
            else {
                localStorage.removeItem('ta-grading-notebook-view-scroll-id');
                localStorage.removeItem('ta-grading-notebook-view-scroll-item');
            }
        }
    }
}

function notebookScrollSave() {
    const notebookView = $('#notebook-view');
    if (notebookView.length !== 0 && notebookView.is(':visible')) {
        const notebookTop = $('#notebook-view').offset()?.top || 0;
        const notebookScrollTop = notebookView.scrollTop() || 0;
        let element = $('#content_0');
        if (
            notebookScrollTop + (notebookView.innerHeight() || 0) + 1
            > notebookView[0].scrollHeight
        ) {
            element = $('[id^=content_]').last();
        }
        else {
            while (element.length !== 0) {
                if (element.offset()?.top ?? 0 > notebookTop) {
                    break;
                }
                element = element.next();
            }
        }

        if (element.length !== 0) {
            if (element.attr('data-item-ref') === undefined) {
                localStorage.setItem(
                    'ta-grading-notebook-view-scroll-id',
                    element.attr('data-non-item-ref') ?? '',
                );
                localStorage.removeItem('ta-grading-notebook-view-scroll-item');
            }
            else {
                localStorage.setItem(
                    'ta-grading-notebook-view-scroll-item',
                    element.attr('data-item-ref') ?? '',
                );
                localStorage.removeItem('ta-grading-notebook-view-scroll-id');
            }
        }
    }
}

// returns taLayoutDet object from LS, and if its not present returns empty object
function getSavedTaLayoutDetails() {
    const savedData = localStorage.getItem('taLayoutDetails');
    return savedData ? (JSON.parse(savedData) as TaLayoutDet) : {} as TaLayoutDet;
}

function saveTaLayoutDetails() {
    localStorage.setItem('taLayoutDetails', JSON.stringify(taLayoutDet));
}

function saveResizedColsDimensions(updateValue: string, isHorizontalResize: boolean) {
    if (isHorizontalResize) {
        taLayoutDet.bottomPanelHeight = updateValue;
    }
    else {
        taLayoutDet.leftPanelWidth = updateValue;
    }
    saveTaLayoutDetails();
}

function saveRightResizedColsDimensions(updateValue: string, isHorizontalResize: boolean) {
    if (isHorizontalResize) {
        taLayoutDet.bottomFourPanelRightHeight = updateValue;
    }
    else {
        taLayoutDet.leftPanelWidth = updateValue;
    }
    saveTaLayoutDetails();
}

function initializeHorizontalTwoPanelDrag() {
    if (taLayoutDet.dividedColName === 'RIGHT') {
        initializeResizablePanels(
            panelsBucket.rightBottomSelector,
            rightHorizDragBarSelector,
            true,
            saveResizedColsDimensions,
        );
    }
    if (taLayoutDet.dividedColName === 'LEFT') {
        if (taLayoutDet.numOfPanelsEnabled === 4) {
            initializeResizablePanels(
                panelsBucket.rightBottomSelector,
                rightHorizDragBarSelector,
                true,
                saveRightResizedColsDimensions,
            );
        }
        initializeResizablePanels(
            panelsBucket.leftBottomSelector,
            leftHorizDragBarSelector,
            true,
            saveResizedColsDimensions,
        );
    }
}

function initializeTaLayout() {
    if (isMobileView) {
        resetSinglePanelLayout();
    }
    else if (taLayoutDet.numOfPanelsEnabled) {
        togglePanelLayoutModes(true);
        if (taLayoutDet.isFullScreenMode && $('#silent-edit-id').length !== 0) {
            toggleFullLeftColumnMode(true);
        }
        // initialize the layout\
        initializeResizablePanels(
            leftSelector,
            verticalDragBarSelector,
            false,
            saveResizedColsDimensions,
        );
        initializeHorizontalTwoPanelDrag();
    }
    else {
        if (taLayoutDet.currentOpenPanel) {
            setPanelsVisibilities(taLayoutDet.currentOpenPanel);
        }
    }
    if (taLayoutDet.isFullScreenMode) {
        toggleFullScreenMode();
    }
    updateLayoutDimensions();
    updatePanelOptions();
    readCookies();
}

function updateLayoutDimensions() {
    // updates width of left columns (normal + full-left-col) with the last saved layout width
    $('.two-panel-item.two-panel-left').css({
        width: taLayoutDet.leftPanelWidth ? taLayoutDet.leftPanelWidth : '50%',
    });
    // updates width of left columns (normal + full-left-col) with the last saved layout width
    const bottomRow
        = taLayoutDet.dividedColName === 'RIGHT'
            ? $('.panel-item-section.right-bottom')
            : $('.panel-item-section.left-bottom');
    bottomRow.css({
        height: taLayoutDet.bottomPanelHeight
            ? taLayoutDet.bottomPanelHeight
            : '50%',
    });

    if (taLayoutDet.numOfPanelsEnabled === 4) {
        $('.panel-item-section.right-bottom').css({
            height: taLayoutDet.bottomFourPanelRightHeight
                ? taLayoutDet.bottomFourPanelRightHeight
                : '50%',
        });
    }
}

function updatePanelOptions() {
    if (taLayoutDet.numOfPanelsEnabled === 1) {
        return;
    }
    $('.grade-panel .panel-position-cont').attr(
        'size',
        taLayoutDet.numOfPanelsEnabled,
    );
    const panelOptions: JQuery<HTMLOptionElement> = $(
        '.grade-panel .panel-position-cont option',
    );
    panelOptions.each((idx) => {
        if (panelOptions[idx].value === 'leftTop') {
            if (
                taLayoutDet.numOfPanelsEnabled === 2
                || (taLayoutDet.numOfPanelsEnabled === 3
                    && taLayoutDet.dividedColName === 'RIGHT')
            ) {
                panelOptions[idx].text = 'Open as left panel';
            }
            else {
                panelOptions[idx].text = 'Open as top left panel';
            }
        }
        else if (panelOptions[idx].value === 'leftBottom') {
            if (
                taLayoutDet.numOfPanelsEnabled === 2
                || (taLayoutDet.numOfPanelsEnabled === 3
                    && taLayoutDet.dividedColName === 'RIGHT')
            ) {
                panelOptions[idx].classList.add('hide');
            }
            else {
                panelOptions[idx].classList.remove('hide');
            }
        }
        else if (panelOptions[idx].value === 'rightTop') {
            if (
                taLayoutDet.numOfPanelsEnabled === 2
                || (taLayoutDet.numOfPanelsEnabled === 3
                    && taLayoutDet.dividedColName !== 'RIGHT')
            ) {
                panelOptions[idx].text = 'Open as right panel';
            }
            else {
                panelOptions[idx].text = 'Open as top right panel';
            }
        }
        else if (panelOptions[idx].value === 'rightBottom') {
            if (
                taLayoutDet.numOfPanelsEnabled === 2
                || (taLayoutDet.numOfPanelsEnabled === 3
                    && taLayoutDet.dividedColName !== 'RIGHT')
            ) {
                panelOptions[idx].classList.add('hide');
            }
            else {
                panelOptions[idx].classList.remove('hide');
            }
        }
    });
}

/*
  Adjust buttons inside Grading panel header and shows only icons on smaller screens
 */
function adjustGradingPanelHeader() {
    const header = $('#grading-panel-header');
    const headerBox = $('.panel-header-box');
    const navBar = $('#bar_wrapper');
    const navBarBox = $('.navigation-box');
    const panelsContainer: HTMLElement | null = document.querySelector(
        '.panels-container',
    );
    if (panelsContainer === null) {
        return;
    }

    if (maxHeaderWidth < headerBox.width()!) {
        maxHeaderWidth = headerBox.width()!;
    }
    if (maxHeaderWidth > header.width()!) {
        headerBox.addClass('smaller-header');
    }
    else {
        headerBox.removeClass('smaller-header');
    }
    // changes for the navigation toolbar buttons
    if (maxNavbarWidth < $('.grading_toolbar').width()!) {
        maxNavbarWidth = $('.grading_toolbar').width()!;
    }
    if (maxNavbarWidth > navBar.width()!) {
        navBarBox.addClass('smaller-navbar');
    }
    else {
        navBarBox.removeClass('smaller-navbar');
    }
    // On mobile display screen hide the two-panel-mode
    if (isMobileView) {
        // hide the buttons
        navBarBox.addClass('mobile-view');
    }
    else {
        navBarBox.removeClass('mobile-view');
    }

    // From the complete content remove the height occupied by other elements
    let height = 0;
    $('.panels-container')
        .first()
        .siblings()
        .each(function () {
            if ($(this).css('display') !== 'none') {
                height += $(this).outerHeight(true)!;
            }
        });

    panelsContainer.style.height = `calc(100% - ${height}px)`;
}

function readCookies() {
    const silent_edit_enabled = window.Cookies.get('silent_edit_enabled') === 'true';

    const autoscroll = window.Cookies.get('autoscroll') || '';
    const opened_mark = window.Cookies.get('opened_mark') || '';
    const scroll_pixel = parseFloat(window.Cookies.get('scroll_pixel') || '');

    const testcases = window.Cookies.get('testcases') || '';

    const files = window.Cookies.get('files') || '';

    $('#silent-edit-id').prop('checked', silent_edit_enabled);

    window.addEventListener('load', () => {
        $(`#title-${opened_mark}`).trigger('click');
        if (scroll_pixel > 0) {
            const gradingRubric = document.getElementById(
                'grading-rubric',
            ) as HTMLElement;
            gradingRubric.scrollTop = scroll_pixel;
        }
    });

    if (autoscroll === 'on') {
        ($('#autoscroll_id')[0] as HTMLInputElement).checked = true;
        const files_array = JSON.parse(files) as string[];
        files_array.forEach((element: string) => {
            const file_path = element.split('#$SPLIT#$');
            let current = $('#file-container');
            for (let x = 0; x < file_path.length; x++) {
                current.children().each(function () {
                    if (x === file_path.length - 1) {
                        $(this)
                            .children('div[id^=file_viewer_]')
                            .each(function () {
                                if (
                                    $(this)[0].dataset.file_name
                                    === file_path[x]
                                    && !$($(this)[0]).hasClass('open')
                                ) {
                                    openFrame(
                                        $(this)[0].dataset.file_name!,
                                        $(this)[0].dataset.file_url!,
                                        $(this).attr('id')!.split('_')[2],
                                    );
                                }
                            });
                        $(this)
                            .children('div[id^=div_viewer_]')
                            .each(function () {
                                if (
                                    $(this)[0].dataset.file_name
                                    === file_path[x]
                                    && !$($(this)[0]).hasClass('open')
                                ) {
                                    openDiv($(this).attr('id')!.split('_')[2]);
                                }
                            });
                    }
                    else {
                        $(this)
                            .children('div[id^=div_viewer_]')
                            .each(function () {
                                if (
                                    $(this)[0].dataset.file_name === file_path[x]
                                ) {
                                    current = $(this);
                                    return false;
                                }
                            });
                    }
                });
            }
        });
    }
    for (let x = 0; x < testcases.length; x++) {
        if (testcases[x] !== '[' && testcases[x] !== ']') {
            openAutoGrading(testcases[x]);
        }
    }
}

// -----------------------------------------------------------------------------
// Panel show/hide
//
function resetSinglePanelLayout() {
    // hide all the two-panel-mode related nodes
    $('.two-panel-cont').removeClass('active');
    $('#two-panel-exchange-btn').removeClass('active');

    $('.panels-container').append(
        '<h3 class="panel-instructions">Click above to select a panel for display</h3>',
    );
    // Remove the full-left-column view (if it's currently present or is in-view) as it's meant for two-panel-mode only
    $('.two-panel-item.two-panel-left, .two-panel-drag-bar').removeClass(
        'active',
    );

    // remove the left bottom section and its drag bar
    $(
        '.panel-item-section.left-bottom, .panel-item-section.right-bottom, .panel-item-section-drag-bar',
    ).removeClass('active');

    const leftTopPanelId = taLayoutDet.currentTwoPanels.leftTop!;
    const leftBottomPanelId = taLayoutDet.currentTwoPanels.leftBottom!;
    const rightTopPanelId = taLayoutDet.currentTwoPanels.rightTop!;
    const rightBottomPanelId = taLayoutDet.currentTwoPanels.rightBottom!;
    // Now Fetch the panels from DOM
    const leftTopPanel = document.getElementById(leftTopPanelId);
    const leftBottomPanel = document.getElementById(leftBottomPanelId);
    const rightTopPanel = document.getElementById(rightTopPanelId);
    const rightBottomPanel = document.getElementById(rightBottomPanelId);

    if (rightBottomPanel) {
        document.querySelector('.panels-container')?.append(rightBottomPanel);
        taLayoutDet.currentOpenPanel = rightBottomPanelId;
    }
    if (rightTopPanel) {
        document.querySelector('.panels-container')?.append(rightTopPanel);
        taLayoutDet.currentOpenPanel = rightTopPanelId;
    }
    if (leftBottomPanel) {
        document.querySelector('.panels-container')?.append(leftBottomPanel);
        taLayoutDet.currentOpenPanel = leftBottomPanelId;
    }
    if (leftTopPanel) {
        document.querySelector('.panels-container')?.append(leftTopPanel);
        taLayoutDet.currentOpenPanel = leftTopPanelId;
    }
    // current open panel will be either left or right panel from two-panel-mode
    // passing forceVisible = true, otherwise this method will just toggle it and it will get hidden
    taLayoutDet.isFullLeftColumnMode = false;
    if (taLayoutDet.currentOpenPanel) {
        setPanelsVisibilities(taLayoutDet.currentOpenPanel, true);
    }
}

function checkForTwoPanelLayoutChange(
    isPanelAdded: boolean,
    panelId: string | null = null,
    panelPosition: string | null = null,
) {
    // update the global variable
    if (isPanelAdded) {
        taLayoutDet.currentTwoPanels[panelPosition!] = panelId;
    }
    else {
        // panel is going to be removed from screen
        // check which one out of the left or right is going to be hidden
        if (taLayoutDet.currentTwoPanels.leftTop === panelId) {
            taLayoutDet.currentTwoPanels.leftTop = null;
        }
        else if (taLayoutDet.currentTwoPanels.leftBottom === panelId) {
            taLayoutDet.currentTwoPanels.leftBottom = null;
        }
        else if (taLayoutDet.currentTwoPanels.rightTop === panelId) {
            taLayoutDet.currentTwoPanels.rightTop = null;
        }
        else if (taLayoutDet.currentTwoPanels.rightBottom === panelId) {
            taLayoutDet.currentTwoPanels.rightBottom = null;
        }
    }
    saveTaLayoutDetails();
}

// Keep only those panels which are part of the two panel layout
function setMultiPanelModeVisiblities() {
    panelElements.forEach((panel) => {
        const id_str = document.getElementById(`#${panel.str}_btn`)
            ? `#${panel.str}_btn`
            : `#${panel.str}-btn`;

        if (
            taLayoutDet.currentTwoPanels.leftTop === panel.str
            || taLayoutDet.currentTwoPanels.leftBottom === panel.str
            || taLayoutDet.currentTwoPanels.rightTop === panel.str
            || taLayoutDet.currentTwoPanels.rightBottom === panel.str
        ) {
            $(`#${panel.str}`).toggle(true);
            $(panel.icon).toggleClass('icon-selected', true);
            $(id_str).toggleClass('active', true);

            // display notebook elements if we have it
            $(`#${panel.str}`)
                .find('.CodeMirror')
                .each(function () {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access
                    (this as any).CodeMirror?.refresh();
                });
        }
        else {
            $(`#${panel.str}`).toggle(false);
            $(panel.icon).toggleClass('icon-selected', false);
            $(id_str).toggleClass('active', false);
        }
    });
}

function setPanelsVisibilities(
    ele: string,
    forceVisible: boolean | null = null,
    position: string | null = null,
) {
    panelElements.forEach((panel) => {
        const id_str = document.getElementById(`${panel.str}_btn`)
            ? `#${panel.str}_btn`
            : `#${panel.str}-btn`;
        if (panel.str === ele) {
            const eleVisibility
                = forceVisible !== null
                    ? forceVisible
                    : !$(`#${panel.str}`).is(':visible');
            $(`#${panel.str}`).toggle(eleVisibility);
            $(panel.icon).toggleClass('icon-selected', eleVisibility);
            $(id_str).toggleClass('active', eleVisibility);

            // display notebook elements if we have it
            $(`#${panel.str}`)
                .find('.CodeMirror')
                .each(function () {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access
                    (this as any).CodeMirror?.refresh();
                });

            if (taLayoutDet.numOfPanelsEnabled > 1 && !isMobileView) {
                checkForTwoPanelLayoutChange(
                    eleVisibility,
                    panel.str,
                    position,
                );
            }
            else {
                // update the global variable
                taLayoutDet.currentOpenPanel = eleVisibility ? panel.str : null;
                $('.panels-container > .panel-instructions').toggle(
                    !taLayoutDet.currentOpenPanel,
                );
            }
        }
        else if (
            (taLayoutDet.numOfPanelsEnabled
                && !isMobileView
                && taLayoutDet.currentTwoPanels.rightTop !== panel.str
                && taLayoutDet.currentTwoPanels.rightBottom !== panel.str
                && taLayoutDet.currentTwoPanels.leftTop !== panel.str
                && taLayoutDet.currentTwoPanels.leftBottom !== panel.str)
            || panel.str !== ele
        ) {
            // only hide those panels which are not given panel and not in taLayoutDet.currentTwoPanels if the twoPanelMode is enabled
            $(`#${panel.str}`).hide();
            $(panel.icon).removeClass('icon-selected');
            $(id_str).removeClass('active');
        }
    });
    // update the two-panels-layout if it's enabled
    if (taLayoutDet.numOfPanelsEnabled > 1 && !isMobileView) {
        updatePanelLayoutModes();
    }
    else {
        saveTaLayoutDetails();
    }
}

function toggleFullScreenMode() {
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
window.toggleFullScreenMode = toggleFullScreenMode;

function toggleFullLeftColumnMode(forceVal = false) {
    // toggle between the normal left and full left panel mode
    if (!forceVal) {
        taLayoutDet.isFullLeftColumnMode = !taLayoutDet.isFullLeftColumnMode;
    }

    const newPanelsContSelector = taLayoutDet.isFullLeftColumnMode
        ? '.content-items-container'
        : '.two-panel-cont';

    const leftPanelCont = document.querySelector(leftSelector);
    const dragBar = document.querySelector(verticalDragBarSelector);
    document
        .querySelector(newPanelsContSelector)
        ?.prepend(leftPanelCont!, dragBar!);

    $('#grading-panel-student-name').hide();
}

/**
 *
 * @param panelsCount
 * @param isLeftTaller
 * @param twoOnRight
 */
window.changePanelsLayout = function (panelsCount: string | number, isLeftTaller: boolean, twoOnRight = false) {
    taLayoutDet.numOfPanelsEnabled = +panelsCount;
    taLayoutDet.isFullLeftColumnMode = isLeftTaller;

    taLayoutDet.dividedColName = twoOnRight ? 'RIGHT' : 'LEFT';

    togglePanelLayoutModes(true);
    toggleFullLeftColumnMode(true);
    initializeResizablePanels(
        leftSelector,
        verticalDragBarSelector,
        false,
        saveResizedColsDimensions,
    );
    initializeHorizontalTwoPanelDrag();
    togglePanelSelectorModal(false);
    if (!taLayoutDet.isFullLeftColumnMode) {
        $('#grading-panel-student-name').show();
    }
};

function togglePanelLayoutModes(forceVal = false) {
    const twoPanelCont = $('.two-panel-cont');
    if (!forceVal) {
        taLayoutDet.numOfPanelsEnabled
            = +taLayoutDet.numOfPanelsEnabled === 3
                ? 1
                : +taLayoutDet.numOfPanelsEnabled + 1;
    }

    if (taLayoutDet.numOfPanelsEnabled === 2 && !isMobileView) {
        twoPanelCont.addClass('active');
        $('#two-panel-exchange-btn').addClass('active');
        $(
            '.panel-item-section.left-bottom, .panel-item-section.right-bottom, .panel-item-section-drag-bar',
        ).removeClass('active');
        $('.two-panel-item.two-panel-left, .two-panel-drag-bar').addClass(
            'active',
        );

        taLayoutDet.currentActivePanels = {
            leftTop: true,
            leftBottom: false,
            rightTop: true,
            rightBottom: false,
        };

        updatePanelLayoutModes();
    }
    else if (+taLayoutDet.numOfPanelsEnabled === 3 && !isMobileView) {
        twoPanelCont.addClass('active');
        $('.two-panel-item.two-panel-left, .two-panel-drag-bar').addClass(
            'active',
        );

        taLayoutDet.currentActivePanels = {
            leftTop: true,
            leftBottom: false,
            rightTop: true,
            rightBottom: false,
        };

        if (taLayoutDet.dividedColName === 'RIGHT') {
            $(
                '.panel-item-section.left-bottom, .panel-item-section-drag-bar.panel-item-left-drag',
            ).removeClass('active');
            $(
                '.panel-item-section.right-bottom, .panel-item-section-drag-bar.panel-item-right-drag',
            ).addClass('active');
            taLayoutDet.currentActivePanels.rightBottom = true;
        }
        else {
            $(
                '.panel-item-section.right-bottom, .panel-item-section-drag-bar.panel-item-right-drag',
            ).removeClass('active');
            $(
                '.panel-item-section.left-bottom, .panel-item-section-drag-bar.panel-item-left-drag',
            ).addClass('active');
            taLayoutDet.currentActivePanels.leftBottom = true;
        }

        initializeHorizontalTwoPanelDrag();
        updatePanelLayoutModes();
    }
    else if (+taLayoutDet.numOfPanelsEnabled === 4 && !isMobileView) {
        twoPanelCont.addClass('active');
        $('.two-panel-item.two-panel-left, .two-panel-drag-bar').addClass(
            'active',
        );

        taLayoutDet.currentActivePanels = {
            leftTop: true,
            leftBottom: true,
            rightTop: true,
            rightBottom: true,
        };

        $(
            '.panel-item-section.right-bottom, .panel-item-section-drag-bar.panel-item-right-drag',
        ).addClass('active');
        $(
            '.panel-item-section.left-bottom, .panel-item-section-drag-bar.panel-item-left-drag',
        ).addClass('active');

        initializeHorizontalTwoPanelDrag();
        updatePanelLayoutModes();
    }
    else {
        resetSinglePanelLayout();
        taLayoutDet.currentTwoPanels = {
            leftTop: null,
            leftBottom: null,
            rightTop: null,
            rightBottom: null,
        };
    }
    updatePanelOptions();
}

// Handles the DOM manipulation to update the two panel layout
function updatePanelLayoutModes() {
    // remove all panel instructions
    $('.panel-instructions').remove();
    setMultiPanelModeVisiblities();
    for (const panelIdx in panelsBucket) {
        const panelCont = document.querySelector(
            panelsBucket[panelIdx],
        )?.childNodes;
        if (!panelCont) {
            continue;
        }
        // Move all the panels from the left and right buckets to the main panels-container
        for (let idx = 0; idx < panelCont.length; idx++) {
            document.querySelector('.panels-container')!.append(panelCont[idx]);
        }
    }

    // loop through panel positions (topLeft, topRight, etc)
    for (const panel in taLayoutDet.currentTwoPanels) {
        // if the panel isn't active, skip it
        if (!taLayoutDet.currentActivePanels[panel]) {
            continue;
        }
        const panel_type = taLayoutDet.currentTwoPanels[panel];
        // get panel corresponding with the layout position
        const layout_panel = $(`${panelsBucket[`${panel}Selector`]}`);
        // get panel corresponding with what the user selected to use for this spot (autograding, rubric, etc)
        const dom_panel = document.getElementById(`${panel_type}`);
        if (dom_panel) {
            $(layout_panel).append(dom_panel);
        }
        else {
            $(layout_panel).append(
                '<h3 class="panel-instructions">Click above to select a panel for display</h3>',
            );
        }
    }
    saveTaLayoutDetails();
}

// Exchanges positions of left and right panels
window.exchangeTwoPanels = function () {
    if (+taLayoutDet.numOfPanelsEnabled === 2) {
        taLayoutDet.currentTwoPanels = {
            leftTop: taLayoutDet.currentTwoPanels.rightTop,
            rightTop: taLayoutDet.currentTwoPanels.leftTop,
        };
        updatePanelLayoutModes();
    }
    else if (
        +taLayoutDet.numOfPanelsEnabled === 3
        || +taLayoutDet.numOfPanelsEnabled === 4
    ) {
        taLayoutDet.currentTwoPanels = {
            leftTop: taLayoutDet.currentTwoPanels.rightTop,
            leftBottom: taLayoutDet.currentTwoPanels.rightBottom,
            rightTop: taLayoutDet.currentTwoPanels.leftTop,
            rightBottom: taLayoutDet.currentTwoPanels.leftBottom,
        };
        $(
            '.panel-item-section.left-bottom, .panel-item-section.right-bottom, .panel-item-section-drag-bar',
        ).toggleClass('active');
        taLayoutDet.dividedColName = $('.panel-item-section.right-bottom').is(
            ':visible',
        )
            ? 'RIGHT'
            : 'LEFT';
        updatePanelOptions();
        updatePanelLayoutModes();
        initializeHorizontalTwoPanelDrag();
    }
    else {
        // taLayoutDet.numOfPanelsEnabled is 1
        alert('Exchange works only when there are two panels...');
    }
};

function openAutoGrading(num: string) {
    $(`#tc_${num}`).click();
    // eslint-disable-next-line eqeqeq
    if ($(`#testcase_${num}`)[0] != null) {
        $(`#testcase_${num}`)[0].style.display = 'block';
    }
}

$(() => {
    Object.assign(taLayoutDet, getSavedTaLayoutDetails());

    // Check initially if its the mobile screen view or not
    isMobileView = window.innerWidth <= MOBILE_WIDTH;
    initializeTaLayout();

    window.addEventListener('resize', () => {
        const wasMobileView = isMobileView;
        isMobileView = window.innerWidth <= MOBILE_WIDTH;
        // if the width is switched between smaller and bigger screens, re-initialize the layout
        if (wasMobileView !== isMobileView) {
            initializeTaLayout();
        }
    });

    window.addEventListener('resize', () => {
        if ($('#silent-edit-id').length === 0) {
            return;
        }
        const name_div = $('#grading-panel-student-name');
        const panel_div = $('.panels-container');
        // have to calculate the height since the item is positioned absolutely
        const height = panel_div[0].getClientRects()[0].top - name_div.closest('.content-item')[0].getClientRects()[0].top;
        const padding_bottom = 12;
        name_div.css('height', height - padding_bottom);
        name_div.show();
        const panel_buttons_bbox = $('.panel-header-box')[0].getClientRects()[0];
        const name_div_bbox = name_div[0].getClientRects()[0];

        const overlap_margin = 15;
        const overlapping = (panel_buttons_bbox.left - name_div_bbox.right) < overlap_margin;
        if (overlapping || taLayoutDet.isFullLeftColumnMode) {
            $('#grading-panel-student-name').hide();
        }
    });

    // Grading panel toggle buttons
    $('.grade-panel button').click(function () {
        const btnCont = $(this).parent();
        const panelSpanId = btnCont.attr('id');

        if (!panelSpanId) {
            return;
        }

        const panelId = panelSpanId.split(/(_|-)btn/)[0];
        const selectEle = $(`select#${panelId}_select`);

        // Hide all select dropdown except the current one
        $('select.panel-position-cont').not(selectEle).hide();

        const isPanelOpen = $(`#${panelId}`).is(':visible');
        // If panel is not in-view and two/three-panel-mode is enabled show the drop-down to select position,
        // otherwise just toggle it
        if (isPanelOpen || +taLayoutDet.numOfPanelsEnabled === 1) {
            setPanelsVisibilities(panelId);
        }
        else {
            // removing previously selected option
            selectEle.val(0);
            if (selectEle.is(':visible')) {
                selectEle.hide();
            }
            else {
                selectEle.show();
            }
        }
    });

    // panel position selector change event
    $('.grade-panel .panel-position-cont').change(function () {
        const panelSpanId = $(this).parent().attr('id');
        const position = $(this).val() as string;
        if (panelSpanId) {
            const panelId = panelSpanId.split(/(_|-)btn/)[0];
            setPanelsVisibilities(panelId, null, position);
            $(`select#${panelId}_select`).hide();
            checkNotebookScroll();
        }
    });

    // Check for the panels status initially
    adjustGradingPanelHeader();
    const resizeObserver = new ResizeObserver(() => {
        adjustGradingPanelHeader();
    });
    // calling it for the first time i.e initializing
    adjustGradingPanelHeader();
    const gradingPanelHeader = document.getElementById('grading-panel-header');
    if (gradingPanelHeader) {
        resizeObserver.observe(gradingPanelHeader);
    }

    notebookScrollLoad();
    checkNotebookScroll();
});

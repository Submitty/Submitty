import {
    taLayoutDet,
    setPanelsVisibilities,
    isMobileView,
    changeMobileView,
    initializeTaLayout,
} from './ta-grading-panels';

// Grading Panel header width
let maxHeaderWidth = 0;
// Navigation Toolbar Panel header width
let maxNavbarWidth = 0;

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

$(() => {
    // Check initially if its the mobile screen view or not
    changeMobileView();

    window.addEventListener('resize', () => {
        const wasMobileView = isMobileView;
        changeMobileView();
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

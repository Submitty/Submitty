/* global courseUrl, showPopup, escapeSpecialChars, full_access_grader_permission, is_team_assignment, is_student */
/* exported gradeableMessageAgree, gradeableMessageCancel, showGradeableMessage, hideGradeableMessage, expandAllSections, collapseAllSections, grade_inquiry_only, reverse_inquiry_only, inquiry_update */
/* exported handleDetailsFiltersMounted, handleViewSectionsChange, handleSortOrderChange, handleAnonChange, handleInquiryChange, handleWithdrawnChange */

const MOBILE_BREAKPOINT = 951;

let collapseItems;
$(document).ready(() => {
    const collapsedSections = Cookies.get('collapsed_sections');
    collapseItems = new Set(collapsedSections && JSON.parse(collapsedSections));

    // Attach the collapsible panel on details-table
    const ANIMATION_DURATION = 600;
    $('#details-table .details-info-header').click(function () {
        $(this).toggleClass('panel-head-active');
        const id = $(this).attr('data-section-id');
        if (collapseItems.has(id)) {
            collapseItems.delete(id);
        }
        else {
            collapseItems.add(id);
        }
        updateCollapsedSections();
        if (window.innerWidth < MOBILE_BREAKPOINT) {
            $(this).next().slideToggle({
                duration: ANIMATION_DURATION,
            });
        }
        else {
            $(this).next().toggle();
        }
    });

    // Adds labels to values in mobile view using pseudo selector :before
    const style = document.createElement('style');
    let content = '';
    // loop over the head row of `details-table`
    $('#details-table thead tr th').each(function (idx) {
        if (idx) {
            // add each header's text as a label for that column's values in the student's submission
            content = $(this).data('col-title') ?? '';
            style.innerHTML += `
              #details-table td:nth-of-type(${escapeSpecialChars((idx + 1).toString())}):before {
                  content: "${escapeSpecialChars(content)}";
              }
            `;
        }
    });
    document.head.appendChild(style);
});

function getCollapsedSections() {
    return JSON.parse(Cookies.get('collapsed_sections') || '[]');
}

// The button label is rendered by the Vue component, from here we push new state to it via reRender.
function updateToggleButtonLabel(collapsed) {
    document.querySelector('.js-toggle-all-sections')?.reRender?.({ collapsed });
}

function updateCollapsedSections() {
    Cookies.set('collapsed_sections', JSON.stringify([...collapseItems]), { path: $('#details-table').attr('data-details-base-path') });
}

function expandAllSections() {
    $('#details-table .details-info-header').each(function () {
        $(this).addClass('panel-head-active');
        $(this).next().show();
    });
    collapseItems.clear();
    updateCollapsedSections();
    updateToggleButtonLabel(false);
}

function collapseAllSections() {
    collapseItems.clear();
    $('#details-table .details-info-header').each(function () {
        $(this).removeClass('panel-head-active');
        $(this).next().hide();
        collapseItems.add($(this).attr('data-section-id'));
    });
    updateCollapsedSections();
    updateToggleButtonLabel(true);
}

function toggleAllSections() {
    const collapsed = getCollapsedSections();

    if (collapsed.length === 0) {
        collapseAllSections();
    }
    else {
        expandAllSections();
    }
}

function inquiryUpdate() {
    const status = Cookies.get('inquiry_status');

    if (status === 'on') {
        $('.grade-button').each(function () {
            if (typeof $(this).attr('data-grade-inquiry') === 'undefined') {
                $(this).closest('.grade-table').addClass('inquiry-only-disabled'); // hide gradeable items without active inquiries, overrrides withdrawn filter
            }
        });
    }
    else {
        $('.grade-button').each(function () {
            $(this).closest('.grade-table').removeClass('inquiry-only-disabled'); // show all gradeable items
        });
    }
}

function handleDetailsFiltersMounted(state) {
    const withdrawnRow = $('[data-student="electronic-grade-withdrawn"]');
    withdrawnRow.hide();
    if (((Cookies.get('include_withdrawn_students') || 'omit') === 'include') || (window.is_team_assignment)) {
        withdrawnRow.show();
    }
    if (state.inquiryOnly) {
        $('.grade-button').each(function () {
            if (typeof $(this).attr('data-grade-inquiry') === 'undefined') {
                $(this).closest('.grade-table').addClass('inquiry-only-disabled');
            }
        });
        document.getElementById('inquiry-banner').style.display = '';
    }
    window.updateElectronicGradingRowNumbersAndColors?.();
}

function handleViewSectionsChange(checked) {
    Cookies.set('view', checked ? 'assigned' : 'all', { path: document.body.dataset.coursePath, expires: 365 });
    localStorage.setItem('general-setting-navigate-assigned-students-only', checked ? 'true' : 'false');
    location.reload();
}

function handleSortOrderChange(checked) {
    Cookies.set('sort', checked ? 'random' : 'id', { path: document.body.dataset.coursePath, expires: 365 });
    location.reload();
}

function handleAnonChange(checked) {
    Cookies.set('anon_mode', checked ? 'on' : 'off', { path: document.body.dataset.coursePath, expires: 365 });
    location.reload();
}

function handleInquiryChange(checked) {
    Cookies.set('inquiry_status', checked ? 'on' : 'off', { path: document.body.dataset.coursePath, expires: 365 });
    if (checked) {
        $('.grade-button').each(function () {
            if (typeof $(this).attr('data-grade-inquiry') === 'undefined') {
                $(this).closest('.grade-table').addClass('inquiry-only-disabled');
            }
        });
    }
    else {
        $('.grade-button').each(function () {
            $(this).closest('.grade-table').removeClass('inquiry-only-disabled');
        });
    }
    document.getElementById('inquiry-banner').style.display = checked ? '' : 'none';
}

function handleWithdrawnChange(checked) {
    Cookies.set('include_withdrawn_students', checked ? 'omit' : 'include', { path: document.body.dataset.coursePath, expires: 365 });
    $('[data-student="electronic-grade-withdrawn"]').toggle(!checked);
    $('[data-student="simple-grade-withdrawn"]').toggle(!checked);
    window.updateSimpleGradingRowNumbersAndColors?.();
    window.updateElectronicGradingRowNumbersAndColors?.();

    // Withdrawn students should always be visible in team gradeables
    if (is_team_assignment) {
        $('[data-student="electronic-grade-withdrawn"]').show();
    }
}

function handleGroupByClustersChange(checked) {
    Cookies.set('group_by_clusters', checked ? 'true' : 'false', { path: '/' });
    window.location.reload();
}

function updateClusteringStatus(status) {
    document.body.setAttribute('data-clustering-status', status);
    $('#clustering-loading-banner').toggle(status === 'fetching');
}

function handleClusteringDone() {
    window.location.reload();
}

function handleClusteringError(message) {
    alert(message);
}

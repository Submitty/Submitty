/* global courseUrl, showPopup, escapeSpecialChars, full_access_grader_permission, is_team_assignment, is_student */
/* exported gradeableMessageAgree, gradeableMessageCancel, showGradeableMessage, hideGradeableMessage, expandAllSections, collapseAllSections, grade_inquiry_only, reverse_inquiry_only, inquiry_update */

const MOBILE_BREAKPOINT = 951;

let collapseItems;
$(document).ready(() => {
    updateToggleButtonText();
    initAiGroupingPanel();
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

    // Creating and adding style for the pseudo selector in the details-table
    const style = document.createElement('style');
    let content = '';
    // loop over the head row of `details-table`
    $('#details-table thead tr th').each(function (idx) {
        if (idx) {
            // the content to be added is inside this data attr
            content = $(this).data('col-title');
            style.innerHTML += `
              #details-table td:nth-of-type(${escapeSpecialChars((idx + 1).toString())}):before {
                  content: "${escapeSpecialChars(content)}";
              }
            `;
        }
    });
    document.head.appendChild(style);

    if (!localStorage.getItem(gradeableMessageStorageKey()) && document.getElementById('gradeable-message-data').dataset.userlevel !== '1') {
        const form = $('#gradeable-message-popup');
        form.css('display', 'block');
        form.find('.form-body').scrollTop(0);
    }
});

function gradeableMessageStorageKey() {
    const dataElement = document.getElementById('gradeable-message-data');
    const semester = dataElement.dataset.semester;
    const course = dataElement.dataset.course;
    const gradeable = dataElement.dataset.gradeable;
    return `${semester}-${course}-${gradeable}-message`;
}

function gradeableMessageAgree() {
    if (!localStorage.getItem(gradeableMessageStorageKey())) {
        localStorage.setItem(gradeableMessageStorageKey(), 'agreed');
        const form = $('#gradeable-message-popup');
        form.css('display', 'none');
    }
    return false;
}

function gradeableMessageCancel() {
    window.location = courseUrl;
}

function showGradeableMessage() {
    const message = $('#gradeable-message-popup');
    message.css('display', 'block');
    $('#agree-button').css('display', 'none');
    $('#cancel-button').css('display', 'none');
    $('#close-hidden-button').css('display', 'block');
}

function hideGradeableMessage() {
    const message = $('#gradeable-message-popup');
    message.css('display', 'none');
}

function initAiGroupingPanel() {
    const panel = document.getElementById('ai-grouping-panel');
    if (panel === null) {
        return;
    }

    const endpoint = panel.dataset.aiGroupingEndpoint;
    if (endpoint === undefined || endpoint.length === 0) {
        return;
    }

    const cookieSort = Cookies.get('sort');
    const cookieDirection = Cookies.get('direction');
    const cookieView = Cookies.get('view');
    const cookieInquiryStatus = Cookies.get('inquiry_status');

    const sort = cookieSort || panel.dataset.sort || 'id';
    const direction = cookieDirection || panel.dataset.direction || 'ASC';
    const view = (cookieView === 'all' || cookieView === 'assigned')
        ? cookieView
        : (panel.dataset.view || 'assigned');
    const inquiryStatus = (cookieInquiryStatus === 'on' || cookieInquiryStatus === 'off')
        ? cookieInquiryStatus
        : (panel.dataset.inquiryStatus || 'off');

    const params = new URLSearchParams({
        sort,
        direction,
        view,
        inquiry_status: inquiryStatus,
    });

    const loading = document.getElementById('ai-grouping-loading');
    const errorBox = document.getElementById('ai-grouping-error');
    const emptyBox = document.getElementById('ai-grouping-empty');
    const list = document.getElementById('ai-grouping-list');

    if (list !== null) {
        list.replaceChildren();
    }
    if (errorBox !== null) {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }
    if (emptyBox !== null) {
        emptyBox.hidden = true;
    }

    $.ajax({
        type: 'GET',
        dataType: 'json',
        url: `${endpoint}?${params.toString()}`,
    }).done((response) => {
        if (response.status !== 'success' || response.data === undefined || !Array.isArray(response.data.groups)) {
            if (errorBox !== null) {
                errorBox.textContent = 'Failed to parse grouping suggestions.';
                errorBox.hidden = false;
            }
            return;
        }

        const groups = response.data.groups;
        if (groups.length === 0) {
            if (emptyBox !== null) {
                emptyBox.textContent = inquiryStatus === 'on'
                    ? 'No grouping suggestions are available because there are no active grade inquiries in the current filtered scope.'
                    : 'No grouping suggestions are currently available.';
                emptyBox.hidden = false;
            }
            return;
        }

        if (list === null) {
            return;
        }

        groups.forEach((group) => {
            const groupCard = document.createElement('article');
            groupCard.className = 'details-ai-group-card';

            const title = document.createElement('h3');
            title.className = 'details-ai-group-title';
            title.textContent = `${group.label} - ${group.size} submitters`;
            groupCard.appendChild(title);

            const summary = document.createElement('p');
            summary.className = 'details-ai-group-summary';
            const confidence = Number(group.confidence);
            const confidenceText = Number.isFinite(confidence) ? confidence.toFixed(2) : 'N/A';
            summary.textContent = `Confidence: ${confidenceText}`;
            groupCard.appendChild(summary);

            if (Array.isArray(group.top_signals) && group.top_signals.length > 0) {
                const signals = document.createElement('p');
                signals.className = 'details-ai-group-signals';
                signals.textContent = `Signals: ${group.top_signals.join(', ')}`;
                groupCard.appendChild(signals);
            }

            const members = document.createElement('ul');
            members.className = 'details-ai-member-list';
            if (Array.isArray(group.members)) {
                group.members.forEach((member) => {
                    const item = document.createElement('li');
                    item.className = 'details-ai-member-item';

                    const link = document.createElement('a');
                    link.className = 'details-ai-member-link';
                    link.href = member.jump_url;
                    link.textContent = member.anon_id;

                    const version = Number(member.active_version);
                    const versionText = Number.isFinite(version) ? `v${version}` : 'vN/A';
                    const rawAutogradingPercent = member.autograding_percent;
                    const autogradingPercent = Number(rawAutogradingPercent);
                    const hasAutogradingPercent = rawAutogradingPercent !== null
                        && rawAutogradingPercent !== undefined
                        && Number.isFinite(autogradingPercent);
                    const scoreText = hasAutogradingPercent ? `${autogradingPercent.toFixed(1)}% auto` : 'auto N/A';
                    link.title = `${versionText}, ${scoreText}`;

                    item.appendChild(link);
                    members.appendChild(item);
                });
            }
            groupCard.appendChild(members);
            list.appendChild(groupCard);
        });
    }).fail((jqXHR) => {
        if (errorBox !== null) {
            const responseMessage = jqXHR.responseJSON && typeof jqXHR.responseJSON.message === 'string'
                ? jqXHR.responseJSON.message
                : 'Unable to load grouping suggestions.';
            errorBox.textContent = responseMessage;
            errorBox.hidden = false;
        }
    }).always(() => {
        if (loading !== null) {
            loading.hidden = true;
        }
    });
}

function getCollapsedSections() {
    return JSON.parse(Cookies.get('collapsed_sections') || '[]');
}

function updateToggleButtonText() {
    const collapsed = getCollapsedSections();
    const button = $('#toggle-all-sections-btn');

    if (collapsed.length === 0) {
        button.text('Collapse All Sections');
    }
    else {
        button.text('Expand All Sections');
    }
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
    updateToggleButtonText();
}

function collapseAllSections() {
    collapseItems.clear();
    $('#details-table .details-info-header').each(function () {
        $(this).removeClass('panel-head-active');
        $(this).next().hide();
        collapseItems.add($(this).attr('data-section-id'));
    });
    updateCollapsedSections();
    updateToggleButtonText();
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

// Ensures all filters and checkboxes remain the same on page reload.
window.addEventListener('DOMContentLoaded', () => {
    const inquiryFilterStatus = Cookies.get('inquiry_status');
    const withdrawnFilterElements = $('[data-student="electronic-grade-withdrawn"]');
    withdrawnFilterElements.hide();
    // Instructors and TAs have access to all toggles
    if (full_access_grader_permission) {
        // Only Assigned Sections
        const assignedFilterBox = document.getElementById('toggle-view-sections');
        const assignedFilterStatus = Cookies.get('view');
        assignedFilterBox.checked = (assignedFilterStatus === 'assigned' || assignedFilterStatus === undefined);

        // Withdrawn Students
        const withdrawnFilterStatus = Cookies.get('include_withdrawn_students') || 'omit';
        const withdrawnFilterBox = document.getElementById('toggle-filter-withdrawn');

        if (!is_team_assignment) { // Toggle not available on team assignments
            if (withdrawnFilterStatus === 'omit') {
                withdrawnFilterBox.checked = true;
                withdrawnFilterElements.hide();
            }
            else {
                withdrawnFilterBox.checked = false;
                withdrawnFilterElements.show();
            }
        }
    }
    // Grade Inquiry Only - students don't have permission
    if (!is_student) {
        const inquiryFilterBox = document.getElementById('toggle-inquiry-only');
        inquiryFilterBox.checked = (inquiryFilterStatus === 'on');
        inquiryFilterBox.addEventListener('change', () => {
            window.setTimeout(initAiGroupingPanel, 0);
        });
    }
    // Randomize Order - all graders have permission
    const randomFilterBox = document.getElementById('toggle-random-order');
    const randomFilterStatus = Cookies.get('sort');
    randomFilterBox.checked = (randomFilterStatus === 'random');
    randomFilterBox.addEventListener('change', () => {
        window.setTimeout(initAiGroupingPanel, 0);
    });

    if (full_access_grader_permission) {
        const assignedFilterBox = document.getElementById('toggle-view-sections');
        if (assignedFilterBox !== null) {
            assignedFilterBox.addEventListener('change', () => {
                window.setTimeout(initAiGroupingPanel, 0);
            });
        }
    }

    // Withdrawn students should always be visible in team gradeables
    if (is_team_assignment) {
        withdrawnFilterElements.show();
    }
});

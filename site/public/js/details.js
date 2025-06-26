/* global courseUrl, showPopup, full_access_grader_permission, is_team_assignment, is_student */
/* exported gradeableMessageAgree, gradeableMessageCancel, showGradeableMessage, hideGradeableMessage, expandAllSections, collapseAllSections, grade_inquiry_only, reverse_inquiry_only, inquiryUpdate filterWithdrawnUpdate */
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

    // Creating and adding style for the pseudo selector in the details-table
    const style = document.createElement('style');
    let content = '';
    // loop over the head row of `details-table`
    $('#details-table thead tr th').each(function (idx) {
        if (idx) {
            // the content to be added is inside this data attr
            content = $(this).data('col-title');
            style.innerHTML += `
              #details-table td:nth-of-type(${idx + 1}):before {
                  content: "${content}";
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
}

function collapseAllSections() {
    collapseItems.clear();
    $('#details-table .details-info-header').each(function () {
        $(this).removeClass('panel-head-active');
        $(this).next().hide();
        collapseItems.add($(this).attr('data-section-id'));
    });
    updateCollapsedSections();
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
        grade_inquiry_toggled = false;
        $('.grade-button').each(function () {
            $(this).closest('.grade-table').removeClass('inquiry-only-disabled'); // show all gradeable items
        });
    }
}

function filterWithdrawnUpdate() {
    const filterCheckbox = document.getElementById('toggle-filter-withdrawn');
    const withdrawnElements = $('[data-student="electronic-grade-withdrawn"]');

    if (filterCheckbox.checked) {
        withdrawnElements.hide();
        Cookies.set('filter_withdrawn_student', 'true');
    }
    else {
        withdrawnElements.show();
        Cookies.set('filter_withdrawn_student', 'false');
    }
}

// Ensures all filters and checkboxes remain the same on page reload.
window.addEventListener('DOMContentLoaded', () => {
    const inquiryFilterStatus = Cookies.get('inquiry_status');
    const withdrawnFilterElements = $('[data-student="electronic-grade-withdrawn"]');
    // Instructors and TAs have access to all toggles
    if (full_access_grader_permission) {
        // Only Assigned Sections
        const assignedFilterBox = document.getElementById('toggle-view-sections');
        const assignedFilterStatus = Cookies.get('view');
        assignedFilterBox.checked = (assignedFilterStatus === 'assigned' || assignedFilterStatus === undefined);

        // Anonymous Mode
        const anonFilterBox = document.getElementById('toggle-anon-students');
        const currentGradeableCookiePath = `anon_mode_${Cookies.get('current_gradeable_path')}`;
        const anonFilterStatus = Cookies.get(currentGradeableCookiePath);
        anonFilterBox.checked = (anonFilterStatus === 'on');

        // Withdrawn Students
        const withdrawnFilterStatus = Cookies.get('filter_withdrawn_student');
        const withdrawnFilterBox = document.getElementById('toggle-filter-withdrawn');
        if (!is_team_assignment) { // Toggle not available on team assignments
            if (withdrawnFilterStatus === 'true' || withdrawnFilterStatus === undefined) {
                withdrawnFilterBox.checked = true;
                withdrawnFilterElements.hide();
            }
            else {
                withdrawnFilterBox.checked = false;
                withdrawnFilterElements.show();
            }
        }
        // Withdrawn students should always be visible in team gradeables
        else {
            withdrawnFilterElements.show();
        }
    }
    // Grade Inquiry Only - students don't have permission
    if (!is_student) {
        const inquiryFilterBox = document.getElementById('toggle-inquiry-only');
        inquiryFilterBox.checked = (inquiryFilterStatus === 'on');
    }
    // Randomize Order - all graders have permission
    const randomFilterBox = document.getElementById('toggle-random-order');
    const randomFilterStatus = Cookies.get('sort');
    randomFilterBox.checked = (randomFilterStatus === 'random');

    // all graders should see withdrawn students on team assignments
    if (is_team_assignment) {
        withdrawnFilterElements.show();
    }
});

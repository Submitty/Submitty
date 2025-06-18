/* global courseUrl, showPopup */
/* exported gradeableMessageAgree, gradeableMessageCancel, showGradeableMessage, hideGradeableMessage, expandAllSections, collapseAllSections, grade_inquiry_only, reverse_inquiry_only, inquiry_update filter_withdrawn_update */

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

function filter_withdrawn_update() {
    // set up, by default this will hide withdrawned student
    const filterWithdrawnCheckbox = $('[data-testid="toggle-filter-withdrawn"]');
    if (Cookies.get('filter_student') === undefined) {
        $('[data-student="electronic-grade-withdrawn"]').hide();
        filterWithdrawnCheckbox.prop('checked', true);
    }
    else if (Cookies.get('filter_student') === 'false') {
        $('[data-student="electronic-grade-withdrawn"]').show();
        filterWithdrawnCheckbox.prop('checked', false);
    }
    else {
        $('[data-student="electronic-grade-withdrawn"]').hide();
        filterWithdrawnCheckbox.prop('checked', true);
    }

    // filter students who withdrew from this course
    filterWithdrawnCheckbox.on('change', () => {
        if (Cookies.get('filter_student') === 'false') {
            $('[data-student="electronic-grade-withdrawn"]').hide();
            Cookies.set('filter_student', true);
        }
        else {
            $('[data-student="electronic-grade-withdrawn"]').show();
            Cookies.set('filter_student', false);
        }
    });
}

function inquiry_update() {
    const check_inquiry = $('[data-testid="toggle-grade-inquiry"]');
    const status = Cookies.get('inquiry_status');
    if (status === 'on') {
        $('.grade-button').each(function () {
            if (typeof $(this).attr('data-grade-inquiry') === 'undefined') {
                $(this).closest('.grade-table').hide();
                check_inquiry.prop('checked', true);
            }
        });
    }
    check_inquiry.on('change', () => {
        if (status === 'on') {
            Cookies.set('inquiry_status', 'off');
        }
        else {
            Cookies.set('inquiry_status', 'on');
        }
        location.reload();
    });
}

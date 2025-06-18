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

function inquiry_update() {
    const button = document.getElementById('inquiryButton');
    const status = Cookies.get('inquiry_status');

    if (status === 'on') {
        $('.grade-button').each(function () {
            if (typeof $(this).attr('data-grade-inquiry') === 'undefined') {
                $(this).closest('.grade-table').hide(); // hide gradeable items without active inquiries
            }
        });
        button.textContent = 'Grade Inquiry Only: On';
    }
    else {
        $('.grade-button').each(function () {
            $(this).closest('.grade-table').show(); // show all gradeable items
        });
        button.textContent = 'Grade Inquiry Only: Off';
    }
}

function filter_withdrawn_update() {
    const filterButton = document.getElementById('filter-withdrawn-button');
    const filter_status = Cookies.get('filter_withdrawn_student');
    const inquiry_status = Cookies.get('inquiry_status');

    const withdrawnElements = $('[data-student="electronic-grade-withdrawn"]');

    const shouldHideAll = (filter_status === undefined || filter_status === 'true');
    const inquiryMode = (inquiry_status === 'on');

    if (shouldHideAll) {
        withdrawnElements.hide();
        filterButton.textContent = 'Show Withdrawn Students';
        Cookies.set('filter_withdrawn_student', 'true');
    } else {
        withdrawnElements.each(function () {
            const hasInquiry = typeof $(this).find('.grade-button').attr('data-grade-inquiry') !== 'undefined';
            if (inquiryMode && !hasInquiry) {
                $(this).hide();
            } else {
                $(this).show();
            }
        });
        filterButton.textContent = 'Hide Withdrawn Students';
        Cookies.set('filter_withdrawn_student', 'false');
    }

    filterButton.addEventListener('click', () => {
        const currentlyHidden = Cookies.get('filter_withdrawn_student') === 'true';
        Cookies.set('filter_withdrawn_student', currentlyHidden ? 'false' : 'true');
        location.reload();
    });
}

/* global courseUrl */
/* exported gradeableMessageAgree, gradeableMessageCancel, showGradeableMessage, hideGradeableMessage, expandAllSections, collapseAllSections */
const MOBILE_BREAKPOINT = 951;

let collapseItems;
$(document).ready(() => {
    const collapsedSections = document.cookie.replace(/(?:(?:^|.*;\s*)collapsed_sections\s*=\s*([^;]*).*$)|^.*$/, '$1');
    if (collapsedSections === '') {
        collapseItems = new Set();
    }
    else {
        collapseItems = new Set(JSON.parse(decodeURIComponent(document.cookie.replace(/(?:(?:^|.*;\s*)collapsed_sections\s*=\s*([^;]*).*$)|^.*$/, '$1'))));
    }

    // Attach the collapsible panel on details-table
    const ANIMATION_DURATION = 600;
    $('#details-table .details-info-header').click(function() {
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
    document.cookie = `collapsed_sections=${encodeURIComponent(JSON.stringify([...collapseItems]))};path=${$('#details-table').attr('data-details-base-path')}`;
}

function expandAllSections() {
    $('#details-table .details-info-header').each(function() {
        $(this).addClass('panel-head-active');
        $(this).next().show();
    });
    collapseItems.clear();
    updateCollapsedSections();
}

function collapseAllSections() {
    collapseItems.clear();
    $('#details-table .details-info-header').each(function() {
        $(this).removeClass('panel-head-active');
        $(this).next().hide();
        collapseItems.add($(this).attr('data-section-id'));
    });
    updateCollapsedSections();
}

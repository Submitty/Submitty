/* global courseUrl */
/* exported gradeableMessageAgree, gradeableMessageCancel, showGradeableMessage, hideGradeableMessage, expandAllSections, collapseAllSections */
const MOBILE_BREAKPOINT = 951;

let COLLAPSE_ITEMS_KEY;
let collapseItems;
$(document).ready(() => {
    COLLAPSE_ITEMS_KEY = `${courseUrl}_gradeable-details-collapse-groups`;
    let collapseItemLocal = localStorage.getItem(COLLAPSE_ITEMS_KEY);
    if (!collapseItemLocal) {
        collapseItemLocal = '[]';
    }
    collapseItems = new Set(JSON.parse(collapseItemLocal));
    collapseItems.forEach((val) => {
        $(`#${val}`).removeClass('panel-head-active');
        $(`#${val}`).next().hide();
    });

    // Attach the collapsible panel on details-table
    const ANIMATION_DURATION = 600;
    $('#details-table .details-info-header').click(function() {
        $(this).toggleClass('panel-head-active');
        const id = $(this).attr('id');
        if (collapseItems.has(id)) {
            collapseItems.delete(id);
        }
        else {
            collapseItems.add(id);
        }
        localStorage.setItem(COLLAPSE_ITEMS_KEY, JSON.stringify([...collapseItems]));
        if (window.innerWidth < MOBILE_BREAKPOINT) {
            $(this).next().slideToggle({
                duration: ANIMATION_DURATION,
            });
        }
        else {
            $(this).next().toggle();
        }
    });

    // Creating and adding style for the psuedo selector in the details-table
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

function expandAllSections() {
    $('#details-table .details-info-header').each(function() {
        $(this).addClass('panel-head-active');
        $(this).next().show();
    });
    collapseItems.clear();
    localStorage.setItem(COLLAPSE_ITEMS_KEY, JSON.stringify([...collapseItems]));
}

function collapseAllSections() {
    collapseItems.clear();
    $('#details-table .details-info-header').each(function() {
        $(this).removeClass('panel-head-active');
        $(this).next().hide();
        collapseItems.add($(this).attr('id'));
    });
    localStorage.setItem(COLLAPSE_ITEMS_KEY, JSON.stringify([...collapseItems]));
}

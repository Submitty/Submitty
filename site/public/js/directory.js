/* global captureTabInModal, showPopup */
/* exported newDownloadForm, newClassListForm, newGradeableJsonForm, newGraderListForm, editRegistrationSectionsForm */
// Modals for StudentList and GraderList

function newDownloadForm() {
    $('.popup-form').css('display', 'none');
    const form = $('#download-form');
    showPopup('#download-form');
    captureTabInModal('download-form');
    form.find('.form-body').scrollTop(0);
    $('#download-form input:checkbox').each(function () {
        if ($(this).val() === 'NULL') {
            $(this).prop('checked', false);
        }
        else {
            $(this).prop('checked', true);
        }
    });
    $('#registration_section_1').focus();
}

function newClassListForm() {
    $('.popup-form').css('display', 'none');
    const form = $('#class-list-form');
    showPopup('#class-list-form');
    captureTabInModal('class-list-form');
    form.find('.form-body').scrollTop(0);
    $('[name="move_missing"]', form).prop('checked', false);
    $('[name="upload"]', form).val(null);
    $('#move_missing').focus();
}

function newGradeableJsonForm() {
    $('.popup-form').css('display', 'none');
    const form = $('#gradeable-json-form');
    form.css('display', 'block');
    captureTabInModal('gradeable-json-form');
    form.find('.form-body').scrollTop(0);
    $('[name="upload"]', form).val(null);
}

function newGraderListForm() {
    $('.popup-form').css('display', 'none');
    const form = $('#grader-list-form');
    showPopup('#grader-list-form');
    captureTabInModal('grader-list-form');
    form.find('.form-body').scrollTop(0);
    $('[name="upload"]', form).val(null);
    $('#grader-list-upload').focus();
}

function editRegistrationSectionsForm() {
    const form = $('#registration-sections-form');
    showPopup('#registration-sections-form');
    captureTabInModal('registration-sections-form');
    form.find('.form-body').scrollTop(0);
    $('#instructor_all').focus();
}

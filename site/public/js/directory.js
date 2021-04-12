// Modals for StudentList and GraderList

function newDownloadForm() {
    $('.popup-form').css('display', 'none');
    var form = $('#download-form');
    form.css('display', 'block');
    captureTabInModal("download-form");
    form.find('.form-body').scrollTop(0);
    $("#download-form input:checkbox").each(function() {
        if ($(this).val() === 'NULL') {
            $(this).prop('checked', false);
        }
        else {
            $(this).prop('checked', true);
        }
    });
    $("#registration_section_1").focus();
}

function newSettingsForm(){
    $('.popup-form').css('display', 'none');
    var form = $('#settings-form');
    form.css('display', 'block');
    captureTabInModal('settings-form');
    form.find('.form-body').scrollTop(0);
    $('#settings-form input:checkbox').each(function() {
        if ($(this).val() == 'NULL') {
            $(this).prop('checked', false);
        }else{
            $(this).prop('checked', true);
        }
    });
    $('#registration_section_1').focus();

}

function newClassListForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#class-list-form");
    form.css("display", "block");
    captureTabInModal("class-list-form");
    form.find('.form-body').scrollTop(0);
    $('[name="move_missing"]', form).prop('checked', false);
    $('[name="upload"]', form).val(null);
    $("#move_missing").focus();
}

function newGraderListForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#grader-list-form");
    form.css("display", "block");
    captureTabInModal("grader-list-form");
    form.find('.form-body').scrollTop(0);
    $('[name="upload"]', form).val(null);
    $("#grader-list-upload").focus();
}

function editRegistrationSectionsForm() {
    var form = $("#registration-sections-form");
    form.css("display","block");
    captureTabInModal("registration-sections-form");
    form.find('.form-body').scrollTop(0);
    $("#instructor_all").focus();
}

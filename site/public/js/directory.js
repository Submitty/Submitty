// Modals for StudentList and GraderList

function newDownloadForm() {
    $('.popup-form').css('display', 'none');
    var form = $('#download-form');
    form.css('display', 'block');
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

function newClassListForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#class-list-form");
    form.css("display", "block");
    $('[name="move_missing"]', form).prop('checked', false);
    $('[name="upload"]', form).val(null);
    $("#move_missing").focus();
}

function newGraderListForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#grader-list-form");
    form.css("display", "block");
    $('[name="upload"]', form).val(null);
    $("#grader-list-upload").focus();
}

function editRegistrationSectionsForm() {
    var form = $("#registration-sections-form");
    form.css("display","block");
    $("#instructor_all").focus();
}

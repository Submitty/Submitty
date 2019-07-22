function userFormChange() {
    var user_elem = $("select[name='user_group']")[0];
    var is_student = user_elem.options[user_elem.selectedIndex].text === "Student";

    var regis_elem = $("select[name='registered_section']")[0];
    var is_no_regis = regis_elem.options[regis_elem.selectedIndex].text === "Not Registered";

    if(is_student && is_no_regis) {
        $("#student-error-message").show();
    }
    else {
        $("user-form-#student-error-message").hide();
    }
    if(is_student) {
        $("#user-form-assigned-sections").hide();
    }
    else {
        $("#user-form-assigned-sections").show();
    }
}

function editUserForm(user_id) {
    var url = buildNewCourseUrl(['users', user_id]);
    $.ajax({
        url: url,
        success: function(data) {
            var json = JSON.parse(data)['data'];
            var form = $("#edit-user-form");
            form.css("display", "block");
            $('[name="edit_user"]', form).val("true");
            var user = $('[name="user_id"]', form);
            user.val(json['user_id']);
            user.attr('readonly', 'readonly');
            if (!user.hasClass('readonly')) {
                user.addClass('readonly');
            }
            $('[name="user_numeric_id"]', form).val(json['user_numeric_id']);
            $('[name="user_firstname"]', form).val(json['user_firstname']);
            if (json['user_preferred_firstname'] === null) {
                json['user_preferred_firstname'] = "";
            }
            $('[name="user_preferred_firstname"]', form).val(json['user_preferred_firstname']);
            $('[name="user_lastname"]', form).val(json['user_lastname']);
            if (json['user_preferred_lastname'] === null) {
                json['user_preferred_lastname'] = "";
            }
            $('[name="user_preferred_lastname"]', form).val(json['user_preferred_lastname']);
            $('[name="user_email"]', form).val(json['user_email']);
            var registration_section;
            if (json['registration_section'] === null) {
                registration_section = "null";
            }
            else {
                registration_section = json['registration_section'].toString();
            }
            var rotating_section;
            if (json['rotating_section'] === null) {
                rotating_section = "null";
            }
            else {
                rotating_section = json['rotating_section'].toString();
            }
            $('[name="registered_section"] option[value="' + registration_section + '"]', form).prop('selected', true);
            $('[name="rotating_section"] option[value="' + rotating_section + '"]', form).prop('selected', true);
            $('[name="manual_registration"]', form).prop('checked', json['manual_registration']);
            $('[name="user_group"] option[value="' + json['user_group'] + '"]', form).prop('selected', true);
            $("[name='grading_registration_section[]']").prop('checked', false);
            if (json['grading_registration_sections'] !== null && json['grading_registration_sections'] !== undefined) {
                json['grading_registration_sections'].forEach(function(val) {
                    $('#grs_' + val).prop('checked', true);
                });
            }
            if(registration_section === 'null' && json['user_group'] === 4) {
                $('#user-form-student-error-message').css('display', 'block');
            }
            else {
                $('#user-form-student-error-message').css('display', 'none');
            }
            if(json['user_group'] == 4) {
                $('#user-form-assigned-sections').hide();
            }
            else {
                $('#user-form-assigned-sections').show();
            }

        },
        error: function() {
            alert("Could not load user data, please refresh the page and try again.");
        }
    })
}

function newUserForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#edit-user-form");
    form.css("display", "block");
    $('[name="edit_user"]', form).val("false");
    $('[name="user_id"]', form).removeClass('readonly').prop('readonly', false).val("");
    $('[name="user_numeric_id"]', form).val("");
    $('[name="user_firstname"]', form).val("");
    $('[name="user_preferred_firstname"]', form).val("");
    $('[name="user_lastname"]', form).val("");
    $('[name="user_email"]', form).val("");
    $('[name="registered_section"] option[value="null"]', form).prop('selected', true);
    $('[name="rotating_section"] option[value="null"]', form).prop('selected', true);
    $('[name="manual_registration"]', form).prop('checked', true);
    $('[name="user_group"] option[value="4"]', form).prop('selected', true);
    $("[name='grading_registration_section[]']").prop('checked', false);
    $('#user-form-student-error-message').css('display', 'block');
    $('#user-form-assigned-sections').css('display', 'none');
}
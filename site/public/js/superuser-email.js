/* exported sendEmail */
/* exported updateSuperuserEmailOptions */
/* eslint no-undef: "off" */


function sendEmail(url) {
    const emailContent = $('#email-content').val();
    const emailSubject = $('#email-subject').val();
    // Check checkboxes for options
    const emailInstructor = $('#email-instructor').is(':checked');
    const emailFullAcess = $('#email-full-access').is(':checked');
    const emailLimitedAccess = $('#email-limited-access').is(':checked');
    const emailStudent = $('#email-student').is(':checked');
    const emailToSecondary = $('#email-to-secondary').is(':checked');
    const emailFaculty = $('#email-faculty').is(':checked');
    $('#email-content').prop('disabled', true);
    $('#send-email').prop('disabled', true);
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            'email_content': emailContent,
            'email_subject': emailSubject,
            'email_full_access': emailFullAcess,
            'email_limited_access': emailLimitedAccess,
            'email_instructor': emailInstructor,
            'email_student': emailStudent,
            'email_to_secondary': emailToSecondary,
            'email_faculty': emailFaculty,
            csrf_token: csrfToken,
        },
        cache: false,
        error: function(err) {
            window.alert('Something went wrong. Please try again.');
            console.error(err);
        },
        success: function(data) {
            try {
                const parsedData = JSON.parse(data);
                if (parsedData['status'] == 'success') {
                    $('#email-content').val('');
                    $('#email-subject').val('');
                    displaySuccessMessage(parsedData['data']['message']);
                }
                else {
                    displayErrorMessage(parsedData['message']);
                }
                $('#email-content').prop('disabled', false);
                $('#send-email').prop('disabled', false);
            }
            catch (e) {
                console.error(e);
            }
        },
    });
}


function updateSuperuserEmailOptions(which) {
    const instructor = $('#email-instructor');
    const full = $('#email-full-access');
    const limited = $('#email-limited-access');
    const student = $('#email-student');
    const faculty = $('#email-faculty');

    if (which == 'instructor') {
        if (!instructor.prop('checked')) {
            full.prop('checked',false);
            limited.prop('checked',false);
            student.prop('checked',false);
        }
    }
    else if (which == 'full-access') {
        if (full.prop('checked')) {
            instructor.prop('checked',true);
        }
        else {
            limited.prop('checked',false);
            student.prop('checked',false);
        }
    }
    else if (which == 'limited-access') {
        if (limited.prop('checked')) {
            instructor.prop('checked',true);
            full.prop('checked',true);
        }
        else {
            student.prop('checked',false);
        }
    }
    else if (which == 'student') {
        if (student.prop('checked')) {
            instructor.prop('checked',true);
            full.prop('checked',true);
            limited.prop('checked',true);
        }
    }

    if (!(instructor.prop('checked') || full.prop('checked') || limited.prop('checked') || student.prop('checked') || faculty.prop('checked'))) {
        $('#send-email').prop('disabled', true);
        $('#email-warning').show();
    }
    else {
        $('#send-email').prop('disabled', false);
        $('#email-warning').hide();
    }
}

$(document).ready(() => {
    $('#email-warning').hide();
});

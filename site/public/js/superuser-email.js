function getCurrentSemester() {
    const today = new Date();
    const year = today.getFullYear().toString().slice(2,4);	//get last two digits
    const semester = ((today.getMonth() + 1) < 7) ? 's' : 'f';	//first half of year 'spring' rest is fall

    return semester + year;
}

function sendEmail(url) {
    const emailContent = $('#email-content').val();
    const emailSubject = $('#email-subject').val();
    // Check checkboxes for options
    const emailInstructor = $('#email-instructor').is(':checked');
    const emailFullAcess = $('#email-full-access').is(':checked');
    const emailLimitedAccess = $('#email-limited-access').is(':checked');
    const emailStudent = $('#email-student').is(':checked');
    const emailToSecondary = $('#email-to-secondary').is(':checked');
    $('#email-content').prop('disabled', true);
    $('#send-email').prop('disabled', true);
    console.log(url);
    console.log(getCurrentSemester());
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            'emailContent': emailContent,
            'emailSubject': emailSubject,
            'semester': getCurrentSemester(),
            'emailFullAccess': emailFullAcess,
            'emailLimitedAccess': emailLimitedAccess,
            'emailInstructor': emailInstructor,
            'emailStudent': emailStudent,
            'emailToSecondary': emailToSecondary,
            csrf_token: csrfToken
        },
        cache: false,
        error: function(err) {
            window.alert('Something went wrong. Please try again.');
            console.error(err);
        },
        success: function(data){
            console.log(data);
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
    });
    console.log(emailContent);
}
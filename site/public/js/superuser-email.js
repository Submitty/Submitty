function getCurrentSemester() {
    const today = new Date();
    const year = today.getFullYear().toString().slice(2,4);	//get last two digits
    const semester = ((today.getMonth() + 1) < 7) ? 's' : 'f';	//first half of year 'spring' rest is fall

    return semester + year;
}

function sendEmail(url) {
    let emailContent = $('#email-content').val();
    let emailSubject = $('#email-subject').val();
    // Check checkboxes for options
    let emailInstructor = $('#email-instructor').is(':checked');
    let emailFullAcess = $('#email-full-access').is(':checked');
    let emailLimitedAccess = $('#email-limited-access').is(":checked");
    let emailStudent = $('#email-student').is(':checked');
    let emailToSecondary = $('#email-to-secondary').is(':checked');
    $('#email-content').prop('disabled', true);
    $('#send-email').prop('disabled', true);
    console.log(url);
    console.log(getCurrentSemester());
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            "emailContent": emailContent,
            "emailSubject": emailSubject,
            "semester": getCurrentSemester(),
            "emailFullAccess": emailFullAcess,
            "emailLimitedAccess": emailLimitedAccess,
            "emailInstructor": emailInstructor,
            "emailStudent": emailStudent,
            "emailToSecondary": emailToSecondary,
            csrf_token: csrfToken
        },
        cache: false,
        error: function(err) {
            window.alert("Something went wrong. Please try again.");
            console.error(err);
        },
        success: function(data){
            console.log(data);
            let parsedData = JSON.parse(data);
            if (parsedData["status"] == "success") {
                $('#email-content').val("");
                $('#email-subject').val("");
                displaySuccessMessage(parsedData["data"]["message"]);
            }
            else {
                displayErrorMessage(parsedData["message"]);
            }
            $('#email-content').prop('disabled', false);
            $('#send-email').prop('disabled', false);
        }
    });
    console.log(emailContent);
}
/* exported sendEmail */
/* eslint no-undef: "off" */

function sendEmail(url) {
    const options = $("[name='email-options']");
    console.log(options);
    $('#email-content').prop('disabled', true);
    $('#send-email').prop('disabled', true);
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            'optons': options,
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

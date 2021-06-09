import {getCurrentSemester} from '../../cypress/support/utils.js';
function sendEmail(url){
    let emailContent = $('#email-content').val();
    $('#email-content').prop('disabled', true);
    console.log(url);
    let request = $.ajax({
        url: url,
        type: 'POST',
        data: {
            "emailContent": emailContent,
            "semester": getCurrentSemester()
        },
        cache: false,
        error: function(err) {
            console.error(err);
            window.alert("Something went wrong. Please try again.");
        },
        success: function(){
            console.log("Success!");
            $('#email-content').val("");
            $('#email-content').prop('disabled', false);
        }
    });
    console.log(emailContent);
}
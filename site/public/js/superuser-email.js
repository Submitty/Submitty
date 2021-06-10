function getCurrentSemester() {
    const today = new Date();
    const year = today.getFullYear().toString().slice(2,4);	//get last two digits
    const semester = ((today.getMonth() + 1) < 7) ? 's' : 'f';	//first half of year 'spring' rest is fall

    return semester + year;
}

function sendEmail(url) {
    let emailContent = $('#email-content').val();
    $('#email-content').prop('disabled', true);
    console.log(url);
    console.log(getCurrentSemester());
    let request = $.ajax({
        url: url,
        type: 'POST',
        data: {
            "emailContent": emailContent,
            "semester": getCurrentSemester(),
            csrf_token: csrfToken
        },
        cache: false,
        error: function(err) {
            window.alert("Something went wrong. Please try again.");
        },
        success: function(data){
            console.log(data);
            let parsedData = JSON.parse(data);
            $('#email-content').val("");
            $('#email-content').prop('disabled', false);
            if (parsedData["status"] == "success") {
                displaySuccessMessage(parsedData["data"]["message"]);
            }
            else {
                displayErrorMessage(parsedData["message"]);
            }
        }
    });
    console.log(emailContent);
}
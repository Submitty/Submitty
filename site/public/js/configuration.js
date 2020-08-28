$(document).ready(function() {

    $("input,textarea,select").on("change", function() {
        var elem = this;
        let formData = new FormData();
        formData.append('csrf_token', csrfToken);
        let entry;
        if(this.type === "checkbox") {
            entry = $(elem).is(":checked");
        }
        else {
            entry = elem.value;
        }
        formData.append("name", elem.name);
        formData.append("entry", entry);
        $.ajax({
            url: buildCourseUrl(['config']),
            data: formData,
            type: "POST",
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    response = JSON.parse(response);

                }
                catch (exc) {
                    console.log(response);
                    response = {
                        status: 'fail',
                        message: 'invalid response received from server'
                    }
                }
                if (response['status'] === 'fail') {
                    alert(response['message']);
                    $(elem).focus();
                    elem.value = $(elem).attr("value");

                    // Ensure auto_rainbow_grades checkbox reverts to unchecked if it failed validation
                    if($(elem).attr('name') == 'auto_rainbow_grades') {
                        $(elem).prop('checked', false);
                    }
                }
                $(elem).attr("value", elem.value);
            }
        });
    });

    function updateForumMessage() {
        $("#forum-enabled-message").toggle();
    }

    $(document).on("change", "#forum-enabled", updateForumMessage);

    function showEmailSeatingOption() {
        $("#email-seating-assignment").show();
        $("#email-seating-assignment_label").show();
    }

    function hideEmailSeatingOption() {
        $("#email-seating-assignment").hide();
        $("#email-seating-assignment-label").hide();
    }

    function updateEmailSeatingOption() {
        if ($("#room-seating-gradeable-id").val()) {
            showEmailSeatingOption();
        }
        else {
            hideEmailSeatingOption();
        }
    }

    updateEmailSeatingOption();

    $(document).on("change", "#room-seating-gradeable-id", updateEmailSeatingOption);
});

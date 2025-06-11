/* global csrfToken, buildCourseUrl */

$(document).ready(() => {
    $('input,textarea,select').on('change', function () {
        const elem = this;
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        let entry;
        let default_section;
        if (this.type === 'checkbox') {
            entry = $(elem).is(':checked');
            if (this.id === 'all-self-registration') {
                default_section = $('#default-section-id').val();
                formData.append('default_section', default_section);
            }
        }
        else {
            entry = elem.value;
        }
        formData.append('name', elem.name);
        formData.append('entry', entry);
        $.ajax({
            url: buildCourseUrl(['config']),
            data: formData,
            type: 'POST',
            processData: false,
            contentType: false,
            success: function (response) {
                try {
                    response = JSON.parse(response);
                }
                catch (exc) {
                    response = {
                        status: 'fail',
                        message: 'invalid response received from server',
                    };
                }
                if (response['status'] === 'fail') {
                    alert(response['message']);
                    $(elem).focus();
                    elem.value = $(elem).attr('value');

                    // Ensure auto_rainbow_grades checkbox reverts to unchecked if it failed validation
                    if ($(elem).attr('name') === 'auto_rainbow_grades') {
                        $(elem).prop('checked', false);
                    }
                }
                $(elem).attr('value', elem.value);
            },
        });
    });

    function updateForumMessage() {
        $('#forum-enabled-message').toggle();
    }

    $(document).on('change', '#forum-enabled', updateForumMessage);

    function showEmailSeatingOption() {
        $('#email-seating-assignment').show();
        $('#email-seating-assignment_label').show();
    }

    function hideEmailSeatingOption() {
        $('#email-seating-assignment').hide();
        $('#email-seating-assignment-label').hide();
    }

    function updateEmailSeatingOption() {
        if ($('#room-seating-gradeable-id').val()) {
            showEmailSeatingOption();
        }
        else {
            hideEmailSeatingOption();
        }
    }

    updateEmailSeatingOption();

    $(document).on('change', '#room-seating-gradeable-id', updateEmailSeatingOption);

    function updateRainbowCustomizationWarning() {
        const warningMessage = $('#customization-exists-warning');
        const checked = $('#auto-rainbow-grades').is(':checked');
        const customizationNotExists = warningMessage.data('value');
        warningMessage.toggle(checked && customizationNotExists);
    }

    $(document).on('change', '#auto-rainbow-grades', updateRainbowCustomizationWarning);
});

function confirmSelfRegistration(element, needs_reg_sections) {
    if (needs_reg_sections) {
        alert('You need to create at least one registration section first');
        return false;
    }
    if ($('#default-section-id').val() === '') {
        alert('You need to select a registration section first');
        return false;
    }

    return !element.checked ? true : confirm('Are you sure you want to enable self registration to this course? This allows ALL users (even those manually removed from the course) to register for this course.');
}

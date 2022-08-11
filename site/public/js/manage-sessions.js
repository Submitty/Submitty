function removeRowTrigger(elem) {
    const tr_elem = elem.parent().parent();
    const table_elem = tr_elem.parent();
    tr_elem.remove();
    table_elem.trigger('change');
}

// eslint-disable-next-line no-unused-vars
function terminateAll(csrf_token) {
    const terminate_button = $('#terminate-all-button');
    terminate_button.prepend('<i class="fas fa-spinner fa-spin ajax-load"></i>');
    const data = new FormData();
    data.append('csrf_token', csrf_token);
    // eslint-disable-next-line no-undef
    const url = buildUrl(['manage_sessions', 'terminate_all']);
    $.ajax({
        url,
        type: 'POST',
        data,
        processData: false,
        contentType: false,
        success: (res) => {
            const response = JSON.parse(res);
            if (response.status === 'success') {
                $('.other-session').each((index, element) => {
                    removeRowTrigger($(element));
                });
                // eslint-disable-next-line no-undef
                displaySuccessMessage(response.data);
            }
            else if (response.status === 'error') {
                // eslint-disable-next-line no-undef
                displayErrorMessage(response.message);
            }
            $('.fa-spinner', terminate_button).remove();
        },
        error: function() {
            // eslint-disable-next-line no-undef
            displayErrorMessage('Something went wrong while terminating the sessions!');
            $('.fa-spinner', terminate_button).remove();
        },
    });
    return false;
}

// eslint-disable-next-line no-unused-vars
function setSecureSession(elem, e, csrf_token) {
    e.preventDefault();
    const enforce_checkbox = $(elem);
    enforce_checkbox.hide();
    enforce_checkbox.after('<i class="fas fa-spinner fa-spin ajax-load"></i>');
    const is_checked = enforce_checkbox.prop('checked');
    const data = new FormData();
    data.append('secure_session', is_checked);
    data.append('csrf_token', csrf_token);
    // eslint-disable-next-line no-undef
    const url = buildUrl(['manage_sessions', 'update_secure_session']);
    $.ajax({
        url,
        type: 'POST',
        data,
        processData: false,
        contentType: false,
        success: function(res) {
            const response = JSON.parse(res);
            if (response.status === 'success') {
                const { data } = response;
                // eslint-disable-next-line no-undef
                displaySuccessMessage(`Secure session set to ${data.secure_session.toString()}.`);
                if (data.secure_session) {
                    enforce_checkbox.prop('checked', true);
                    $('.other-session').each((index, element) => {
                        removeRowTrigger($(element));
                    });
                }
                else {
                    enforce_checkbox.prop('checked', false);
                }
            }
            else if (response.status === 'error') {
                // eslint-disable-next-line no-undef
                displayErrorMessage(response.message);
            }
            $('.fa-spinner', enforce_checkbox.parent()).remove();
            enforce_checkbox.show();
        },
        error: function() {
            // eslint-disable-next-line no-undef
            displayErrorMessage('Something went wrong while updating secure session setting!');
            $('.fa-spinner', enforce_checkbox.parent()).remove();
            enforce_checkbox.show();
        },
    });
}

// eslint-disable-next-line no-unused-vars
function terminateSession(elem, csrf_token) {
    const data = new FormData();
    data.append('session_id', $('input[name=session_id]', $(elem)).val());
    data.append('csrf_token', csrf_token);
    // eslint-disable-next-line no-undef
    const url = buildUrl(['manage_sessions', 'terminate']);
    $.ajax({
        url,
        type: 'POST',
        data,
        processData: false,
        contentType: false,
        success: function(res) {
            const response = JSON.parse(res);
            if (response.status === 'success') {
                const { data } = response;
                // eslint-disable-next-line no-undef
                displaySuccessMessage(data.message);
                removeRowTrigger($(elem));
            }
            else if (response.status === 'error') {
                // eslint-disable-next-line no-undef
                displayErrorMessage(response.message);
            }
        },
        error: function() {
            // eslint-disable-next-line no-undef
            displayErrorMessage('Something went wrong while terminating the session!');
        },
    });
    return false;
}

$(() => {
    const sessions_table = $('#sessions-table');
    sessions_table.on('change', function() {
        if ($('tr', $(this)).length < 3) {
            $('#terminate-all-button').attr('disabled', true);
            $('#terminate-all-button').attr('title', 'Only one session (current) is active.');
        }
    });
    sessions_table.trigger('change');
});

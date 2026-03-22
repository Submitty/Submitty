/* global csrfToken, buildCourseUrl, displayErrorMessage, displayWarningMessage */

let updateInProgressCount = 0;
const errors = {};
let previous_gradeable = '';
let gradeable = '';
let rebuild_triggered = false;

function updateErrorMessage() {
    const saveStatus = $('#save_status');
    saveStatus.removeClass('save-success save-error save-pending');
    if (Object.keys(errors).length !== 0) {
        saveStatus.html('<i class="fas fa-exclamation-triangle"></i> Some Changes Failed!').addClass('save-error');
    }
    else if (updateInProgressCount === 0) {
        saveStatus.html('<i class="fas fa-check-circle"></i> All Changes Saved').addClass('save-success');
    }
}

function setError(name, err) {
    if (name === 'autograding_config_path') {
        name = 'autograding_config_path_displayed';
        const error_elem = $('#autograding_config_error');
        error_elem.text(err);
        error_elem.show();
    }
    $(`[name="${name}"]`).each((i, elem) => {
        elem.title = err;
        elem.setCustomValidity('Invalid field.');
    });
    errors[name] = err;
}

function clearError(name, update) {
    if (name === 'autograding_config_path') {
        name = 'autograding_config_path_displayed';
        const error_elem = $('#autograding_config_error');
        error_elem.text('');
        error_elem.hide();
    }
    $(`[name="${name}"]`).each((i, elem) => {
        elem.title = '';
        elem.setCustomValidity('');

        // Update the value if provided
        if (update !== undefined) {
            $(elem).val(update);
        }
    });
    // remove the error for this property
    delete errors[name];
}

function setGradeableUpdateInProgress() {
    const saveStatus = $('#save_status');
    saveStatus.removeClass('save-success save-error save-pending');
    saveStatus.html('<i class="fas fa-spinner fa-spin"></i> Saving...').addClass('save-pending');
    updateInProgressCount++;
}

function setGradeableUpdateComplete() {
    updateInProgressCount--;
}

function updateGradeableErrorCallback(message, response_data) {
    for (const key in response_data) {
        if (Object.prototype.hasOwnProperty.call(response_data, key)) {
            setError(key, response_data[key]);
        }
    }
    updateErrorMessage();
}

function parseUpdateGradeableResponseArray(response, gradeable_id) {
    // Trigger periodic checks for latest rebuild status
    if (response.includes('rebuild_queued')) {
        rebuild_triggered = true;
        ajaxCheckBuildStatus(gradeable_id, 'unknown');
    }
}

function getGradeableId() {
    return $('#g_id').val();
}

function ajaxUpdateGradeableProperty(gradeable_id, p_values, successCallback, errorCallback) {
    const container = $('#container-rubric');
    if (container.length === 0) {
        alert('UPDATES DISABLED: no \'container-rubric\' element!');
        return;
    }
    // Don't process updates until the page is done loading
    if (!container.is(':visible')) {
        return;
    }
    setGradeableUpdateInProgress();
    $.getJSON({
        type: 'POST',
        url: buildCourseUrl(['gradeable', gradeable_id, 'update']),
        data: p_values,
        success: function (response) {
            if (Array.isArray(response['data'])) {
                parseUpdateGradeableResponseArray(response['data'], gradeable_id);
            }
            setGradeableUpdateComplete();
            if (response.status === 'success') {
                successCallback(response.data);
                updateErrorMessage();
            }
            else if (response.status === 'fail') {
                errorCallback(response.message, response.data);
            }
            else {
                alert('Internal server error');
                console.error(response);
            }
        },
        error: function (response) {
            setGradeableUpdateComplete();
            console.error('Failed to parse response from server: ', response);
        },
    });
}

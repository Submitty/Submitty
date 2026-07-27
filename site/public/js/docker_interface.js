/* exported collapseSection, confirmationDialog, removeImage, addImage, updateImage */
/* global csrfToken, displayErrorMessage, displaySuccessMessage */

let isUpdateInProgress = false;

/**
* toggles visibility of a content sections on the Docker UI
* @param {string} id of the section to toggle
* @param {string} btn_id id of the button calling this function
*/
function collapseSection(id, btn_id) {
    const tgt = document.getElementById(id);
    const btn = document.getElementById(btn_id);

    if (tgt.style.display === 'block') {
        tgt.style.display = 'none';
        btn.innerHTML = 'Expand';
    }
    else {
        tgt.style.display = 'block';
        btn.innerHTML = 'Collapse';
    }
}

function filterOnClick() {
    const this_filter = $(this).data('capability');

    $('.filter-buttons').each(function () {
        $(this).addClass('fully-transparent');
    });

    $(this).removeClass('fully-transparent');

    $('.image-row').each(function () {
        const this_row = $(this);
        let hide = true;
        $(this).find('.badge').each(function () {
            if ($(this).text() === this_filter) {
                hide = false;
            }
        });
        if (hide) {
            this_row.hide();
        }
        else {
            this_row.show();
        }
    });
}

function showAll() {
    $('.image-row').show();
    $('.filter-buttons').removeClass('fully-transparent');
}

function addFieldOnChange() {
    const command = $(this).val();
    const regex = new RegExp('^[a-z0-9]+[a-z0-9._(__)-]*[a-z0-9]+/[a-z0-9]+[a-z0-9._(__)-]*[a-z0-9]+:[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}$');
    if (!regex.test(command)) {
        $('#send-button').attr('disabled', true);
        if (command !== '') {
            $('#docker-warning').css('display', '');
        }
    }
    else {
        $('#send-button').attr('disabled', false);
        $('#docker-warning').css('display', 'none');
    }
}

function confirmationDialog(url, id) {
    if (confirm(`Are you sure you want to remove ${id} image?`)) {
        removeImage(url, id);
    }
}

function removeImage(url, id) {
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            image: id,
            csrf_token: csrfToken,
        },
        success: (data) => {
            const json = JSON.parse(data);
            if (json.status === 'success') {
                $('#add-field').val('');
                $('#docker-status-badge').text(`${id} has been removed from the configuration! Click "Update dockers and machines" to apply the changes.`);
                setDockerStatusBadge(true);
                displaySuccessMessage(json.data);
            }
            else {
                displayErrorMessage(json.message);
            }
        },
        error: (err) => {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}

function addImage(url) {
    const capability = $('#capability-form').val();
    const image = $('#add-field').val();
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            capability: capability,
            image: image,
            csrf_token: csrfToken,
        },
        success: (data) => {
            const json = JSON.parse(data);
            if (json.status === 'success') {
                $('#add-field').val('');
                $('#docker-status-badge').text(`${image} has been added to the configuration! Click "Update dockers and machines" to apply the changes.`);
                setDockerStatusBadge(true);
                displaySuccessMessage(json.data);
            }
            else {
                displayErrorMessage(json.message);
            }
        },
        error: (err) => {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}
/**
 * @param {string} logContent
 */
function showDockerLogButton(logContent) {
    $('#show-docker-log-button').show();
    $('#docker-status-log').empty();
    if (logContent) {
        $('#docker-status-log').append(`<pre>${logContent}</pre>`);
    }
}

/**
 * @param {boolean} updateNeeded
 */
function setDockerStatusBadge(updateNeeded) {
    const badge = $('#docker-status-badge');
    badge.removeClass('btn-danger btn-success');
    if (updateNeeded) {
        badge.addClass('btn-danger');
    }
    else {
        badge.addClass('btn-success');
    }
}

function updateImage() {
    if (!window.dockerAdminUrl) {
        return;
    }

    isUpdateInProgress = true;

    $('#docker-status-badge').text('Changes applying...');

    $.ajax({
        url: `${window.dockerAdminUrl}/update_docker`,
        type: 'POST',
        data: {
            csrf_token: csrfToken,
        },
        success: (data) => {
            const response = JSON.parse(data);
            if (response.status === 'success') {
                checkDockerUpdateStatus();
            }
            else {
                displayErrorMessage(response.message);
                $('#docker-status-badge').text('An error occurred while updating');
                showDockerLogButton(response.data.log);
            }
        },
        error: (err) => {
            console.error(err);
            window.alert('Something went wrong. Please try again.')
            $('#docker-status-badge').text('Something went wrong. Please try again.');
        },
    });
}

/**
 * checks for changes while an update is in progress and applies them to the images table
 */
function checkDockerUpdateStatus() {
    $.ajax({
        type: 'POST',
        url: `${window.dockerAdminUrl}/docker_update_status`,
        data: { csrf_token: csrfToken },
        dataType: 'json',
        success: (response) => {
            if (response.status === 'success') {
                if (response.data && response.data.in_progress) {
                    $('#docker-status-badge').text('Changes applying...');
                    checkDockerUpdateStatus();
                    return;
                }

                isUpdateInProgress = false;
                $('#docker-status-badge').text('Changes applied, manually reload the page to view them!');
                showDockerLogButton(response.data.log);
                setDockerStatusBadge(false);
            }
            else if (response.status === 'fail') {
                isUpdateInProgress = false;
                displayErrorMessage(response.data);
                $('#docker-status-badge').text('A failure occurred while applying changes');
                showDockerLogButton(response.data.log);
            }
        },
        error: (err) => {
            isUpdateInProgress = false;
            console.error(err);
            $('#docker-status-badge').text('A site error occurred while updating dockers and machines');
        },
    });
}

$(document).ready(() => {
    $('.filter-buttons').on('click', filterOnClick);
    $('#show-all').on('click', showAll);
    $('#add-field').on('input', addFieldOnChange);
    $('#add-field').trigger('input');

    // Toggle the log panel, same interaction as RainbowGrades' #show_log_button
    $('#show-docker-log-button').click(() => {
        $('#docker-status-log').toggle();
    });

    if (typeof window.dockerUpdateNeeded !== 'undefined') {
        setDockerStatusBadge(window.dockerUpdateNeeded);
    }
});

window.addEventListener('DOMContentLoaded', () => {
    const successMessage = sessionStorage.getItem('successMessage');
    if (successMessage) {
        displaySuccessMessage(successMessage);

        // Clear the message from sessionStorage so it doesn't show again
        sessionStorage.removeItem('successMessage');
    }
});

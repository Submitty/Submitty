/* exported collapseSection, confirmationDialog, removeImage, addImage, updateImage */
/* global csrfToken, displayErrorMessage, displaySuccessMessage */

let isUpdateInProgress = false;
const DOCKER_STATUS_BADGE = 'dockerStatusBadge';

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

/**
 * @param {string} logContent
 */
function showDockerLogButton(logContent) {
    $('#show-docker-log-button').show();
    const logs = $('#docker-status-log').empty();
    if (logContent) {
        $('<pre></pre>').text(logContent).appendTo(logs);
    }
}

/**
 * @param {string} text
 * @param {boolean} updateNeeded
 */
function setDockerStatusBadge(text, updateNeeded) {
    //TODO: add a third option / argument of btn-warning
    const badge = $('#docker-status-badge');
    badge.text(text);
    badge.removeClass('btn-danger btn-success');
    if (updateNeeded) {
        badge.addClass('btn-danger');
    }
    else {
        badge.addClass('btn-success');
    }
    sessionStorage.setItem(DOCKER_STATUS_BADGE, JSON.stringify({ text, updateNeeded }));
}

function restoreDockerStatusBadge() {
    const saved_status_badge = JSON.parse(sessionStorage.getItem(DOCKER_STATUS_BADGE));
    if (saved_status_badge) {
        setDockerStatusBadge(saved_status_badge.text, saved_status_badge.updateNeeded);
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
                setDockerStatusBadge(`${id} has been removed from the configuration! Click "Update dockers and machines" to apply the changes.`, true);
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
                setDockerStatusBadge(`${image} has been added to the configuration! Click "Update dockers and machines" to apply the changes.`, true);
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

function updateImage() {
    if (!window.dockerAdminUrl || isUpdateInProgress) {
        return;
    }

    isUpdateInProgress = true;

    setDockerStatusBadge('Changes applying...', true);

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
                setDockerStatusBadge('An error occurred while updating', true);
                showDockerLogButton(response.data.log);
            }
        },
        error: (err) => {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
            setDockerStatusBadge('Something went wrong. Please try again.', true);
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
                    setDockerStatusBadge('Changes applying...', true);
                    checkDockerUpdateStatus();
                    return;
                }

                isUpdateInProgress = false;
                setDockerStatusBadge('Changes applied, manually reload the page to view them!', false);
                showDockerLogButton(response.data.log);
            }
            else if (response.status === 'fail') {
                isUpdateInProgress = false;
                displayErrorMessage(response.data);
                setDockerStatusBadge('A failure occurred while applying changes', true);
                showDockerLogButton(response.data.log);
            }
        },
        error: (err) => {
            isUpdateInProgress = false;
            console.error(err);
            setDockerStatusBadge('A site error occurred while updating dockers and machines', true);
        },
    });
}

$(document).ready(() => {
    $('.filter-buttons').on('click', filterOnClick);
    $('#show-all').on('click', showAll);
    $('#add-field').on('input', addFieldOnChange).trigger('input');

    $('#show-docker-log-button').click(() => {
        $('#docker-status-log').toggle();
    });

    const saved_status_badge = sessionStorage.getItem(DOCKER_STATUS_BADGE);

    if (saved_status_badge) {
        if (window.dockerUpdateNeeded === false) {
            sessionStorage.removeItem(DOCKER_STATUS_BADGE);
        }
        else {
            restoreDockerStatusBadge();
        }
    }

    // TODO: Fixme
    const successMessage = sessionStorage.getItem('successMessage');
    if (successMessage) {
        displaySuccessMessage(successMessage);
        sessionStorage.removeItem('successMessage');
    }
});

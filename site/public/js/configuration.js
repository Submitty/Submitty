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

    const pullButton = $('#pull-course-repository');
    const stateElem = $('#course-repo-sync-state');
    const messageElem = $('#course-repo-sync-message');
    let pollingHandle = null;

    function renderRepoStatus(data) {
        const queueStatus = data.queue_status;
        const lastSync = data.last_sync;
        let stateText = '';
        if (queueStatus === 'processing') {
            stateText = 'Sync in progress...';
            pullButton.prop('disabled', true);
        }
        else if (queueStatus === 'queued') {
            stateText = 'Sync queued...';
            pullButton.prop('disabled', true);
        }
        else {
            pullButton.prop('disabled', false);
            if (lastSync && lastSync.status) {
                stateText = `Last sync: ${lastSync.status}`;
            }
            else {
                stateText = 'No sync has run yet.';
            }
        }
        stateElem.text(stateText);

        if (lastSync && lastSync.message) {
            let message = lastSync.message;
            if (lastSync.last_updated) {
                message += ` (updated ${lastSync.last_updated})`;
            }
            if (lastSync.commit) {
                message += ` commit ${lastSync.commit}`;
            }
            if (Array.isArray(lastSync.created_gradeables) || Array.isArray(lastSync.updated_gradeables)) {
                const createdCount = Array.isArray(lastSync.created_gradeables) ? lastSync.created_gradeables.length : 0;
                const updatedCount = Array.isArray(lastSync.updated_gradeables) ? lastSync.updated_gradeables.length : 0;
                message += ` | gradeables created: ${createdCount}, updated: ${updatedCount}`;
            }
            if (Array.isArray(lastSync.warnings) && lastSync.warnings.length > 0) {
                message += ` | warnings: ${lastSync.warnings.length}`;
            }
            messageElem.text(message);
        }
        else {
            messageElem.text('');
        }

        if (queueStatus === 'queued' || queueStatus === 'processing') {
            if (pollingHandle === null) {
                pollingHandle = setInterval(fetchRepoStatus, 3000);
            }
        }
        else if (pollingHandle !== null) {
            clearInterval(pollingHandle);
            pollingHandle = null;
        }
    }

    function fetchRepoStatus() {
        $.ajax({
            url: buildCourseUrl(['config', 'course_repository', 'status']),
            type: 'GET',
            success: function (response) {
                try {
                    response = JSON.parse(response);
                    if (response.status === 'success') {
                        renderRepoStatus(response.data);
                    }
                }
                catch (exc) {
                    // Ignore parse errors and keep previous status text.
                }
            },
        });
    }

    pullButton.on('click', () => {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        pullButton.prop('disabled', true);
        $.ajax({
            url: buildCourseUrl(['config', 'course_repository', 'pull']),
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
                if (response.status === 'fail') {
                    alert(response.message);
                    pullButton.prop('disabled', false);
                    return;
                }
                fetchRepoStatus();
            },
        });
    });

    fetchRepoStatus();
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

/* global csrfToken, buildCourseUrl, NONUPLOADED_CONFIG_VALUES, displayErrorMessage, displaySuccessMessage, gradeable_max_autograder_points,
          is_electronic, onHasReleaseDate, reloadInstructorEditRubric, getItempoolOptions,
          isItempoolAvailable, getGradeableId, closeAllComponents, onHasDueDate, setPdfPageAssignment,
          PDF_PAGE_INSTRUCTOR, PDF_PAGE_STUDENT, PDF_PAGE_NONE, displayWarningMessage, CodeMirror */
/* exported showBuildLog, ajaxRebuildGradeableButton, onPrecisionChange, onItemPoolOptionChange, updatePdfPageSettings,
          loadGradeableEditor, saveGradeableConfigEdit */

let updateInProgressCount = 0;
const errors = {};
let previous_gradeable = '';
let gradeable = '';
let rebuild_triggered = false;

function updateErrorMessage() {
    if (Object.keys(errors).length !== 0) {
        $('#save_status').text('Some Changes Failed!').css('color', 'red');
    }
    else {
        if (updateInProgressCount === 0) {
            $('#save_status').text('All Changes Saved').css('color', 'var(--text-black)');
        }
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
    $('#save_status').text('Saving...').css('color', 'var(--text-black)');
    updateInProgressCount++;
}

function setGradeableUpdateComplete() {
    updateInProgressCount--;
}

function updatePdfPageSettings() {
    const pdf_page = $('#yes_pdf_page').is(':checked');
    const pdf_page_student = $('#yes_pdf_page_student').is(':checked');
    if (pdf_page === false) {
        $('#no_pdf_page_student').prop('checked', true);
    }
    setPdfPageAssignment(pdf_page === false ? PDF_PAGE_NONE : (pdf_page_student === true ? PDF_PAGE_STUDENT : PDF_PAGE_INSTRUCTOR))
        .catch((err) => {
            alert(`Failed to update pdf page setting! ${err.message}`);
        });
}

function onItemPoolOptionChange(componentId) {
    const linkItemPool = $(`#yes-link-item-pool-${componentId}`);
    // Provide a select option for item-pool items on the rubric components

    if (linkItemPool.is(':checked')) {
        $(`#component-itempool-${componentId}-cont`).removeClass('hide');
    }
    else {
    // make all the rubric components available to each student
        $(`#component-itempool-${componentId}-cont`).addClass('hide');
    }
}

function onPrecisionChange() {
    ajaxUpdateGradeableProperty(getGradeableId(), {
        precision: $('#point_precision_id').val(),
        csrf_token: csrfToken,
    }, () => {
        // Clear errors by just removing red background
        clearError('precision');
        updateErrorMessage();

        closeAllComponents(true)
            .then(() => {
                return reloadInstructorEditRubric(getGradeableId(), isItempoolAvailable(), getItempoolOptions());
            })
            .catch((err) => {
                alert(`Failed to reload the gradeable rubric! ${err.message}`);
            });
    }, updateGradeableErrorCallback);
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

function updateDueDate() {
    const cont = $('#due_date_container');
    const cont1 = $('#late_days_options_container');
    const cont2 = $('#manual_grading_container');
    const cont3 = $('#release_container');
    if ($('#has_due_date_no').is(':checked')) {
        cont.hide();
        cont1.hide();
        cont2.hide();
        cont3.hide();
        $('#has_release_date_no').prop('checked', true);
    }
    else {
        cont.show();
        cont1.show();
        cont2.show();
        cont3.show();
    }
    onHasDueDate();
}

function updateReleaseDate() {
    const cont = $('#release_date_container');
    if ($('#has_release_date_no').is(':checked')) {
        cont.hide();
    }
    else {
        cont.show();
    }
    onHasReleaseDate();
}

$(document).ready(() => {
    window.onbeforeunload = function (event) {
        if (Object.keys(errors).length !== 0) {
            event.returnValue = 1;
        }
    };

    ajaxCheckBuildStatus();
    checkWarningBanners();
    $('input:not(#random-peer-graders-list,#number_to_peer_grade),select,textarea').change(function () {
        if ($(this).hasClass('date-radio') && is_electronic) {
            updateDueDate();
        }
        if ($(this).hasClass('date-radio')) {
            updateReleaseDate();
        }
        if ($(this).hasClass('ignore')) {
            return;
        }
        if (previous_gradeable === '') {
            previous_gradeable = $('#gradeable-lock').val();
        }
        gradeable = $('#gradeable-lock').val();
        if (previous_gradeable !== gradeable) {
            $('#gradeable-lock-points').val(0);
        }
        if (gradeable !== '') {
            $('#gradeable-lock-max-points-field').show();
            $('#gradeable-lock-max-points').text(`Out of ${gradeable_max_autograder_points[gradeable]} Maximum Autograding Points`);
            previous_gradeable = gradeable;
        }
        else {
            $('#gradeable-lock-points').val(0);
            $('#gradeable-lock-max-points-field').hide();
        }

        let points = $('#gradeable-lock-points').val();
        if (points === '') {
            return false;
        }
        points = parseInt(points);
        if ((points < 0 || points > gradeable_max_autograder_points[gradeable])) {
            displayErrorMessage('Points must be between 0 and the max autograder points for that gradeable.');
            return;
        }

        // If its rubric-related, then make different request
        if ($('#gradeable_rubric').find(`[name="${this.name}"]`).length > 0) {
            // ... but don't automatically save electronic rubric data
            if (!$('#radio_electronic_file').is(':checked')) {
                saveRubric(false);
            }
            return;
        }
        if ($('#grader_assignment').find(`[name="${this.name}"]`).length > 0) {
            saveGraders();
            return;
        }
        if ($(this).prop('id') === 'all_access' || $(this).prop('id') === 'minimum_grading_group') {
            saveGraders();
        }
        // Don't save if it we're ignoring it
        if ($(this).hasClass('ignore')) {
            return;
        }

        const data = { csrf_token: csrfToken };
        if (this.name === 'hidden_files') {
            data[this.name] = $(this).val().replace(/\s*,\s*/, ',');
        }
        else {
            data[this.name] = $(this).val();
        }
        // Retrieve status for each of the panels
        $('input[name="peer_panel"]').each(function () {
            data[$(this).attr('id')] = $(this).is(':checked');
        });
        const notifications_sent = Number(document.querySelector('#container-rubric').dataset.notifications_sent);
        const addDataToRequest = function (i, val) {
            if (val.type === 'radio' && !$(val).is(':checked')) {
                return;
            }
            if ($('#no_late_submission').is(':checked') && $(val).attr('name') === 'late_days') {
                $(val).val('0');
            }
            // Ask for confirmation if the release is delegated to the future and notifications have been sent already
            if (notifications_sent > 0 && val.name === 'grade_released_date') {
                const updating = new Date($(val).val());
                const original = new Date($(val).attr('data-original'));

                if (original !== updating && updating >= new Date()) {
                    const resend = confirm(
                        'Students have been notified and emailed that grades for this gradeable have been released. '
                        + 'If you change the grades release date, would you like to resend notifications and emails to '
                        + 'students when the new grades release date is reached?',
                    );

                    data['notifications_sent'] = resend ? 0 : notifications_sent;
                }
            }
            data[val.name] = $(val).val();
        };
        if (data['depends_on'] !== null) {
            data['depends_on_points'] = points;
        }
        else if (data['depends_on_points'] !== null) {
            data['depends_on'] = gradeable;
        }

        // If its date-related, then submit all date data
        if ($('#gradeable-dates').find(`input[name="${this.name}"]:enabled`).length > 0
            || $(this).hasClass('date-related')) {
            $('#gradeable-dates :input:enabled,.date-related').each(addDataToRequest);
        }
        // Redundant to send this data
        delete data.peer_panel;
        ajaxUpdateGradeableProperty($('#g_id').val(), data,
            (response_data) => {
                // Clear errors by setting new values
                for (const key in response_data) {
                    if (Object.prototype.hasOwnProperty.call(response_data, key)) {
                        clearError(key, response_data[key]);
                    }
                }
                // Clear errors by just removing red background
                for (const key in data) {
                    if (Object.prototype.hasOwnProperty.call(data, key)) {
                        clearError(key);
                    }
                    if (key === 'grade_released_date' && data['notifications_sent'] === 0) {
                        document.getElementById('gradeable-notifications-message').remove();
                        document.querySelector('#container-rubric').dataset.notifications_sent = '0';
                    }
                }
                updateErrorMessage();
                checkWarningBanners();
            }, updateGradeableErrorCallback);
    });

    $('#random_peer_graders_list, #clear_peer_matrix').click(
        function () {
            if ($('input[name="all_grade"]:checked').val() === 'All Grade All') {
                if (confirm('Each student grades every other student! Continue?')) {
                    const data = { csrf_token: csrfToken };
                    data[this.name] = $(this).val();
                    setRandomGraders($('#g_id').val(), data, (response_data) => {
                        // Clear errors by setting new values
                        for (const key in response_data) {
                            if (Object.prototype.hasOwnProperty.call(response_data, key)) {
                                clearError(key, response_data[key]);
                            }
                        }
                        // Clear errors by just removing red background
                        for (const key in data) {
                            if (Object.prototype.hasOwnProperty.call(data, key)) {
                                clearError(key);
                            }
                        }
                        updateErrorMessage();
                    }, updateGradeableErrorCallback, true);
                    return;
                }
            }
            if (confirm('This will update peer matrix. Are you sure?')) {
                const data = { csrf_token: csrfToken };
                data[this.name] = $(this).val();
                setRandomGraders($('#g_id').val(), data, (response_data) => {
                // Clear errors by setting new values
                    for (const key in response_data) {
                        if (Object.prototype.hasOwnProperty.call(response_data, key)) {
                            clearError(key, response_data[key]);
                        }
                    }
                    // Clear errors by setting custom validity to ''
                    for (const key in data) {
                        if (Object.prototype.hasOwnProperty.call(data, key)) {
                            clearError(key);
                        }
                    }
                    updateErrorMessage();
                }, updateGradeableErrorCallback, false);
            }
            else {
                return false;
            }
        });
});

function checkWarningBanners() {
    $('#gradeable-dates-warnings-banner').hide();
    if ($('#yes_grade_inquiry_allowed').is(':checked')) {
        const grade_inquiry_start_date = $('#date_grade_inquiry_start').val();
        const grade_inquiry_due_date = $('#date_grade_inquiry_due').val();

        // hide/show the element when the start date is before/after the due date respectfully
        if (grade_inquiry_start_date > grade_inquiry_due_date) {
            $('#grade-inquiry-dates-warning').show();
            $('#gradeable-dates-warnings-banner').show();
        }
        else {
            $('#grade-inquiry-dates-warning').hide();
        }
    }

    if ($('#has_release_date_yes').is(':checked')) {
        const release_date = $('#date_released').val();
        const grade_inquiry_due_date = $('#date_grade_inquiry_due').val();
        if (release_date > grade_inquiry_due_date) {
            $('#no-grade-inquiry-warning').show();
            $('#gradeable-dates-warnings-banner').show();
        }
        else {
            $('#release-dates-warning').hide();
        }
    }
}

function ajaxRebuildGradeableButton() {
    const gradeable_id = $('#g_id').val();
    $.ajax({
        url: buildCourseUrl(['gradeable', gradeable_id, 'rebuild']),
        success: function () {
            rebuild_triggered = true;
            ajaxCheckBuildStatus();
        },
        error: function (response) {
            console.error(response);
        },
    });
}

function ajaxGetBuildLogs(gradeable_id, rebuilt = false) {
    $.getJSON({
        type: 'GET',
        url: buildCourseUrl(['gradeable', gradeable_id, 'build_log']),
        success: function (response) {
            let alerted = false;
            const build_info = response['data'][0];
            const cmake_info = response['data'][1];
            const make_info = response['data'][2];

            if (build_info !== null) {
                // eslint-disable-next-line no-restricted-syntax
                $('#build-log-body').html(build_info);
                for (const line of build_info.split('\n')) {
                    if (line.includes('WARNING:')) {
                        alerted = true;
                        displayWarningMessage(line.split('WARNING:')[1].trim());
                    }
                    else if (line.includes('ERROR:')) {
                        alerted = true;
                        displayErrorMessage(line.split('ERROR:')[1].trim());
                    }
                }
            }
            else {
                $('#build-log-body').text('There is currently no build output.');
            }
            if (cmake_info !== null) {
                // eslint-disable-next-line no-restricted-syntax
                $('#cmake-log-body').html(cmake_info);
            }
            else {
                $('#cmake-log-body').text('There is currently no cmake output.');
            }
            if (make_info !== null) {
                // eslint-disable-next-line no-restricted-syntax
                $('#make-log-body').html(make_info);
            }
            else {
                // eslint-disable-next-line no-restricted-syntax
                $('#make-log-body').html('There is currently no make output.');
            }

            if (alerted || !rebuilt) {
                // Display the build log for respective rebuild warnings/errors or manual instructor requests
                $('.log-container').show();
                $('#open-build-log').hide();
                $('#close-build-log').show();
            }
        },
        error: function (response) {
            console.error(`Failed to parse response from server: ${response}`);
        },
    });
}

function ajaxCheckBuildStatus() {
    const gradeable_id = $('#g_id').val();
    $('#rebuild-log-button').css('display', 'none');
    hideBuildLog();
    $.getJSON({
        type: 'GET',
        url: buildCourseUrl(['gradeable', gradeable_id, 'build_status']),
        success: function (response) {
            $('#rebuild-log-button').css('display', 'block');
            if (response['data'] === 'queued') {
                $('#rebuild-status').text(gradeable_id.concat(' is in the rebuild queue...'));
                $('#rebuild-log-button').css('display', 'none');
                setTimeout(ajaxCheckBuildStatus, 1000);
                return;
            }
            else if (response['data'] === 'processing') {
                $('#rebuild-status').text(gradeable_id.concat(' is being rebuilt...'));
                $('#rebuild-log-button').css('display', 'none');
                setTimeout(ajaxCheckBuildStatus, 1000);
                return;
            }
            else if (response['data'] === 'warnings') {
                $('#rebuild-status').text('Gradeable built with warnings');
            }
            // eslint-disable-next-line eqeqeq
            else if (response['data'] == true) {
                $('.config_search_error').hide();
                $('#rebuild-status').text('Gradeable build complete');
            }
            // eslint-disable-next-line eqeqeq
            else if (response['data'] == false) {
                $('#rebuild-status').text('Gradeable build failed');
                $('#autograding_config_error').text('The current configuration is not valid, please check the build log for details.');
                $('.config_search_error').show();
            }
            else {
                $('#rebuild-status').text('Error');
                console.error('Internal server error, please try again.');
            }

            if (rebuild_triggered) {
                // Check for any potential build process warnings/errors
                rebuild_triggered = false;
                ajaxGetBuildLogs(gradeable_id, true);
            }
        },
        error: function (response) {
            console.error(`Failed to parse response from server: ${response}`);
        },
    });
}
function setRandomGraders(gradeable_id, p_values, successCallback, errorCallback, all_grade_all) {
    let number_to_grade = 1;
    if (all_grade_all === true) {
        number_to_grade = 10000;
    }
    else {
        number_to_grade = $('#number_to_peer_grade').val();
    }

    if (number_to_grade <= 0) {
        number_to_grade = 0;
        if (!confirm('This will clear Peer Matrix. Continue?')) {
            $('#peer_loader').addClass('hide');
            return false;
        }
    }

    gradeable_id = $('#g_id').val();
    let restrict_to_registration = 'unchecked';
    let submit_before_grading = 'unchecked';
    $('#peer_loader').removeClass('hide');
    if ($('#restrict-to-registration').is(':checked')) {
        restrict_to_registration = 'checked';
    }
    if ($('#submit-before-grading').is(':checked')) {
        submit_before_grading = 'checked';
    }

    $.ajax({
        type: 'POST',
        url: buildCourseUrl(['gradeable', gradeable_id, 'RandomizePeers']),
        data: {
            csrf_token: p_values['csrf_token'],
            number_to_grade: number_to_grade,
            restrict_to_registration: restrict_to_registration,
            submit_before_grading: submit_before_grading,
        },
        success: function (response) {
            const res = JSON.parse(response);
            if (res.data === 'Invalid Number of Students Entered') {
                confirm('Do you Want to go with ALL grade ALL?');
            }
            if (res.data === 'Clear Peer Matrix') {
                $('#save_status').text('Peer Matrix Cleared').css('color', 'var(--text-black)');
            }
            setGradeableUpdateComplete();
            $('#peer_loader').addClass('hide');
            location.reload();
        },

        /* To check for Server Error Messages */
        error: function (jqXHR, exception) {
            let msg = '';
            if (jqXHR.status === 0) {
                msg = 'Not connect.\n Verify Network.';
            }
            else if (jqXHR.status === 404) {
                msg = 'Requested page not found. [404]';
            }
            else if (jqXHR.status === 500) {
                msg = 'Internal Server Error [500].';
            }
            else if (exception === 'parsererror') {
                msg = 'Requested JSON parse failed.';
            }
            else if (exception === 'timeout') {
                msg = 'Time out error.';
            }
            else if (exception === 'abort') {
                msg = 'Ajax request aborted.';
            }
            else {
                msg = `Uncaught Error.\n${jqXHR.responseText}`;
            }
            alert(`error occurred${msg}`);
        },
    });
}
function ajaxUpdateGradeableProperty(gradeable_id, p_values, successCallback, errorCallback) {
    if ('peer_graders_list' in p_values && $('#peer_graders_list').length) {
        $('#save_status').text('Saving Changes').css('color', 'var(--text-black)');
        const csvFile = $('#peer_graders_list').prop('files')[0];
        const reader = new FileReader();
        reader.readAsText(csvFile);
        const jsonFile = [];
        reader.onload = function () {
            try {
                const lines = reader.result.split('\n');
                const headers = lines[0].split(',');
                let students_lines_index = -1;
                let graders_lines_index = -1;

                for (let k = 0; k < headers.length; k++) {
                    if (headers[k].toLowerCase().trim() === 'student') {
                        students_lines_index = k;
                    }
                    else if (headers[k].toLowerCase().trim() === 'grader') {
                        graders_lines_index = k;
                    }
                }

                if (students_lines_index === -1) {
                    alert('Cannot process file, requires exactly one labelled \'student\' column');
                    return;
                }

                if (graders_lines_index === -1) {
                    alert('Cannot process file, requires exactly one labelled \'grader\' column');
                    return;
                }

                for (let i = 1; i < lines.length; i++) {
                    const built_line = {};
                    const cells = lines[i].split(',');

                    for (let j = 0; j < cells.length; j++) {
                        if (cells[j].trim() !== '') {
                            built_line[headers[j].trim()] = cells[j].trim();
                        }
                    }
                    // built_line[headers[0].trim()]= cells[students_lines_index].trim();
                    // built_line[headers[1].trim()]= cells[graders_lines_index].trim();
                    jsonFile[i - 1] = built_line;
                }
                const container = $('#container-rubric');
                if (container.length === 0) {
                    alert('UPDATES DISABLED: no \'container-rubric\' element!');
                    return;
                }
                // Don't process updates until the page is done loading
                if (!container.is(':visible')) {
                    return;
                }
                p_values['peer_graders_list'] = jsonFile;
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
                            $('#save_status').text('All Changes Saved').css('color', 'var(--text-black)');
                            successCallback(response.data);
                        }
                        else if (response.status === 'fail') {
                            $('#save_status').text('Error Saving Changes').css('color', 'red');
                            errorCallback(response.message, response.data);
                        }
                        else {
                            alert('Internal server error');
                            $('#save_status').text('Error Saving Changes').css('color', 'red');
                            console.error(response.message);
                        }
                        location.reload();
                    },
                    error: function (response) {
                        $('#save_status').text('Error Saving Changes').css('color', 'red');
                        setGradeableUpdateComplete();
                        console.error(`Failed to parse response from server: ${response}`);
                    },
                });
            }
            catch (e) {
                $('#save_status').text('Error Saving Changes').css('color', 'red');
            }
        };
    }

    else {
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
}

function serializeRubric() {
    return (function () {
        const o = {};
        const a = this.serializeArray();
        const ignore = ['numeric_label_0', 'max_score_0', 'numeric_extra_0', 'numeric_extra_0',
            'text_label_0', 'checkpoint_label_0', 'num_numeric_items', 'num_text_items'];

        // Ignore all properties not on rubric
        $.each(a, function () {
            if ($('#gradeable_rubric').find(`[name="${this.name}"]`).length === 0) {
                ignore.push(this.name);
            }
        });

        // Ignore all properties marked to be ignored
        $('.ignore').each(function () {
            ignore.push($(this).attr('name'));
        });

        // parse checkpoints

        $('.checkpoints-table').find('.multi-field').each(function () {
            let label = '';
            let extra_credit = false;
            let skip = false;

            $(this).find('.checkpoint_label').each(function () {
                label = $(this).val();
                if ($.inArray($(this).attr('name'), ignore) !== -1) {
                    skip = true;
                }
                ignore.push($(this).attr('name'));
            });

            if (skip) {
                return;
            }

            $(this).find('.checkpoint_extra').each(function () {
                extra_credit = $(this).is(':checked');
                ignore.push($(this).attr('name'));
            });

            if (o['checkpoints'] === undefined) {
                o['checkpoints'] = [];
            }
            o['checkpoints'].push({ label: label, extra_credit: extra_credit });
        });

        // parse text items

        $('.text-table').find('.multi-field').each(function () {
            let label = '';
            let skip = false;

            $(this).find('.text_label').each(function () {
                label = $(this).val();
                if ($.inArray($(this).attr('name'), ignore) !== -1) {
                    skip = true;
                }
                ignore.push($(this).attr('name'));
            });

            if (skip) {
                return;
            }

            if (o['text'] === undefined) {
                o['text'] = [];
            }
            o['text'].push({ label: label });
        });

        // parse numeric items

        $('.numerics-table').find('.multi-field').each(function () {
            let label = '';
            let max_score = 0;
            let extra_credit = false;
            let skip = false;

            $(this).find('.numeric_label').each(function () {
                label = $(this).val();
                if ($.inArray($(this).attr('name'), ignore) !== -1) {
                    skip = true;
                }
                ignore.push($(this).attr('name'));
            });

            if (skip) {
                return;
            }

            $(this).find('.max_score').each(function () {
                max_score = parseFloat($(this).val());
                ignore.push($(this).attr('name'));
            });

            $(this).find('.numeric_extra').each(function () {
                extra_credit = $(this).is(':checked');
                ignore.push($(this).attr('name'));
            });

            if (o['numeric'] === undefined) {
                o['numeric'] = [];
            }
            o['numeric'].push({ label: label, max_score: max_score, extra_credit: extra_credit });
        });

        $.each(a, function () {
            if ($.inArray(this.name, ignore) !== -1) {
                return;
            }
            o[this.name] = this.value || '';
        });
        return o;
    }.call($('form')));
}

function saveRubric(redirect = true) {
    const values = serializeRubric();

    $('#save_status').text('Saving Rubric...').css('color', 'var(--text-black)');
    $.getJSON({
        type: 'POST',
        url: buildCourseUrl(['gradeable', $('#g_id').val(), 'rubric']),
        data: {
            values: values,
            csrf_token: csrfToken,
        },
        success: function (response) {
            if (response.status === 'success') {
                delete errors['rubric'];
                updateErrorMessage();
                if (redirect) {
                    window.location.replace(`${buildCourseUrl(['gradeable', $('#g_id').val(), 'update'])}?nav_tab=2`);
                }
            }
            else {
                errors['rubric'] = response.message;
                updateErrorMessage();
                alert('Error saving rubric, you may have tried to delete a component with grades.  Refresh the page');
            }
        },
        error: function (response) {
            alert('Error saving rubric.  Refresh the page');
            console.error(`Failed to parse response from server: ${response}`);
        },
    });
}

function serializeGraders() {
    // Setup graders with an array for each privilege level
    const graders = {};
    const minLevel = parseInt($('#minimum_grading_group').val());

    $('#grader_assignment').find('input').each(function () {
        const parts = this.name.split('_');
        // Ignore if we aren't at the right access level
        const level = parts[0] === 'grader' ? parts[1].substr(1) : parts[0].substr(1);
        if (level > minLevel) {
            if ($('#all_access').is(':checked')) {
                $(this).prop('checked', false);
            }
            return;
        }
        // check all boxes with right access level for all access
        if ($('#all_access').is(':checked')) {
            $(this).prop('checked', true);
        }

        // Ignore everything but checkboxes ('grader' prefix)
        if (parts[0] !== 'grader') {
            return;
        }

        if ($(this).is(':checked')) {
            if (!(parts[3] in graders)) {
                graders[parts[3]] = [];
            }
            graders[parts[3]].push(parts[2]);
        }
    });

    return graders;
}

function saveGraders() {
    const values = serializeGraders();

    $('#save_status').text('Saving Graders...').css('color', 'var(--text-black)');
    $.getJSON({
        type: 'POST',
        url: buildCourseUrl(['gradeable', $('#g_id').val(), 'graders']),
        data: {
            graders: values,
            csrf_token: csrfToken,
        },
        success: function (response) {
            if (response.status !== 'success') {
                alert('Error saving graders!');
                console.error(response.message);
                errors['graders'] = '';
            }
            else {
                delete errors['graders'];
            }
            updateErrorMessage();
        },
        error: function (response) {
            alert('Error saving graders!');
            console.error(`Failed to parse response from server: ${response}`);
        },
    });
}

function showBuildLog() {
    ajaxGetBuildLogs($('#g_id').val());
}

function hideBuildLog() {
    $('.log-container').hide();
    $('#open-build-log').show();
    $('#close-build-log').hide();
}

let originalConfigContent = null;
let codeMirrorInstance = null;
let current_g_id = null;
let current_file_path = null;
let isConfigEdited = false;
function FilePath(path, type) {
    this.path = path;
    this.type = type;
}

window.addEventListener('beforeunload', (event) => {
    if (isConfigEdited) {
        event.preventDefault();
    }
});

// When the text editor opens, the user shouldn't have to manually scroll to see the contents
function scrollToBottom() {
    window.scrollTo({ top: 935, left: 0, behavior: 'smooth' });
}

function updateGradeableEditor(g_id, file_path) {
    if ((current_g_id !== g_id || current_file_path !== file_path)) {
        $('#gradeable-config-edit').data('edited', false);

        closeCodeMirrorInstance();

        current_g_id = g_id;
        current_file_path = file_path;
        loadGradeableEditor(g_id, file_path);
    }
}

// When you load the editor
function loadGradeableEditor(g_id, file_path) {
    $.ajax({
        url: buildCourseUrl(['gradeable', 'edit', 'load']),
        type: 'POST',
        data: {
            gradeable_id: g_id,
            file_path: file_path,
            csrf_token: csrfToken,
        },
        success: function (data) {
            try {
                const json = JSON.parse(data);
                if (json.status === 'fail') {
                    displayErrorMessage(json['message']);
                    return;
                }

                if (!$('#gradeable-config-edit-bar').is(':visible')) {
                    $('#gradeable-config-edit-bar').show();
                }

                document.getElementById('add-to-config').disabled = true;
                const configData = json['data'];
                originalConfigContent = configData.config_content;
                const editbox = $('textarea#gradeable-config-edit');
                editbox.val(originalConfigContent);

                editbox.off('input').on('input', function () {
                    const current = $(this).val();
                    $(this).data('edited', current !== originalConfigContent);
                });

                editbox.css({
                    'min-width': '-webkit-fill-available',
                });

                editbox.data('edited', false);
                editbox.data('file-path', file_path);
                loadCodeMirror();
                scrollToBottom();
            }
            catch {
                displayErrorMessage('Error parsing data. Please try again');
            }
        },
    });
}

function configSelectorChange() {
    location.reload();
}

function isUsingDefaultConfig() {
    const selector = document.getElementById('autograding_config_selector');
    const selectedPath = selector.value;
    return NONUPLOADED_CONFIG_VALUES.includes(selectedPath);
}

function updateEditorButtonStyle() {
    const availableMessage = document.getElementById('editor-not-available');
    const editorButton = document.getElementById('open-config-editor');

    if (isUsingDefaultConfig()) {
        editorButton.style.display = 'none';
        availableMessage.style.display = 'block';
    }
    else {
        editorButton.style.display = 'inline-block';
        availableMessage.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    updateEditorButtonStyle();
});

function toggleGradeableConfigEdit() {
    $('#gradeable-config-structure').toggleClass('open').toggle();
    const editorButton = document.getElementById('open-config-editor');
    if (editorButton.innerText === 'Open Editor') {
        editorButton.innerText = 'Close Editor';
        current_g_id = null;
        current_file_path = null;
        scrollToBottom();
    }
    else {
        editorButton.innerText = 'Open Editor';
        cancelGradeableConfigEdit(); // Ensure unsaved changes are deleted
    }
}

function cancelGradeableConfigEdit() {
    $('#gradeable-config-edit-bar').hide();
    $('#gradeable-config-edit').data('edited', false);
    isConfigEdited = false;
    current_g_id = null;
    current_file_path = null;

    closeCodeMirrorInstance();
}

function closeCodeMirrorInstance() {
    if (codeMirrorInstance) {
        codeMirrorInstance.toTextArea(); // Replace CodeMirror with original <textarea>
        codeMirrorInstance = null;
    }
}

function saveGradeableConfigEdit(g_id) {
    const content = codeMirrorInstance?.getValue() || $('textarea#gradeable-config-edit').val();
    $.ajax({
        url: buildCourseUrl(['gradeable', 'edit', 'save']),
        type: 'POST',
        data: {
            gradeable_id: g_id,
            file_path: $('textarea#gradeable-config-edit').data('file-path'),
            write_content: content,
            csrf_token: csrfToken,
        },
        success: function (data) {
            try {
                const json = JSON.parse(data);
                if (json['status'] === 'fail') {
                    displayErrorMessage(json['message']);
                    return;
                }
                originalConfigContent = $('#gradeable-config-edit').val();
                $('#gradeable-config-edit').data('edited', false);
                cancelGradeableConfigEdit();
                ajaxCheckBuildStatus();
                displaySuccessMessage('Autograding configuration successfully updated.');
            }
            catch (err) {
                displayErrorMessage('Error parsing data. Please try again');
                return;
            }
        },
        error: function () {
            window.alert('Something went wrong while saving the gradeable config. Please try again.');
        },
    });
}

function addRootFolder(g_id) {
    const folderName = prompt('Enter a name for the new folder:');
    if (!folderName) {
        return;
    }

    const folderPath = `/${folderName}`;

    $.post({
        url: buildCourseUrl(['gradeable', 'edit', 'modify_structure']),
        data: {
            action: 'add_folder',
            gradeable_id: g_id,
            path: folderPath,
            csrf_token: csrfToken,
        },
        success: (res) => {
            const json = JSON.parse(res);
            if (json.status === 'success') {
                displaySuccessMessage('Folder created successfully.');
                location.reload();
            }
            else {
                displayErrorMessage(json.message);
            }
        },
        error: () => displayErrorMessage('Failed to create folder.'),
    });
}

function addFile(g_id, path) {
    if (!path) {
        openFilePickerAndUpload(null, g_id); // add to root
        return;
    }

    const cleaned = path.replace(/^.*config_upload\/\d+\//, '');
    openFilePickerAndUpload(cleaned, g_id); // add to folder
}

function openFilePickerAndUpload(targetFolderPath, g_id) {
    const input = document.getElementById('hidden-config-file-input');
    input.value = '';
    input.onchange = null;

    input.onchange = (event) => {
        const file = event.target.files[0];
        if (!file) return;

        const dest = (targetFolderPath || '').replace(/^\/+/, ''); // '' ⇒ root
        const relativePath = dest ? `${dest}/${file.name}` : file.name;

        uploadFile(relativePath, file, g_id);
    };

    input.click();
}

function uploadFile(relativePath, file, g_id) {
    const formData = new FormData();
    formData.append('action',       'add_file');
    formData.append('gradeable_id', g_id);
    formData.append('path',         relativePath);
    formData.append('file',         file);
    formData.append('csrf_token',   csrfToken);

    $.ajax({
        url: buildCourseUrl(['gradeable', 'edit', 'modify_structure']),
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false
    })
    .done((raw) => {
        let res;
        try { res = JSON.parse(raw); }
        catch { displayErrorMessage('Unexpected server response.'); return; }

        if (res.status === 'success') {
        displaySuccessMessage('File successfully added.');
        location.reload();
        } else {
        displayErrorMessage(res.message ?? 'Failed to add file.');
        }
    })
    .fail(() => displayErrorMessage('Something went wrong while uploading.'));
}

function removeFile(g_id, path, isFolder) {
    let confirmed;
    if (isFolder) {
        confirmed = confirm('Are you sure you want delete this folder? This action cannot be undone.');
    }
    else {
        confirmed = confirm('Are you sure you want delete this file? This action cannot be undone.');    
    }
    if (!confirmed) {
        return;
    }

    $.post({
        url: buildCourseUrl(['gradeable', 'edit', 'modify_structure']),
        data: {
            action: 'delete',
            gradeable_id: g_id,
            path: path,
            csrf_token: csrfToken,
        },
        success: (res) => {
            const json = JSON.parse(res);
            if (json.status === 'success') {
                displaySuccessMessage('Selected item deleted.');
                location.reload();
            }
            else {
                displayErrorMessage(json.message);
            }
        },
        error: () => displayErrorMessage('Error deleting files/folders.'),
    });
}

function loadCodeMirror() {
    codeMirrorInstance = CodeMirror.fromTextArea(
        document.getElementById('gradeable-config-edit'),
        {
            mode: { name: 'json', json: true },
            theme: localStorage.theme === 'light' ? 'eclipse' : 'monokai',
            lineNumbers: false,
            tabSize: 2,
            indentUnit: 2,
            lineWrapping: true,
        },
    );
    codeMirrorInstance.on('change', () => {
        const currentContent = codeMirrorInstance.getValue();
        isConfigEdited = currentContent !== originalConfigContent;
    });
}

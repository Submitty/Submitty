/* global csrfToken, buildCourseUrl, getGradeableId, setError, clearError, updateErrorMessage, setGradeableUpdateInProgress, setGradeableUpdateComplete, ajaxUpdateGradeableProperty, updateGradeableErrorCallback */

function serializeGraders() {
    const graders = {};
    const minLevel = parseInt($('#minimum_grading_group').val());

    $('#grader_assignment').find('input').each(function () {
        const parts = this.name.split('_');
        const level = parts[0] === 'grader' ? parts[1].substr(1) : parts[0].substr(1);
        if (level > minLevel) {
            if ($('#all_access').is(':checked')) {
                $(this).prop('checked', false);
            }
            return;
        }
        if ($('#all_access').is(':checked')) {
            $(this).prop('checked', true);
        }
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
        url: buildCourseUrl(['gradeable', getGradeableId(), 'graders']),
        data: {
            graders: values,
            csrf_token: csrfToken,
        },
        success: function (response) {
            if (response.status !== 'success') {
                alert('Error saving graders!');
                console.error(response.message);
                setError('graders', '');
            }
            else {
                clearError('graders');
            }
            updateErrorMessage();
        },
        error: function (response) {
            alert('Error saving graders!');
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

    gradeable_id = getGradeableId();
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
        error: function (jqXHR, exception) {
            let msg = '';
            if (jqXHR.status === 0) { msg = 'Not connect.\n Verify Network.'; }
            else if (jqXHR.status === 404) { msg = 'Requested page not found. [404]'; }
            else if (jqXHR.status === 500) { msg = 'Internal Server Error [500].'; }
            else if (exception === 'parsererror') { msg = 'Requested JSON parse failed.'; }
            else if (exception === 'timeout') { msg = 'Time out error.'; }
            else if (exception === 'abort') { msg = 'Ajax request aborted.'; }
            else { msg = `Uncaught Error.\n${jqXHR.responseText}`; }
            alert(`error occurred${msg}`);
        },
    });
}

function updateCheckBoxes(prefix, id, section) {
    const base_all_cb_name = `#${prefix}_all_${id}`;

    if (section === 0) {
        const check = $(base_all_cb_name).is(':checked');
        $(`.grader-checkbox[data-prefix="${prefix}"][data-grader-id="${id}"]`).prop('checked', check);
    }
    else {
        const all_checked = $(`.grader-checkbox[data-prefix="${prefix}"][data-grader-id="${id}"]:not(:checked)`).length === 0;
        $(base_all_cb_name).prop('checked', all_checked);
    }
}

function onSectionTypeChange() {
    disableElementChildren('#doc_all_access', true);
    disableElementChildren('#doc_registration', true);

    if ($('#rotating_section').is(':checked')) {
        disableElementChildren('#rotating_data', false);
        disableElementChildren('#grader-warning', false);
    }
    else if ($('#all_access').is(':checked')) {
        disableElementChildren('#doc_all_access', false);
        disableElementChildren('#rotating_data', true);
        disableElementChildren('#grader-warning', false);
    }
    else {
        disableElementChildren('#doc_registration', false);
        disableElementChildren('#rotating_data', true);
        disableElementChildren('#grader-warning', true);
    }
}

function onMinGraderChange() {
    disableElementChildren('.g2', false);
    disableElementChildren('.g3', false);

    switch ($('#minimum_grading_group').val()) {
        case '1':
            disableElementChildren('.g2', true);
            // fall through
        case '2':
            disableElementChildren('.g3', true);
            // fall through
        case '3':
            break;
        default:
            alert('Error! Invalid Minimum Grader!');
            break;
    }
}

function initAdminGradeableGraders() {
    onSectionTypeChange();
    $('[name="grader_assignment_method"]').change(onSectionTypeChange);

    onMinGraderChange();
    $('#minimum_grading_group').change(onMinGraderChange);

    $('.grader-all-checkbox').each(function () {
        const prefix = $(this).data('prefix');
        const id = $(this).data('grader-id');
        updateCheckBoxes(prefix, id, 1);
    });
}

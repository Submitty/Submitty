/* exported newDeletePollForm updatePollAcceptingAnswers updatePollVisible updateDropdownStates importPolls toggleTimerInputs togglePollFormOptions changePollType addResponse submitErrorChecks validateCustomResponse addCustomResponse removeCustomResponse toggle_section get_new_chart_width disableNoResponse clearResponses */
/* global csrfToken displaySuccessMessage displayErrorMessage setEventHandlers MAX_SIZE */

$(document).ready(() => {
    $('.dropdown-bar').on('click', function () {
        $(this).siblings('table').toggle();
        $(this).find('i').toggleClass('down');
    });
});

/*
 * Beginning of AllPollsPageInstructor.twig functions
 */

function newDeletePollForm(pollid, pollname, base_url) {
    if (confirm(`This will delete poll '${pollname}'. Are you sure?`)) {
        const url = `${base_url}/deletePoll`;
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('poll_id', pollid);
        $.ajax({
            url: url,
            type: 'POST',
            data: fd,
            processData: false,
            cache: false,
            contentType: false,
            success: function (data) {
                try {
                    const msg = JSON.parse(data);
                    if (msg.status !== 'success') {
                        console.error(msg);
                        window.alert('Something went wrong. Please try again.');
                    }
                    else {
                        window.location.reload();
                    }
                }
                catch (err) {
                    console.error(err);
                    window.alert('Something went wrong. Please try again.');
                }
            },
            error: function (err) {
                console.error(err);
                window.alert('Something went wrong. Please try again.');
            },
        });
    }
}

function updatePollAcceptingAnswers(pollid, base_url) {
    const accepting_answers_checkbox = `#poll_${pollid}_view_results`;
    const visible_checkbox = `#poll_${pollid}_visible`;
    let url = base_url;
    const fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('poll_id', pollid);
    if ($(accepting_answers_checkbox).is(':checked')) {
        $(visible_checkbox).prop('checked', true);
        url += '/setOpen';
    }
    else {
        url += '/setEnded';
    }
    $.ajax({
        url: url,
        type: 'POST',
        data: fd,
        processData: false,
        cache: false,
        contentType: false,
        error: function (err) {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}

function updatePollVisible(pollid, base_url) {
    const visible_checkbox = `#poll_${pollid}_visible`;
    const accepting_answers_checkbox = `#poll_${pollid}_view_results`;
    let url = base_url;
    const fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('poll_id', pollid);
    if (!$(visible_checkbox).is(':checked')) {
        $(accepting_answers_checkbox).prop('checked', false);
        url += '/setClosed';
    }
    else {
        url += '/setEnded';
    }
    $.ajax({
        url: url,
        type: 'POST',
        data: fd,
        processData: false,
        cache: false,
        contentType: false,
        error: function (err) {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}

function updateDropdownStates(curr_state, cookie_key) {
    const expiration_date = new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate() + 7);
    Cookies.set(cookie_key, !curr_state, { expires: expiration_date, path: '/' });
}

/*
 * End of AllPollsPageInstructor.twig functions
 * Beginning of PollForm.twig functions
 */

let size_is_valid = true;

function togglePollFormOptions() {
    const correct_options = $('.correct-box');

    correct_options.each(function () {
        $(this).prop('checked', $('#toggle-all').prop('checked'));
    });
}

function changePollType() {
    const count = $('.option_id').length;
    for (let i = 0; i < count; i++) {
        if ($('#poll-type-single-response-survey').is(':checked')
            || $('#poll-type-multiple-response-survey').is(':checked')) {
            const correct_box = $('.correct-box')[i];
            $(correct_box).prop('checked', true);
            $(correct_box).hide();
            $('#toggle-all').hide();
            $('#toggle-all-label').hide();
        }
        else {
            $($('.correct-box')[i]).show();
            $('#toggle-all').show();
            $('#toggle-all-label').show();
        }
    }
}

function checkImageSize(uploadedFile) {
    size_is_valid = uploadedFile.files[0].size <= MAX_SIZE;
    submitErrorChecks();
}

function addResponse() {
    const count = $('.option_id').length;
    let curr_max_id = -1;
    for (let i = 0; i < count; i++) {
        const option_id = $($('.option_id')[i]).val();
        curr_max_id = Math.max(parseInt(option_id === '' ? i : option_id, 10), curr_max_id);
    }
    const first_free_id = curr_max_id + 1;
    let hidden_style = '';
    let is_checked = '';
    if ($('#poll-type-single-response-survey').is(':checked')
        || $('#poll-type-multiple-response-survey').is(':checked')) {
        hidden_style = "style='display:none'";
        is_checked = 'checked';
        $('#toggle-all').hide();
        $('#toggle-all-label').hide();
    }
    $('#responses').append(`
            <div class="response-container" id="response_${first_free_id}_wrapper" data-testid="response-${first_free_id}-wrapper">
                <input type="hidden" class="order order-${count}" name="option[${first_free_id}][order]" value="${count}"/>
                <input type="hidden" class="option_id" name="option[${first_free_id}][id]" value=""/>
                <input aria-label="Is correct" class="correct-box" type="checkbox" name="option[${first_free_id}][is_correct]" ${hidden_style} ${is_checked}>
                <textarea aria-label="Response text" data-testid="poll-response" class="poll-response" name="option[${first_free_id}][response]" placeholder="Enter response here..."
                rows="10" cols="30"></textarea>
                <div class="move-btn up-btn">
                    <i class="fa fa-lg fa-chevron-up"></i>
                </div>
                <div class="move-btn down-btn">
                    <i class="fa fa-lg fa-chevron-down"></i>
                </div>
                <div class="move-btn delete-btn" data-testid="response-delete-button">
                    <i class="fa fa-lg fa-trash"></i>
                </div>
                <br/>
            </div>
        `);
    setEventHandlers();
}

function submitErrorChecks() {
    let empty_responses = false;
    for (let i = 0; i < $('.poll-response').length; i++) {
        if ($($('.poll-response')[i]).val() === '') {
            empty_responses = true;
        }
    }

    if ($('#poll-name').val().length === 0) {
        $('#poll-form-submit').prop('disabled', true);
        $('.polls-submit-error').hide();
        $('#empty-name-error').show();
    }
    else if ($('#poll-question').val().length === 0) {
        $('#poll-form-submit').prop('disabled', true);
        $('.polls-submit-error').hide();
        $('#empty-question-error').show();
    }
    else if (!$('#poll-type-single-response-single-correct').is(':checked')
        && !$('#poll-type-single-response-multiple-correct').is(':checked')
        && !$('#poll-type-single-response-survey').is(':checked')
        && !$('#poll-type-multiple-response-exact').is(':checked')
        && !$('#poll-type-multiple-response-flexible').is(':checked')
        && !$('#poll-type-multiple-response-survey').is(':checked')) {
        $('#poll-form-submit').prop('disabled', true);
        $('.polls-submit-error').hide();
        $('#question-type-error').show();
    }
    else if (!size_is_valid) {
        $('#poll-form-submit').prop('disabled', true);
        $('.polls-submit-error').hide();
        $('#file-size-error').show();
    }
    else if ($('#poll-date').val().length === 0) {
        $('#poll-form-submit').prop('disabled', true);
        $('.polls-submit-error').hide();
        $('#empty-date-error').show();
    }
    else if ($('.response-container').length === 0) {
        $('#poll-form-submit').prop('disabled', true);
        $('.polls-submit-error').hide();
        $('#response-count-error').show();
    }
    else if ($('.correct-box:checked').length === 0) {
        $('#poll-form-submit').prop('disabled', true);
        $('.polls-submit-error').hide();
        $('#correct-response-count-error').show();
    }
    else if ($('.correct-box:checked').length > 1
        && $('#poll-type-single-response-single-correct').is(':checked')) {
        $('#poll-form-submit').prop('disabled', true);
        $('.polls-submit-error').hide();
        $('#single-response-error').show();
    }
    else if (empty_responses) {
        $('#poll-form-submit').prop('disabled', true);
        $('.polls-submit-error').hide();
        $('#empty-response-error').show();
    }
    else {
        $('#poll-form-submit').prop('disabled', false);
        $('.polls-submit-error').hide();
    }
}

/*
 * End of PollForm.twig functions
 * Beginning of ViewPoll.twig functions
 */

function validateCustomResponse() {
    const custom_response = $('.custom-poll-response');
    const custom_response_submit = $('.custom-response-submit');

    const validate = () => {
        custom_response_submit.prop('disabled', custom_response.val().trim() === '');
    };

    custom_response.on('input', () => {
        validate();
    });

    validate();
}

function addCustomResponse(pollid, base_url) {
    const custom_response_text = document.querySelector('.custom-poll-response').value;
    const url = `${base_url}/addCustomResponse`;
    const fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('poll_id', pollid);
    fd.append('custom-response', custom_response_text);
    $.ajax({
        url: url,
        type: 'POST',
        data: fd,
        processData: false,
        cache: false,
        contentType: false,
        error: (err) => {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
        success: (data) => {
            try {
                const msg = JSON.parse(data);
                if (msg.status !== 'success') {
                    displayErrorMessage(msg.message);
                }
                else {
                    window.location.reload();
                }
            }
            catch (err) {
                console.error(err);
                window.alert('Something went wrong. Please try again.');
            }
        },
    });
}

function removeCustomResponse(pollid, optionid, base_url) {
    const url = `${base_url}/removeCustomResponse`;
    const fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('poll_id', pollid);
    fd.append('option_id', optionid);
    $.ajax({
        url: url,
        type: 'POST',
        data: fd,
        processData: false,
        cache: false,
        contentType: false,
        error: (err) => {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
        success: (data) => {
            try {
                const msg = JSON.parse(data);
                if (msg.status !== 'success') {
                    displayErrorMessage(msg.message);
                }
                else {
                    document.getElementById(`option-row-${optionid}`).remove();
                    displaySuccessMessage(msg.data.message);
                }
            }
            catch (err) {
                console.error(err);
                window.alert('Something went wrong. Please try again.');
            }
        },
    });
}

function importPolls() {
    $('#import-polls-form').submit();
}

function toggleTimerInputs() {
    if ($('#enable-timer').prop('checked')) {
        $('#timer-inputs').show();
    }
    else {
        $('#timer-inputs').hide();
    }
}

function toggle_section(section_id) {
    $(`#${section_id}`).toggle('fast');
}

function get_new_chart_width() {
    const MIN_CHART_WIDTH = 400;
    const DESIRED_CHART_FACTOR = 0.75;
    const table_size = $('#info-histogram-table').width();
    const desired_size = table_size * DESIRED_CHART_FACTOR;
    // if the width of the viewport is small enough
    if (desired_size < MIN_CHART_WIDTH) {
        // set the width of poll-info to 100%
        $('#poll-info').css('max-width', '100%');
        return Math.max(MIN_CHART_WIDTH, table_size);
    }
    // reset width of poll-info
    $('#poll-info').css('max-width', '');
    return desired_size;
}

function disableNoResponse() {
    $('.no-response-radio').prop('checked', false);
}

function clearResponses() {
    if ($('.no-response-radio').is(':checked')) {
        $('.response-radio').prop('checked', false);
    }
}

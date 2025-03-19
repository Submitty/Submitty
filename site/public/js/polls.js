/* exported newDeletePollForm updatePollAcceptingAnswers updatePollVisible updateDropdownStates importPolls toggleTimerInputs togglePollFormOptions validateCustomResponse addCustomResponse removeCustomResponse toggle_section get_new_chart_width disableNoResponse clearResponses updateHistogram initializeInstructorSocketClient initializeStudentSocketClient */
/* global csrfToken displaySuccessMessage displayErrorMessage Plotly WebSocketClient */

$(document).ready(() => {
    $('.dropdown-bar').on('click', function () {
        $(this).siblings('table').toggle();
        $(this).find('i').toggleClass('down');
    });
});

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

function togglePollFormOptions() {
    const correct_options = $('.correct-box');

    correct_options.each(function () {
        $(this).prop('checked', $('#toggle-all').prop('checked'));
    });
}

function validateCustomResponse() {
    const custom_response = $('.custom-poll-response');
    const custom_response_submit = $('.custom-response-submit');

    const validate = () => {
        custom_response_submit.prop('disabled',
            custom_response.val().trim() === '' || custom_response_submit.attr('data-disabled') === 'true',
        );
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

function updateTimer(endDate) {
    const timerDisplayElement = $('#timerDisplay');
    const timerElement = $('#timer');

    function tick() {
        const now = new Date();
        const timeRemaining = endDate - now;

        // Show the timer element once we're ready to start updating it
        timerElement.show();

        if (timeRemaining <= 0) {
            timerElement.text('Poll Ended');
            clearInterval(timerId);
            return;
        }

        const seconds = Math.floor((timeRemaining / 1000) % 60);
        const minutes = Math.floor((timeRemaining / (1000 * 60)) % 60);
        const hours = Math.floor((timeRemaining / (1000 * 60 * 60)) % 24);

        const hoursUpdated = (hours < 10) ? `0${hours}` : hours;
        const minutesUpdated = (minutes < 10) ? `0${minutes}` : minutes;
        const secondsUpdated = (seconds < 10) ? `0${seconds}` : seconds;

        timerDisplayElement.text(`${hoursUpdated}:${minutesUpdated}:${secondsUpdated}`);
    }

    const timerId = setInterval(tick, 20);
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

function updateHistogram(updates) {
    // Fetch the global histogram variables and corresponding element
    const { data, layout, responseIndices } = window.histogram;
    const container = $('#chartContainer')[0];

    for (const option of Object.keys(updates)) {
        // Update the current y value for the option based on the updates (-1, 0, 1)
        data[0].y[responseIndices[option]] += Number(updates[option]);
    }

    // Re-render the histogram based on the current layout
    Plotly.newPlot(container, data, layout);
}

function initializeInstructorSocketClient(url) {
    window.socketClient = new WebSocketClient();
    window.socketClient.onmessage = (msg) => {
        switch (msg.type) {
            case 'update_histogram':
                updateHistogram(msg.message);
                break;
            default:
                console.error('Unknown web socket message received:', msg);
                break;
        }
    };
    window.socketClient.open(url);
}

function initializeStudentSocketClient(url) {
    window.socketClient = new WebSocketClient();
    window.socketClient.onmessage = (msg) => {
        const submit_button = $('.student-submit');
        const custom_response_submit = $('.custom-response-submit');

        switch (msg.type) {
            case 'poll_opened':
            case 'poll_updated':
                submit_button.prop('disabled', false);

                if (custom_response_submit.length > 0) {
                    custom_response_submit.attr('data-disabled', 'false');
                    validateCustomResponse();
                }

                displaySuccessMessage(msg.message);
                break;
            case 'poll_closed':
            case 'poll_ended':
                submit_button.prop('disabled', true);

                if (custom_response_submit.length > 0) {
                    custom_response_submit.prop('disabled', true);
                    custom_response_submit.attr('data-disabled', 'true');
                }

                displayErrorMessage(msg.message);
                break;
            default:
                console.error('Unknown web socket message received:', msg);
                break;
        }
    };
    window.socketClient.open(url);
}

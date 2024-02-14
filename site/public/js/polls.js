/* exported newDeletePollForm updatePollAcceptingAnswers updatePollVisible updateDropdownStates importPolls */
/* global csrfToken */

$(document).ready(() => {
    $('.dropdown-bar').on('click', function() {
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
            success: function(data) {
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
            error: function(err) {
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
        error: function(err) {
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
        error: function(err) {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}

function updateDropdownStates(curr_state, cookie_key) {
    const expiration_date = new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate()+7);
    Cookies.set(cookie_key, !curr_state, { expires: expiration_date, path: '/' });
}

function removeCustomResponse(pollid, optionid, base_url) {
    const url = base_url + '/removeCustomResponse';
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
        error: function(err) {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
        success: function(data) {
            displaySuccessMessage("Successfully removed custom response");
            document.getElementById(`option-row-${optionid}`).remove();
            document.querySelector('.custom-response-wrapper').style.display = 'block';
        }
    });
}

function updateCustomResponse(pollid, optionid, base_url) {
    const custom_response_value = document.getElementById(`${optionid}_custom_response`).value;
    const url = base_url + '/updateCustomResponse';
    const fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('poll_id', pollid);
    fd.append('option_id', optionid);
    fd.append('option_response',custom_response_value);
    $.ajax({
        url: url,
        type: 'POST',
        data: fd,
        processData: false,
        cache: false,
        contentType: false,
        error: function(err) {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
        success: function(data) {
            console.log(JSON.stringify(data));
            displaySuccessMessage("Successfully updated custom response");
            const parent_container = document.getElementById(`${optionid}_custom_response`).parentNode;
            parent_container.querySelector('.markdown').style.display = 'inline-flex';
            parent_container.querySelector('.markdown p').textContent = custom_response_value;
            parent_container.querySelector('.edit-btn').style.display = 'inline-flex';
            parent_container.querySelector('textarea').style.display = 'none';
            parent_container.querySelector('.upload-btn').style.display = 'none';
        }
    });
}

function importPolls() {
    $('#import-polls-form').submit();
}

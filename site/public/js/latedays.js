/* global luxon */
const DateTime = luxon.DateTime;

function calculateLateDays(inputDate) {
    const select_menu = document.getElementById('g_id');
    if (select_menu.selectedIndex === 0) {
        alert('Please select a gradeable first!');
        return;
    }

    const due_date_value = select_menu.options[select_menu.selectedIndex].getAttribute('data-due-date');
    const new_due_date = DateTime.fromISO(inputDate);
    const old_due_date = DateTime.fromISO(due_date_value);
    const diff = Math.floor(Math.max(0, new_due_date.diff(old_due_date, 'days').days));
    document.getElementById('late_days').value = diff;
}

$(document).ready(() => {
    // eslint-disable-next-line no-undef
    flatpickr('#late-calendar', {
        // eslint-disable-next-line no-undef
        plugins: [ShortcutButtonsPlugin(
            {
                button: [
                    {
                        label: 'Now',
                    },
                    {
                        label: 'End of time',
                    },
                ],
                label: 'or',
                onClick: (index, fp) => {
                    let date;
                    switch (index) {
                        case 0:
                            date = DateTime.local();
                            break;
                        case 1:
                            date = DateTime.fromISO('9998-01-01');
                            break;
                    }
                    fp.setDate(date, true);
                },
            },
        )],
        allowInput: true,
        enableTime: false,
        enableSeconds: false,
        time_24hr: true,
        dateFormat: 'Y-m-d',
        // eslint-disable-next-line no-unused-vars
        onChange: function (selectedDates, dateStr, instance) {
            calculateLateDays(selectedDates[0]);
        },
    });
});

// eslint-disable-next-line no-unused-vars
function updateLateDays(data) {
    const fd = new FormData($('#late-day-form').get(0));
    const selected_csv_option = $('input:radio[name=csv_option]:checked').val();
    // eslint-disable-next-line no-undef
    const url = `${buildCourseUrl(['late_days', 'update'])}?csv_option=${selected_csv_option}`;
    $.ajax({
        url: url,
        type: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success: function () {
            window.location.reload();
        },
        error: function () {
            window.alert('Something went wrong. Please try again.');
        },
    });
    return false;
}

// eslint-disable-next-line no-unused-vars
function deleteLateDays(user_id, datestamp) {
    // Convert 'MM/DD/YYYY HH:MM:SS A' to 'MM/DD/YYYY'
    // datestamp_mmddyy = datestamp.split(" ")[0];
    // eslint-disable-next-line no-undef
    const url = buildCourseUrl(['late_days', 'delete']);
    const confirm = window.confirm('Are you sure you would like to delete this entry?');
    if (confirm) {
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                // eslint-disable-next-line no-undef
                csrf_token: csrfToken,
                user_id: user_id,
                datestamp: datestamp,
            },
            success: function () {
                window.location.reload();
            },
            error: function () {
                window.alert('Something went wrong. Please try again.');
            },
        });
    }
}

function updateCacheBuildStatus(url, confirm_message, status) {
    const confirm = window.confirm(confirm_message);
    if (confirm) {
        // show rebuild status message
        $('#rebuild-status-panel').show();
        $('#rebuild-status').html(status);

        // disable and grey out table and buttons
        $('#calculate-btn').prop('disabled', true).css('opacity', 0.5);
        $('#flush-btn').prop('disabled', true).css('opacity', 0.5);
        $('#late-day-table').css('opacity', 0.5);

        $.ajax({
            url: url,
            success: function () {
                window.location.reload();
            },
            error: function () {
                window.alert('Something went wrong. Please try again.');
            },
        });
    }
}

// eslint-disable-next-line no-unused-vars
function calculateLateDayCache() {
    // eslint-disable-next-line no-undef
    const url = buildCourseUrl(['bulk_late_days', 'calculate']);
    const confirm_message = 'Are you sure you want to recalculate the cache? Calculating the remaining late day information for every user may take a while.';
    const status = 'Recaclulating...';
    updateCacheBuildStatus(url, confirm_message, status);
}

// eslint-disable-next-line no-unused-vars
function flushLateDayCache() {
    // eslint-disable-next-line no-undef
    const url = buildCourseUrl(['bulk_late_days', 'flush']);
    const confirm_message = 'Are you sure you want to flush the cache? This will remove the late day cache for every user.';
    const status = 'Flushing...';
    updateCacheBuildStatus(url, confirm_message, status);
}

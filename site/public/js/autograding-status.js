/* global buildUrl, displayErrorMessage, displaySuccessMessage */
// Change this variable to change the frequency of the update request, measured in milliseconds
const refresh_freq = 5000;
// Change this variable to change the max number of entries in the table before the oldest entries are replaced
const max_log = 60;

let time_id = -1;
let update = true;

function updateTable() {
    $.ajax({
        url: buildUrl(['autograding_status', 'get_update']),
        type: 'GET',
        success: function (response) {
            try {
                const data = $('#data');
                let json = JSON.parse(response);
                if (json.status !== 'success') {
                    displayErrorMessage('This login session has expired, please log in again to continue to receive updates');
                    return;
                }

                json = json.data;

                // Check to see if any of the machine and capability info are outdated, if so, refresh the page
                if (data.data('machine-num') !== Object.keys(json.machine_grading_counts).length) {
                    location.reload();
                }
                if (data.data('capability-num') !== Object.keys(json.capability_queue_counts).length) {
                    location.reload();
                }

                // Update Class Statistics table
                let table = document.getElementById('course-table');
                $('#course-table tbody').html('');
                Object.keys(json.course_info).forEach((key1) => {
                    const course_name = key1.split('__');
                    Object.keys(json.course_info[key1]).forEach((key2) => {
                        const info = json.course_info[key1][key2];
                        const new_row = table.getElementsByTagName('tbody')[0].insertRow(-1);
                        new_row.insertCell().innerText = course_name[0];
                        new_row.insertCell().innerText = course_name[1];
                        new_row.insertCell().innerText = key2;
                        const int_box = new_row.insertCell();
                        if (info.interactive !== 0) {
                            int_box.innerText = info.interactive;
                        }
                        const regrade_box = new_row.insertCell();
                        if (info.regrade !== 0) {
                            regrade_box.innerText = info.regrade;
                        }
                    });
                });

                // Update Machine Statistics table
                table = document.getElementById('machine-table');
                $('#machine-table tbody').html('');
                Object.keys(json.ongoing_job_info).forEach((key) => {
                    const info = json.ongoing_job_info[key];
                    info.forEach((elem) => {
                        const new_row = table.getElementsByTagName('tbody')[0].insertRow(-1);
                        new_row.insertCell().innerText = key;
                        new_row.insertCell().innerText = elem.semester;
                        new_row.insertCell().innerText = elem.course;
                        new_row.insertCell().innerText = elem.gradeable_id;
                        new_row.insertCell().innerText = elem.user_id;
                        new_row.insertCell().innerText = elem.elapsed_time;
                        new_row.insertCell().innerText = elem.error;
                    });
                });

                // Update Grading Monitor table
                table = document.getElementById('autograding-status-table');
                const new_row = table.insertRow(3);
                let new_cell = new_row.insertCell();
                new_cell.innerText = json.time;
                new_cell.className = 'right-boarder';

                new_cell = new_row.insertCell();
                // eslint-disable-next-line eqeqeq
                if (json.queue_counts.interactive_ongoing != 0) {
                    new_cell.innerText = json.queue_counts.interactive_ongoing;
                }

                new_cell = new_row.insertCell();
                // eslint-disable-next-line eqeqeq
                if (json.queue_counts.interactive != 0) {
                    new_cell.innerText = json.queue_counts.interactive;
                }
                new_cell.className = 'right-boarder';

                new_cell = new_row.insertCell();
                // eslint-disable-next-line eqeqeq
                if (json.queue_counts.regrade_ongoing != 0) {
                    new_cell.innerText = json.queue_counts.regrade_ongoing;
                }
                new_cell = new_row.insertCell();
                // eslint-disable-next-line eqeqeq
                if (json.queue_counts.regrade != 0) {
                    new_cell.innerText = json.queue_counts.regrade;
                }
                new_cell.className = 'right-boarder';
                Object.keys(json.machine_grading_counts).forEach((key, i) => {
                    if (i === Object.keys(json.machine_grading_counts).length - 1) {
                        new_cell = new_row.insertCell();
                        // eslint-disable-next-line eqeqeq
                        if (json.machine_grading_counts[key] != 0) {
                            new_cell.innerText = json.machine_grading_counts[key];
                        }
                        new_cell.className = 'right-boarder';
                    }
                    else {
                        new_cell = new_row.insertCell();
                        // eslint-disable-next-line eqeqeq
                        if (json.machine_grading_counts[key] != 0) {
                            new_cell.innerText = json.machine_grading_counts[key];
                        }
                    }
                });

                Object.keys(json.capability_queue_counts).forEach((key) => {
                    const new_cell = new_row.insertCell();
                    // eslint-disable-next-line eqeqeq
                    if (json.capability_queue_counts[key] != 0) {
                        new_cell.innerText = json.capability_queue_counts[key];
                    }
                });
                // Check if old logs should be removed to make room for new logs
                if ($('#autograding-status-table tbody tr').length > max_log) {
                    // +3 to account for the thead
                    table.deleteRow(max_log + 3);
                }

                // Queue this function to be run again after specified delay
                if (update) {
                    time_id = setTimeout(updateTable, refresh_freq);
                }
            }
            catch (e) {
                console.log(e);
            }
        },
    });
}

function toggleUpdate() {
    if ($(this).text() === 'Pause Update') {
        update = false;
        clearTimeout(time_id);
        displaySuccessMessage('Update has been stopped');
        $(this).text('Resume Update');
    }
    else {
        update = true;
        updateTable();
        displaySuccessMessage('Update has been resumed');
        $(this).text('Pause Update');
    }
}

function updateStackTrace() {
    $('.stack-refresh-btn').prop('disabled', true);
    $.ajax({
        url: buildUrl(['autograding_status', 'get_stack']),
        type: 'GET',
        success: function (response) {
            $('.stack-refresh-btn').prop('disabled', false);
            const json = JSON.parse(response);
            if (json.status !== 'success') {
                displayErrorMessage(json.message);
                return;
            }
            const error_log = $('.stack-trace');
            error_log.empty();
            error_log.append('<div class="stack-trace-wrapper"></div>');
            error_log.append('<pre class="stack-trace-info custom-scrollbar"></pre>');
            const wrapper = $('.stack-trace-wrapper');
            const info = $('.stack-trace-info');
            // Shouldn't be needed if the files follow the same timestamp format, but it's here just in case
            const keys = Object.keys(json.data);
            keys.sort(
                (a, b) => {
                    if (a === b) {
                        return 0;
                    }
                    if (a < b) {
                        return 1;
                    }
                    return -1;
                },
            );
            keys.forEach((key, i) => {
                const new_tab = $('<a class="tab"></a>').text(key);
                if (i === 0) {
                    new_tab.addClass('active-tab');
                    info.text(json.data[key]);
                }
                wrapper.append(new_tab);
                new_tab.attr('data', json.data[key]);
                new_tab.on('click', () => {
                    $('.active-tab').removeClass('active-tab');
                    new_tab.addClass('active-tab');
                    $('.stack-trace-info').text(new_tab.attr('data'));
                });
            });
        },
    });
}

$(document).ready(() => {
    $('#toggle-btn').text('Pause Update');
    $('#toggle-btn').on('click', toggleUpdate);
    $('.stack-refresh-btn').on('click', updateStackTrace);
    time_id = setTimeout(updateTable, refresh_freq);
    updateStackTrace();
});

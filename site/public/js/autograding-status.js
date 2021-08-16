// Change this variable to change the frequency of the update request, measured in miliseconds
const refresh_freq = 5000;
// Change this variable to change the max number of entries in the table before the oldest entries are replaced
const max_log = 60;

let interval = null;

function updateTable() {
    clearInterval(interval);
    $.ajax({
        // eslint-disable-next-line no-undef
        url: buildUrl(['autograding_status', 'get_update']),
        type: 'GET',
        // eslint-disable-next-line no-undef
        data: {'csrf_token': csrfToken},
        success: function(response) {
            try {
                const data = $('#data');
                let json = JSON.parse(response);
                if (json.status !== 'success') {
                    // eslint-disable-next-line no-undef
                    displayErrorMessage('This login session has expired, please log in again to continue to receive updates');
                    return;
                }

                json = json.data;

                // Check to see if any of the machine and capability info are outdated, if so, refresh the page
                if (data.data('machine-num') != Object.keys(json.machine_grading_counts).length) {
                    location.reload();
                }
                if (data.data('capability-num') != Object.keys(json.capability_queue_counts).length) {
                    location.reload();
                }

                // Update Class Statistics table
                let table = document.getElementById('course-table');
                $('#course-table tbody').html('');
                Object.keys(json.course_info).forEach(key1 => {
                    const course_name = key1.split('__');
                    Object.keys(json.course_info[key1]).forEach(key2 => {
                        const info = json.course_info[key1][key2];
                        const new_row = table.getElementsByTagName('tbody')[0].insertRow(-1);
                        new_row.insertCell().innerHTML = course_name[0];
                        new_row.insertCell().innerHTML = course_name[1];
                        new_row.insertCell().innerHTML = key2;
                        new_row.insertCell().innerHTML = info.interactive;
                        new_row.insertCell().innerHTML = info.regrade;
                    });
                });

                // Update Machine Statistics table
                table = document.getElementById('machine-table');
                $('#machine-table tbody').html('');
                Object.keys(json.ongoing_job_info).forEach(key => {
                    const info = json.ongoing_job_info[key];
                    info.forEach(elem => {
                        const new_row = table.getElementsByTagName('tbody')[0].insertRow(-1);
                        new_row.insertCell().innerHTML = key;
                        new_row.insertCell().innerHTML = elem.semester;
                        new_row.insertCell().innerHTML = elem.course;
                        new_row.insertCell().innerHTML = elem.gradeable_id;
                        new_row.insertCell().innerHTML = elem.user_id;
                        new_row.insertCell().innerHTML = elem.elapsed_time;
                        new_row.insertCell().innerHTML = elem.error;
                    });
                });

                // Update Grading Monitor table
                table = document.getElementById('autograding-status-table');
                const new_row = table.insertRow(3);
                let new_cell = new_row.insertCell();
                new_cell.innerHTML = json.time;
                new_cell.className = 'right-boarder';
                new_row.insertCell().innerHTML = json.queue_counts.interactive_ongoing;
                new_cell = new_row.insertCell();
                new_cell.innerHTML = json.queue_counts.interactive;
                new_cell.className = 'right-boarder';
                new_row.insertCell().innerHTML = json.queue_counts.regrade_ongoing;
                new_cell = new_row.insertCell();
                new_cell.innerHTML = json.queue_counts.regrade;
                new_cell.className = 'right-boarder';
                Object.keys(json.machine_grading_counts).forEach((key, i) => {
                    if (i == Object.keys(json.machine_grading_counts).length - 1) {
                        new_cell = new_row.insertCell();
                        new_cell.innerHTML = json.machine_grading_counts[key];
                        new_cell.className = 'right-boarder';
                    }
                    else {
                        new_row.insertCell().innerHTML = json.machine_grading_counts[key];
                    }
                });
                Object.keys(json.capability_queue_counts).forEach(key => {
                    new_row.insertCell().innerHTML = json.capability_queue_counts[key];
                });
                // Check if old logs should be removed to make room for new logs
                if ($('#autograding-status-table tbody tr').length > max_log) {
                    // +3 to account for the thead
                    table.deleteRow(max_log + 3);
                }

                // Queue this function to be run again after specified delay
                interval = setInterval(updateTable, refresh_freq);
            }
            catch (e) {
                console.log(e);
            }
        },
    });
}

function toggleUpdate() {
    if ($(this).text() === 'Pause Update') {
        clearInterval(interval);
        interval = null;
        // eslint-disable-next-line no-undef
        displaySuccessMessage('Update has been stopped');
        $(this).text('Resume Update');
    }
    else {
        updateTable();
        // eslint-disable-next-line no-undef
        displaySuccessMessage('Update has been resumed');
        $(this).text('Pause Update');
    }
}

$(document).ready(() => {
    interval = setInterval(updateTable, refresh_freq);
    $('#toggle-btn').text('Pause Update');
    $('#toggle-btn').on('click', toggleUpdate);
});

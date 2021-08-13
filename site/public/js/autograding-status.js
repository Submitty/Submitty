// Change this variable to change the frequency of the update request, measured in miliseconds
const refresh_freq = 5000;
// Change this variable to change the max number of entries in the table before the oldest entries are replaced
const max_log = 60;

let interval = null;

function updateTable() {
    console.log(buildCourseUrl(['autograding_status', 'get_update']));
    clearInterval(interval);
    $.ajax({
        url: buildCourseUrl(['autograding_status', 'get_update']),
        type: 'GET',
        data: {'csrf_token': csrfToken},
        success: function(response) {
            try {
                const data = $('#data');
                const json = JSON.parse(response).data;
                if (data.data('machine-num') != Object.keys(json.machine_count).length) {
                    location.reload();
                }
                if (data.data('capability-num') != Object.keys(json.capability_count).length) {
                    location.reload();
                }
                const table = document.getElementById("autograding-status-table");
<<<<<<< Updated upstream
                const new_row = table.insertRow(3);
=======
                const new_row = table.insertRow();
>>>>>>> Stashed changes
                new_cell = new_row.insertCell();
                new_cell.innerHTML = json.time;
                new_cell.className = "right-boarder";
                new_row.insertCell().innerHTML = json.interactive_ongoing;
                new_cell = new_row.insertCell();
                new_cell.innerHTML = json.interactive_queue;
                new_cell.className = "right-boarder";
                new_row.insertCell().innerHTML = json.regrade_ongoing;
                new_cell = new_row.insertCell();
                new_cell.innerHTML = json.regrade_queue;
                new_cell.className = "right-boarder";
                Object.keys(json.machine_count).forEach(function(key, i) {
                    if (i == Object.keys(json.machine_count).length - 1) {
                        new_cell = new_row.insertCell();
                        new_cell.innerHTML = json.machine_count[key];
                        new_cell.className = "right-boarder";
                    }
                    else {
                        new_row.insertCell().innerHTML = json.machine_count[key];
                    }
                });
                Object.keys(json.capability_count).forEach(key => {
                    new_row.insertCell().innerHTML = json.capability_count[key];
                });
                if ($("#autograding-status-table tbody tr").length > max_log) {
                    // +3 to account for the thead
                    table.deleteRow(max_log + 3);
                }
                interval = setInterval(updateTable, refresh_freq);
            }
            catch (e) {
                console.log(response);
                console.log(e);
            }
        }
    });
}

function stopUpdate() {
    clearInterval(interval);
    interval = null;
    displaySuccessMessage("Update has been stopped");
}

$(document).ready(function() {
    interval = setInterval(updateTable, refresh_freq);
    $('#stop-btn').on('click', stopUpdate);
});
function updateTable() {
    console.log(buildCourseUrl(['autograding_status', 'get_update']));
    $.ajax({
        url: buildCourseUrl(['autograding_status', 'get_update']),
        type: 'GET',
        data: {'csrf_token': csrfToken},
        success: function(response) {
            try {
                const json = JSON.parse(response).data;
                const table = document.getElementById("autograding-status-table");
                const new_row = table.insertRow();
                new_row.insertCell().innerHTML = json.time;
                new_row.insertCell().innerHTML = json.interactive_ongoing;
                new_row.insertCell().innerHTML = json.interactive_queue;
                new_row.insertCell().innerHTML = json.regrade_ongoing;
                new_row.insertCell().innerHTML = json.regrade_queue;
                Object.keys(json.machine_count).forEach(key => {
                    new_row.insertCell().innerHTML = json.machine_count[key];
                });
                Object.keys(json.capability_count).forEach(key => {
                    new_row.insertCell().innerHTML = json.capability_count[key];
                });
                if ($("#autograding-status-table tbody tr").length > 60) {
                    table.deleteRow(3);
                }
            }
            catch (e) {
                console.log(response);
                console.log(e);
            }
        }
    });
}

$(document).ready(function() {
    setInterval(updateTable, 5000);
});
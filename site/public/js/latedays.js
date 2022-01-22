function calculateLateDays(inputDate){
    let select_menu = document.getElementById("g_id");
    if(select_menu.selectedIndex === 0){
        alert("Please select a gradeable first!");
        return;
    }
    var due_date_value = select_menu.options[select_menu.selectedIndex].getAttribute("data-due-date");
    var new_due_date = new Date(inputDate);
    var old_due_date = new Date(due_date_value);
    var delta = (new_due_date.getTime() - old_due_date.getTime()) / (1000*60*60*24);
    if (delta < 0) {
        delta = 0;
    }
    var diff = Math.floor(delta);
    document.getElementById("late_days").value = diff;
}

$(document).ready(function() {
    flatpickr("#late-calendar", {
        plugins: [ShortcutButtonsPlugin(
                {
                    button: [
                        {
                            label: "Now"
                        },
                        {
                            label: "End of time"
                        }
                    ],
                    label: "or",
                    onClick: (index, fp) => {
                        let date;
                        switch (index) {
                            case 0:
                                date = new Date();
                                break;
                            case 1:
                                date = new Date("9998-01-01");
                                break;
                        }
                        fp.setDate(date, true);
                    }
                }
            )],
        allowInput: true,
        enableTime: false,
        enableSeconds: false,
        time_24hr: true,
        dateFormat: "Y-m-d",
        onChange: function(selectedDates, dateStr, instance) {
            calculateLateDays(selectedDates[0]);
        },
    });
});

function updateLateDays(data) {
    var fd = new FormData($('#late-day-form').get(0));
    var selected_csv_option = $("input:radio[name=csv_option]:checked").val();
    var url = buildCourseUrl(['late_days', 'update']) + '?csv_option=' + selected_csv_option;
    $.ajax({
        url: url,
        type: "POST",
        data: fd,
        processData: false,
        contentType: false,
        success: function() {
            window.location.reload();
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    })
    return false;
}

function deleteLateDays(user_id, datestamp) {
    // Convert 'MM/DD/YYYY HH:MM:SS A' to 'MM/DD/YYYY'
    //datestamp_mmddyy = datestamp.split(" ")[0];
    var url = buildCourseUrl(['late_days', 'delete']);
    var confirm = window.confirm("Are you sure you would like to delete this entry?");
    if (confirm) {
        $.ajax({
            url: url,
            type: "POST",
            data: {
                csrf_token: csrfToken,
                user_id: user_id,
                datestamp: datestamp
            },
            success: function() {
                window.location.reload();
            },
            error: function() {
                window.alert("Something went wrong. Please try again.");
            }
        })
    }
}

function updateCacheBuildStatus(url, confirm_message, status) {
    var confirm = window.confirm(confirm_message);
    if (confirm) {
        // show rebuild status message
        $('#rebuild-status-panel').show();
        $('#rebuild-status').html(status);

        // disable and grey out table and buttons
        $('#calculate-btn').prop('disabled',true).css('opacity',0.5);
        $('#flush-btn').prop('disabled',true).css('opacity',0.5);
        $('#late-day-table').css('opacity',0.5);

        $.ajax({
            url: url,
            success: function() {
                window.location.reload();
            },
            error: function() {
                window.alert("Something went wrong. Please try again.");
            }
        })
    }
}

function calculateLateDayCache() {
    const url = buildCourseUrl(['bulk_late_days', 'calculate']);
    const confirm_message = "Are you sure you want to recalculate the cache? Calculating the remaining late day information for every user may take a while.";
    const status = "Recaclulating...";
    updateCacheBuildStatus(url, confirm_message, status);
}

function flushLateDayCache() {
    const url = buildCourseUrl(['bulk_late_days', 'flush']);
    const confirm_message = "Are you sure you want to flush the cache? This will remove the late day cache for every user.";
    const status = "Flushing...";
    updateCacheBuildStatus(url, confirm_message, status);
}
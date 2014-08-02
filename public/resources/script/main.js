function load_progress_bar(progress, text) {
    console.log(progress);

    if (progress == 0) {
        $("#centered-progress").html("");
        $("#bar-progress").html("");
    } else if (progress > 10) {
        $("#centered-progress").html("");
        $("#bar-progress").html(text);
    } else {
        $("#centered-progress").html(text);
        $("#bar-progress").html("");
    }
}

function check_server(course, assignment_id, assignment_version, submitting_version, assignment_graded, submitting_graded, interval) {
    $.post("index.php?course="+course+"&page=checkrefresh", 
        {
            assignment_id: assignment_id,
            assignment_version: assignment_version,
            submitting_version: submitting_version,
            assignment_graded: assignment_graded,
            submitting_graded: submitting_graded
        },
        function(data) {
            if (data === true || data === "true") {
                location.reload(true);
            } else {
                init_refresh_on_update(course, assignment_id, assignment_version, submitting_version, assignment_graded, submitting_graded, interval);
            }
        }
    );
}

function init_refresh_on_update(course, assignment_id, assignment_version, submitting_version, assignment_graded, submitting_graded, interval)
{
    setTimeout(function() { check_server(course, assignment_id, assignment_version, submitting_version, assignment_graded, submitting_graded, interval) }, interval);
}
        

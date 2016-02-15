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

function check_server(semester, course, assignment_id, assignment_version, active_version, assignment_graded, active_graded, interval) {
    $.post("index.php?semester="+semester+"&course="+course+"&page=checkrefresh",
        {
            assignment_id: assignment_id,
            assignment_version: assignment_version,
            active_version: active_version,
            assignment_graded: assignment_graded,
            active_graded: active_graded
        },
        function(data) {
            if (data.indexOf("REFRESH_ME") > -1) {
                location.reload(true);
            } else {
                init_refresh_on_update(semester, course, assignment_id, assignment_version, active_version, assignment_graded, active_graded, interval);
            }
        }
    );
}

function init_refresh_on_update(semester, course, assignment_id, assignment_version, active_version, assignment_graded, active_graded, interval)
{
    setTimeout(function() { check_server(semester, course, assignment_id, assignment_version, active_version, assignment_graded, active_graded, interval) }, interval);
}

function toggleDiv(id) {
    var e = document.getElementById(id);
    if (!e) {
        return false;
    }
    if (e.style.display == "none") {
        e.style.display = "block"
    } else {
        e.style.display="none"
    }
    return false;
}

function hideAllDiv(max_id, id_name) {
    for (var i = 0; i < max_id; i++){
        toggleDiv(id_name+i);
    }
}

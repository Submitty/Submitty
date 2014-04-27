function load_progress_bar(progress, text) {
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


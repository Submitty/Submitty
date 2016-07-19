/**
 * Toggles the page details box of the page, showing or not showing various information
 * such as number of queries run, length of time for script execution, and other details
 * useful for developers, but shouldn't be shown to normal users
 */
function togglePageDetails() {
    if (document.getElementById('page-info').style.visibility == 'visible') {
        document.getElementById('page-info').style.visibility = 'hidden';
    }
    else {
        document.getElementById('page-info').style.visibility = 'visible';
    }
}

/**
 * Remove an alert message from display. This works for successes, warnings, or errors to the
 * user
 * @param elem
 */
function removeAlert(elem) {
    $('#' + elem).fadeOut('slow', function() {
        $('#' + elem).remove();
    });
}

function assignmentChange(url, sel){
    url = url.replace("change_assignment_id", sel.value);
    window.location.href = url;
}
function versionChange(url, sel){
    url = url.replace("change_assignment_version", sel.value);
    window.location.href = url;
}

/* TODO: Add way to add new errors/notices/successes to the screen for ajax forms */
$(function() {
    setTimeout(function() {
        $('.inner-message').fadeOut();
    }, 5000);
});

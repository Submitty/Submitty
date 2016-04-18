function togglePageDetails() {
    if (document.getElementById('page-info').style.visibility == 'visible') {
        document.getElementById('page-info').style.visibility = 'hidden';
    }
    else {
        document.getElementById('page-info').style.visibility = 'visible';
    }
}

function removeAlert(elem) {
    $('#' + elem).fadeOut('slow', function() {
        $('#' + elem).remove();
    });
}

/* TODO: Add way to add new errors/notices/successes to the screen for ajax forms */

$(function() {
    setTimeout(function() {
        $('.inner-message').fadeOut();
    }, 5000);
});

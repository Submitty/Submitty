function togglePageDetails() {
    if (document.getElementById('page-info').style.visibility == 'visible') {
        document.getElementById('page-info').style.visibility = 'hidden';
    }
    else {
        document.getElementById('page-info').style.visibility = 'visible';
    }
}

function removeBox(elem) {
    $('#' + elem).fadeOut('slow');
}

$(function() {
    setTimeout(function() {
        removeBox('error-messages');
        removeBox('alert-messages');
    }, 4000);
});
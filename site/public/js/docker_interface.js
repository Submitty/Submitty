/* exported collapseSection, addImage */
/**
* toggles visibility of a content sections on the Docker UI
* @param {string} id of the section to toggle
* @param {string} btn_id id of the button calling this function
*/
function collapseSection(id,btn_id) {
    const tgt = document.getElementById(id);
    const btn = document.getElementById(btn_id);

    if (tgt.style.display === 'block'){
        tgt.style.display = 'none';
        btn.innerHTML = 'Expand';
    }
    else {
        tgt.style.display = 'block';
        btn.innerHTML = 'Collapse';
    }
}

function filterOnClick() {
    if ($(this).hasClass('fully-transparent')) {
        $(this).removeClass('fully-transparent');
    }
    else {
        $(this).addClass('fully-transparent');
    }
}

function addFieldOnChange() {
    const command = $(this).val();
    const regex = new RegExp('^([a-z0-9][a-z0-9._(__)-]+[a-z0-9]/)+[a-z0-9]+:[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}$');
    if (!regex.test(command)) {
        $('#send-button').attr('disabled',true);
        $('#docker-warning').css('display', '');
    }
    else {
        $('#send-button').attr('disabled',false);
        $('#docker-warning').css('display', 'none');
    }
}

function addImage(url) {
    const capability = $('#capability-form').val();
    const image = $('#add-field').val();
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            'capability': capability,
            'image': image,
            csrf_token: csrfToken
        },
        success: function(data) {
            console.log(data);
            location.reload();
        },
        error: function(err) {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}

function updateImage(url) {
    $.ajax({
        url: url,
        type: 'GET',
        data: {
            csrf_token: csrfToken
        },
        success: function(data) {
            console.log(data);
            location.reload();
        },
        error: function(err) {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}

$(document).ready(() => {
    $('.filter-buttons').on('click', filterOnClick);
    $('#add-field').on('input', addFieldOnChange);
});

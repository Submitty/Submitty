/* exported collapseSection, addImage, updateImage */
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
    const this_filter = $(this).data('capability');

    $('.filter-buttons').each(function (){
        $(this).addClass('fully-transparent');
    });

    if ($(this).hasClass('fully-transparent')) {
        $(this).removeClass('fully-transparent');
    }

    $('.image-row').each(function() {
        const this_row = $(this);
        let hide = true;
        $(this).find('.badge').each(function (){
            if ($(this).text() == this_filter) {
                hide = false;
            }
        });
        if (hide) {
            this_row.hide();
        }
        else {
            this_row.show();
        }
    });
}

function showAll() {
    $('.image-row').show();
    $('.filter-buttons').removeClass('fully-transparent');
}

function addFieldOnChange() {
    const command = $(this).val();
    const regex = new RegExp('^[a-z0-9]+[a-z0-9._(__)-]*[a-z0-9]+/[a-z0-9]+[a-z0-9._(__)-]*[a-z0-9]+:[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}$');
    if (!regex.test(command)) {
        $('#send-button').attr('disabled',true);
        if (command !== '') {
            $('#docker-warning').css('display', '');
            localStorage.setItem('capability', command);
        }
        else {
            localStorage.removeItem('capability');
        }
    }
    else {
        $('#send-button').attr('disabled',false);
        $('#docker-warning').css('display', 'none');
        localStorage.setItem('capability', command);
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
            // eslint-disable-next-line no-undef
            csrf_token: csrfToken,
        },
        success: function(data) {
            const json = JSON.parse(data);
            if (json.status == 'success') {
                $('#add-field').val('');
                localStorage.removeItem('capability');
                // eslint-disable-next-line no-undef
                displaySuccessMessage(json.data);
            }
            else {
                // eslint-disable-next-line no-undef
                displayErrorMessage(json.message);
            }
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
            // eslint-disable-next-line no-undef
            csrf_token: csrfToken,
        },
        success: function(data) {
            const json = JSON.parse(data);
            if (json.status == 'success') {
                // eslint-disable-next-line no-undef
                displaySuccessMessage(json.data);
            }
            else {
                // eslint-disable-next-line no-undef
                displayErrorMessage(json.message);
            }
        },
        error: function(err) {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}

$(document).ready(() => {
    $('.filter-buttons').on('click', filterOnClick);
    $('#show-all').on('click', showAll);
    $('#add-field').val(localStorage.getItem('capability'));
    $('#add-field').on('input', addFieldOnChange);
    $('#add-field').trigger('input');
});

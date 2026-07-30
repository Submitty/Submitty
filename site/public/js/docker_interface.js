/* exported collapseSection, openRemoveDialog, submitRemoveImage, removeImage, addImage, updateImage */
/* global csrfToken, displayErrorMessage, displaySuccessMessage, showPopup, closePopup */
/**
* toggles visibility of a content sections on the Docker UI
* @param {string} id of the section to toggle
* @param {string} btn_id id of the button calling this function
*/
function collapseSection(id, btn_id) {
    const tgt = document.getElementById(id);
    const btn = document.getElementById(btn_id);

    if (tgt.style.display === 'block') {
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

    $('.filter-buttons').each(function () {
        $(this).addClass('fully-transparent');
    });

    $(this).removeClass('fully-transparent');

    $('.image-row').each(function () {
        const this_row = $(this);
        let hide = true;
        $(this).find('.badge').each(function () {
            if ($(this).text() === this_filter) {
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
        $('#send-button').attr('disabled', true);
        if (command !== '') {
            $('#docker-warning').css('display', '');
        }
    }
    else {
        $('#send-button').attr('disabled', false);
        $('#docker-warning').css('display', 'none');
    }
}

let removeDialogUrl = null;

function openRemoveDialog(button, url) {
    removeDialogUrl = url;
    const primary = button.dataset.imageId;
    const aliases = (button.dataset.aliases || '').split(',').filter(Boolean);

    const options = $('#remove-image-options');
    options.empty();

    const names = [{ name: primary, primary: true }]
        .concat(aliases.map((a) => ({ name: a, primary: false })));

    names.forEach((entry, i) => {
        const checkboxId = `remove-image-option-${i}`;
        const wrapper = $('<div>', { class: 'remove-image-option' });
        const label = $('<label>', { for: checkboxId });
        const checkbox = $('<input>', {
            type: 'checkbox',
            id: checkboxId,
            class: 'remove-image-checkbox',
            value: entry.name,
        }).prop('checked', entry.primary);
        checkbox.attr('data-testid', 'remove-image-checkbox');
        label.append(checkbox);
        label.append(document.createTextNode(` ${entry.name}${entry.primary ? ' (primary)' : ''}`));
        wrapper.append(label);
        options.append(wrapper);
    });

    $('#remove-image-error').text('');
    showPopup('#remove-image-form');
}

function submitRemoveImage() {
    const selected = $('.remove-image-checkbox:checked').map(function () {
        return $(this).val();
    }).get();

    if (selected.length === 0) {
        $('#remove-image-error').text('Select at least one name to remove.');
        return;
    }

    closePopup('remove-image-form');
    removeImage(removeDialogUrl, selected);
}

function removeImage(url, images) {
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            images: images,
            csrf_token: csrfToken,
        },
        success: (data) => {
            const json = JSON.parse(data);
            if (json.status === 'success') {
                sessionStorage.setItem('successMessage', json.data);
                location.reload();
            }
            else {
                displayErrorMessage(json.message);
            }
        },
        error: (err) => {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}

function addImage(url) {
    const capability = $('#capability-form').val();
    const image = $('#add-field').val();
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            capability: capability,
            image: image,
            csrf_token: csrfToken,
        },
        success: (data) => {
            const json = JSON.parse(data);
            if (json.status === 'success') {
                $('#add-field').val('');
                sessionStorage.setItem('successMessage', json.data);
                location.reload();
            }
            else {
                displayErrorMessage(json.message);
            }
        },
        error: (err) => {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}

function updateImage(url) {
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            csrf_token: csrfToken,
        },
        success: (data) => {
            const json = JSON.parse(data);
            if (json.status === 'success') {
                displaySuccessMessage(json.data);
            }
            else {
                displayErrorMessage(json.message);
            }
        },
        error: (err) => {
            console.error(err);
            window.alert('Something went wrong. Please try again.');
        },
    });
}

$(document).ready(() => {
    $('.filter-buttons').on('click', filterOnClick);
    $('#show-all').on('click', showAll);
    $('#add-field').on('input', addFieldOnChange);
    $('#add-field').trigger('input');
});

window.addEventListener('DOMContentLoaded', () => {
    const successMessage = sessionStorage.getItem('successMessage');
    if (successMessage) {
        displaySuccessMessage(successMessage);

        // Clear the message from sessionStorage so it doesn't show again
        sessionStorage.removeItem('successMessage');
    }
});

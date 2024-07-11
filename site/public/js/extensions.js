/* global buildCourseUrl */
/* global luxon */
/* exported confirmExtension, clearDate, deleteHomeworkExtension, setLateDays */
const DateTime = luxon.DateTime;

$(document).ready(() => {
    $('#gradeable-select').change(() => {
        const g_id = $('#gradeable-select').val();
        const expirationDate = DateTime.local().plus({ days: 1 });
        const expirationDateJS = expirationDate.toJSDate();
        Cookies.set('exception_gid', g_id, { expires: expirationDateJS });
        // eslint-disable-next-line no-self-assign
        window.location = window.location; // pseudo post/redirect/get pattern
    });
});

function updateHomeworkExtension() {
    const fd = new FormData($('#extensions-form').get(0));
    const url = buildCourseUrl(['extensions', 'update']);
    $.ajax({
        url: url,
        type: 'POST',
        data: fd,
        processData: false,
        cache: false,
        contentType: false,
        success: function (data) {
            let json;
            try {
                json = JSON.parse(data);
            }
            catch (err) {
                window.alert('Error parsing data. Please try again.');
            }
            if (json['data'] && json['data']['is_team']) {
                extensionPopup(json);
            }
            else {
                // eslint-disable-next-line no-self-assign
                window.location = window.location; // pseudo post/redirect/get pattern
            }
        },
        error: function () {
            window.alert('Something went wrong. Please try again.');
        },
    });
}

function deleteHomeworkExtension(user) {
    $('#user_id').val(user);
    $('#late-days').val(0);
    $('#reason-for-exception').val('');
    updateHomeworkExtension();
}

function clearDate() {
    document.getElementById('late-calendar').value = '';
}

function setLateDays() {
    const newDate = luxon.DateTime.fromISO($('#late-calendar').val());
    const oldDate = luxon.DateTime.fromISO($('#due-date').data('date'));
    const diff = newDate.diff(oldDate, 'days').days;
    if (!Number.isNaN(diff)) {
        document.getElementById('late-days').value = diff;
    }
}

function confirmExtension(option) {
    $('.popup-form').css('display', 'none');
    $('input[name="option"]').val(option);
    updateHomeworkExtension();
    $('input[name="option"]').val(-1);
}

function extensionPopup(json) {
    $('.popup-form').css('display', 'none');
    const form = $('#more_extension_popup');
    form[0].outerHTML = json['data']['popup'];
    $('#more_extension_popup').css('display', 'block');
    $('#team-extension-cancel').focus();
}

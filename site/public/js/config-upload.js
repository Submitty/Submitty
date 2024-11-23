/* global buildCourseUrl */

$(document).ready(() => {
    $('#file_upload').on('change', () => {
        $('#upload_form').submit();
    });
});

function openRenamePopup(file_path) {
    const url = `${buildCourseUrl(['autograding_config', 'usage'])}?config_path=${encodeURIComponent(file_path)}`;
    $('#alert_in_use').html('');
    $('#gradeables_using_config').empty();
    $.ajax({
        url: url,
        success(data) {
            const gradeable_ids = JSON.parse(data)['data'];
            if (gradeable_ids.length > 0) {
                $('#alert_in_use').html('Note: Currently these gradeables are using this config, rename at your own risk');
                for (let i = 0; i < gradeable_ids.length; i++) {
                    $('#gradeables_using_config').append(`<li>${gradeable_ids[i]}</li>`);
                }
            }
        },
    });
    $('#rename_config_popup').css('display', 'block');
    $('#curr_config_name').val(file_path);
}

function openDeletePopup(file_path) {
    const message = $('[data-name="delete-config-message"]');
    message.html('');
    message.append(`<b>${file_path}</b>`);
    $('[name="config_path"]').val(file_path);
    $('#delete_config_popup').css('display', 'block');
}

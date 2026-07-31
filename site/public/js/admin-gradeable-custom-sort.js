/* global buildCourseUrl, displaySuccessMessage, csrfToken, showPopup, closePopup, captureTabInModal */

function getCustomSortGradeableId() {
    return $('#g_id').val();
}

function newCustomSortCsvUploadForm() {
    $('#custom-sort-csv-upload-file').val('');
    $('#custom-sort-csv-upload-error').hide().text('');
    showPopup('#custom-sort-csv-upload-form');
    captureTabInModal('custom-sort-csv-upload-form');
}

function showCustomSortUploadError(message) {
    $('#custom-sort-csv-upload-error').text(message).show();
}

$(document).ready(() => {
    $(document).off('mousedown', '#custom-sort-csv-upload-submit').on('mousedown', '#custom-sort-csv-upload-submit', () => {
        const file_input = $('#custom-sort-csv-upload-file').get(0);
        const f = file_input.files[0];

        if (!f) {
            showCustomSortUploadError('Please choose a CSV file first.');
            return;
        }

        const reader = new FileReader();
        reader.readAsText(f);
        reader.onload = function () {
            const gradeable_id = getCustomSortGradeableId();

            $.ajax(buildCourseUrl(['gradeable', gradeable_id, 'custom_sort', 'csv']), {
                type: 'POST',
                data: { csrf_token: csrfToken, big_file: reader.result },
            })
                .done((response) => {
                    let parsed;
                    try {
                        parsed = JSON.parse(response);
                    }
                    catch (e) {
                        showCustomSortUploadError('Unexpected response from the server. Please refresh and try again.');
                        return;
                    }

                    if (parsed['status'] === 'success') {
                        closePopup('custom-sort-csv-upload-form');
                        displaySuccessMessage('Custom grading order uploaded successfully. Refreshing page...');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                    else {
                        showCustomSortUploadError(parsed['message'] || 'Failed to upload the custom grading order.');
                    }
                })
                .fail(() => {
                    showCustomSortUploadError('Failed to reach the server. Please check your connection and try again.');
                });
        };
    });

    $(document).off('mousedown', '#clear_custom_sort').on('mousedown', '#clear_custom_sort', () => {
        if (!confirm('Revert to the default grading order? This does not delete the peer assignment, only the custom position data.')) {
            return;
        }
        const gradeable_id = getCustomSortGradeableId();

        $.ajax(buildCourseUrl(['gradeable', gradeable_id, 'custom_sort', 'clear']), {
            type: 'POST',
            data: { csrf_token: csrfToken },
        })
            .done((response) => {
                let parsed;
                try {
                    parsed = JSON.parse(response);
                }
                catch (e) {
                    window.alert('[SAVE ERROR] Refresh Page');
                    return;
                }

                if (parsed['status'] === 'success') {
                    displaySuccessMessage('Custom grading order cleared. Refreshing page...');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
                else {
                    window.alert(parsed['message'] || 'Failed to clear the custom grading order.');
                }
            })
            .fail(() => {
                window.alert('[SAVE ERROR] Refresh Page');
            });
    });
});

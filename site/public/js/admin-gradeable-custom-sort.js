/* global buildCourseUrl, submitAJAX, displayErrorMessage, displaySuccessMessage, csrfToken */

function getCustomSortGradeableId() {
    return $('#g_id').val();
}

$(document).ready(() => {
    $(document).off('mousedown', '#custom_sort_csv_submit').on('mousedown', '#custom_sort_csv_submit', () => {
        const file_input = $('#custom_sort_csv_file').get(0);
        const f = file_input.files[0];
        if (!f) {
            displayErrorMessage('Please choose a CSV file first.');
            return;
        }

        const reader = new FileReader();
        reader.readAsText(f);
        reader.onload = function () {
            const gradeable_id = getCustomSortGradeableId();

            submitAJAX(
                buildCourseUrl(['gradeable', gradeable_id, 'custom_sort', 'csv']),
                { csrf_token: csrfToken, big_file: reader.result },
                () => {
                    displaySuccessMessage('Custom grading order uploaded successfully. Refreshing page...');
                    window.location.reload();
                },
                () => {
                    displayErrorMessage("Failed to upload custom grading order.");
                },
            );
        };
    });

    $(document).off('mousedown', '#clear_custom_sort').on('mousedown', '#clear_custom_sort', () => {
        if (!confirm('Revert to the default grading order? This does not delete the peer assignment, only the custom position data.')) {
            return;
        }
        const gradeable_id = getCustomSortGradeableId();
        submitAJAX(
            buildCourseUrl(['gradeable', gradeable_id, 'custom_sort', 'clear']),
            { csrf_token: csrfToken },
            () => {
                window.location.reload();
            },
            () => {},
        );
    });
});

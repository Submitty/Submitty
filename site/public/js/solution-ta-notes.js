/* global buildCourseUrl, displaySuccessMessage, displayErrorMessage, csrfToken, previewMarkdown */
/* exported updateSolutionTaNotes, previewSolutionNotesMarkdown */
function updateSolutionTaNotes(gradeable_id, component_id, itempool_item) {
    const data = {
        solution_text: $(`#textbox-solution-${component_id}`).val().trim(),
        component_id,
        itempool_item,
        csrf_token: csrfToken,
    };
    $.ajax({
        url: buildCourseUrl(['gradeable', gradeable_id, 'solution_ta_notes']),
        type: 'POST',
        data,
        success: function (res) {
            res = JSON.parse(res);
            if (res.status === 'success') {
                displaySuccessMessage('Solution has been updated successfully...');
                // Dom manipulation after the Updating/adding the solution note
                $(`#solution-box-${component_id}`).attr('data-first-edit', 0);

                // Updating the last edit info
                $(`#solution-box-${component_id} .last-edit`).removeClass('hide');
                $(`#solution-box-${component_id} .last-edit i.last-edit-time`).text(res.data.edited_at);
                $(`#solution-box-${component_id} .last-edit i.last-edit-author`).text(
                    res.data.current_user_id === res.data.author ? `${res.data.author} (You)` : res.data.author,
                );
                // Updating the saved notes with the latest solution
                $(`#sol-textbox-cont-${component_id}-saved .solution-notes-text`).text(res.data.solution_text);
            }
            else {
                displayErrorMessage('Something went wrong while updating the solution...');
            }
        },
        error: function(err) {
            console.log(err);
        },
    });
}

function previewSolutionNotesMarkdown() {
    const component_id = $(this).closest('.solution-cont').data('component_id');
    const markdown_textarea = $(`textarea#textbox-solution-${component_id}`);
    const preview_element = $(`#solution_notes_preview_${component_id}`);
    const preview_button = $(this);
    const content = markdown_textarea.val();

    previewMarkdown(markdown_textarea, preview_element, preview_button, {content: content});
}

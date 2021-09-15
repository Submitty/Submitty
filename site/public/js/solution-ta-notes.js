/* global buildCourseUrl, displaySuccessMessage, displayErrorMessage, csrfToken, previewMarkdown */
/* exported updateSolutionTaNotes, showSolutionTextboxCont, cancelEditingSolution, previewSolutionNotesMarkdown */
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
                // $(`#edit-solution-btn-${component_id}`).removeClass('hide');
                // $(`#sol-textbox-cont-${component_id}-saved`).removeClass('hide');
                // $(`#sol-textbox-cont-${component_id}-edit`).addClass('hide');

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
                displayErrorMessage('Something went wrong while upating the solution...');
            }
        },
        error: function(err) {
            console.log(err);
        },
    });
}

function showSolutionTextboxCont(currentEle, solTextboxCont, noSolutionCont) {
    // $(currentEle).addClass('hide');
    // // Show the textbox to start writing out the solutions
    // if ($(solTextboxCont).hasClass('hide')) {
    //     $(solTextboxCont).removeClass('hide');
    //     $(noSolutionCont).addClass('hide');
    //}
}

function cancelEditingSolution(componentId) {
    const isFirstEdit = $(`#solution-box-${componentId}`).attr('data-first-edit');

    // if (+isFirstEdit) {
    //     $(`#show-sol-btn-${componentId}`).removeClass('hide');
    //     $(`.solution-notes-text-${componentId}`).removeClass('hide');
    //     $(`#sol-textbox-cont-${componentId}-edit`).addClass('hide');
    // }
    // else {
    //     $(`#edit-solution-btn-${componentId}`).removeClass('hide');
    //     $(`#sol-textbox-cont-${componentId}-saved`).removeClass('hide');
    //     $(`#sol-textbox-cont-${componentId}-edit`).addClass('hide');
    // }
}

function previewSolutionNotesMarkdown() {
    const component_id = $(this).closest('.solution-cont').data('component_id');
    const markdown_textarea = $(`textarea#textbox-solution-${component_id}`);
    const preview_element = $(`#solution_notes_preview_${component_id}`);
    const preview_button = $(this);
    const content = markdown_textarea.val();

    console.log('component_id', component_id);
    console.log('markdown_textarea', markdown_textarea);
    console.log('preview_element', preview_element);
    console.log('preview_button', preview_button);
    console.log('content', content);

    previewMarkdown(markdown_textarea, preview_element, preview_button, {content: content});
}

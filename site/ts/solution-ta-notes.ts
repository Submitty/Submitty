import { displayErrorMessage, displaySuccessMessage } from './utils/server';

interface SolutionTaNotesResponse {
    status: string;
    data: {
        edited_at: string;
        author: string;
        current_user_id: string;
    };
}

declare global {
    interface Window {
        updateSolutionTaNotes: typeof updateSolutionTaNotes;
        detectSolutionChange: typeof detectSolutionChange;
    }
}

function updateSolutionTaNotes(gradeable_id: string, component_id: string, itempool_item: string) {
    const data = {
        solution_text: ($(`#textbox-solution-${component_id}`).val() as string ?? '').trim(),
        component_id,
        itempool_item,
        csrf_token: window.csrfToken,
    };
    $.ajax({
        url: buildCourseUrl(['gradeable', gradeable_id, 'solution_ta_notes']),
        type: 'POST',
        data,
        success: function (res: string) {
            const response = JSON.parse(res) as SolutionTaNotesResponse;
            if (response.status === 'success') {
                displaySuccessMessage('Solution has been updated successfully');
                // Dom manipulation after the Updating/adding the solution note
                $(`#solution-box-${component_id}`).attr('data-first-edit', '0');

                // Updating the last edit info
                $(`#solution-box-${component_id} .last-edit`).removeClass('hide');
                $(`#solution-box-${component_id} .last-edit i.last-edit-time`).text(response.data.edited_at);
                $(`#solution-box-${component_id} .last-edit i.last-edit-author`).text(
                    response.data.current_user_id === response.data.author ? `${response.data.author} (You)` : response.data.author,
                );

                $(`#solution-box-${component_id}`).find('.solution-cont')
                    .attr('data-original-solution', ($(`#textbox-solution-${component_id}`).val() as string ?? ''));

                const save_button = $(`#solution-box-${component_id}`).find('.solution-save-btn');
                save_button.prop('disabled', true);
            }
            else {
                displayErrorMessage('Something went wrong while updating the solution');
            }
        },
        error: function (err) {
            console.log(err);
        },
    });
}
window.updateSolutionTaNotes = updateSolutionTaNotes;

// set Save button class depending on if the solution has been altered from the previous solution
function detectSolutionChange(this: HTMLTextAreaElement) {
    const textarea = $(this);
    const solution_div = textarea.closest('.solution-cont');
    const save_button = solution_div.find('.solution-save-btn');
    if (textarea.val() !== solution_div.attr('data-original-solution')) {
        save_button.prop('disabled', false);
    }
    else {
        save_button.prop('disabled', true);
    }
}
window.detectSolutionChange = detectSolutionChange;

import { getCsrfToken } from '../../../utils/server';

declare global {
    interface Window{
        detectSolutionChange(this: HTMLElement): void;
    }
}

function updateSolutionTaNotes(gradeable_id: string, component_id: string, itempool_item: string) {
    const data = {
        solution_text: ($(`#textbox-solution-${component_id}`).val() as string).trim(),
        component_id,
        itempool_item,
        csrf_token: getCsrfToken(),
    };
    $.ajax({
        url: buildCourseUrl(['gradeable', gradeable_id, 'solution_ta_notes']),
        type: 'POST',
        data,
        success: function (res) {
            res = JSON.parse(res);
            if (res.status === 'success') {
                displaySuccessMessage('Solution has been updated successfully');
                // Dom manipulation after the Updating/adding the solution note
                $(`#solution-box-${component_id}`).attr('data-first-edit', 0);

                // Updating the last edit info
                $(`#solution-box-${component_id} .last-edit`).removeClass('hide');
                $(`#solution-box-${component_id} .last-edit i.last-edit-time`).text(res.data.edited_at);
                $(`#solution-box-${component_id} .last-edit i.last-edit-author`).text(
                    res.data.current_user_id === res.data.author ? `${res.data.author} (You)` : res.data.author,
                );

                $(`#solution-box-${component_id}`).find('.solution-cont')
                    .attr('data-original-solution', $(`#textbox-solution-${component_id}`).val() as string);

                const save_button = $(`#solution-box-${component_id}`).find('.solution-save-btn');
                save_button.prop('disabled', true);
            }
            else {
                displayErrorMessage('Something went wrong while updating the solution');
            }
        },
        error: function(err) {
            console.log(err);
        },
    });
}

//set Save button class depending on if the solution has been altered from the previous solution
function detectSolutionChange(this: HTMLElement) {
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

$(() => {
    $('#solution_ta_notes .solution-save-btn').on('click', (event: JQuery.TriggeredEvent) => {
        const gradeable_id = $(event.currentTarget!).attr('data-gradeable-id')!;
        const component_id = $(event.currentTarget!).attr('data-component-id')!;
        const itempool_item = $(event.currentTarget!).attr('data-itempool-item')!;

        updateSolutionTaNotes(gradeable_id, component_id, itempool_item);
    });
});

// Needed for MarkdownArea call
window.detectSolutionChange = detectSolutionChange;

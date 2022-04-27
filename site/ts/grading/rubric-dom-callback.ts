import { getCsrfToken } from '../utils/server';

import * as rubricDomAccess from './rubric-dom-access';
import * as rubricAjax from './rubric-ajax';
import { dividesEvenly, NO_COMPONENT_ID } from './rubric-base';
import * as rubric from './rubric';

// TODO: This should be removed as code that uses these functions are moved
// to modules.
declare global {
    interface Window{
        onChangeOverallComment(): void;
        onToggleEditMode(): void;
    }
}


/**
 * Called when the 'add new mark' div gets pressed
 * @param me DOM element of the 'add new mark' div
 */
export function onAddNewMark(me: HTMLElement): void {
    rubric.addNewMark(rubricDomAccess.getComponentIdFromDOMElement(me))
        .catch((err) => {
            console.error(err);
            alert(`Error adding mark! ${err.message}`);
        });
}

/**
 * Called when a mark is marked for deletion
 * @param me DOM Element of the delete button
 */
export function onDeleteMark(me: HTMLElement): void {
    $(me).parents('.mark-container').toggleClass('mark-deleted');
}

/**
 * Called when a mark marked for deletion gets restored
 * @param me DOM Element of the restore button
 */
export function onRestoreMark(me: HTMLElement): void {
    $(me).parents('.mark-container').toggleClass('mark-deleted');
}

/**
 * Called when a component is deleted
 * @param me DOM Element of the delete button
 */
export function onDeleteComponent(me: HTMLElement): void {
    if (!confirm('Are you sure you want to delete this component?')) {
        return;
    }
    rubric.deleteComponent(rubricDomAccess.getComponentIdFromDOMElement(me))
        .catch((err) => {
            console.error(err);
            alert(`Failed to delete component! ${err.message}`);
        })
        .then(() => {
            return rubric.reloadInstructorEditRubric(rubricDomAccess.getGradeableId(), rubricDomAccess.isItempoolAvailable(), rubricDomAccess.getItempoolOptions());
        })
        .catch((err) => {
            alert(`Failed to reload rubric! ${err.message}`);
        });
}

/**
 * Called when the 'add new component' button is pressed
 */
export function onAddComponent(peer: boolean): void {
    rubric.addComponent(peer)
        .catch((err) => {
            console.error(err);
            alert(`Failed to add component! ${err.message}`);
        })
        .then(() => {
            return rubric.closeAllComponents(true);
        })
        .then(() => {
            return rubric.reloadInstructorEditRubric(rubricDomAccess.getGradeableId(), rubricDomAccess.isItempoolAvailable(), rubricDomAccess.getItempoolOptions());
        })
        .then(() => {
            return rubric.openComponent(rubricDomAccess.getComponentIdByOrder(rubricDomAccess.getComponentCount() - 1));
        })
        .catch((err) => {
            alert(`Failed to reload rubric! ${err.message}`);
        });
}

/**
 * Called when the 'Import Components' button is pressed
 */
export function importComponentsFromFile(): void {
    const submit_url = buildCourseUrl(['gradeable', rubricDomAccess.getGradeableId(), 'components', 'import']);
    const formData = new FormData();

    const files = (<HTMLInputElement>$('#import-components-file')[0]).files;

    if (files === null || files.length === 0) {
        return;
    }

    // Files selected
    for (let i = 0; i < files.length; i++) {
        formData.append(`files${i}`, files[i], files[i].name);
    }

    formData.append('csrf_token', getCsrfToken());

    $.ajax({
        url: submit_url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        dataType: 'json',
        success: function (response: { status: string; message: string | undefined; }) {
            if (response.status !== 'success') {
                console.error(`Something went wrong importing components: ${response.message}`);
            }
            else {
                location.reload();
            }
        },
        error: function (e) {
            console.log(e);
            alert('Error parsing response from server. Please copy the contents of your Javascript Console and ' +
                'send it to an administrator, as well as what you were doing and what files you were uploading. - [handleUploadGradeableComponents]');
        },
    });
}

/**
 * Called when the point value of a common mark changes
 * @param me DOM Element of the mark point entry
 */
export function onMarkPointsChange(me: HTMLElement): void {
    rubric.refreshComponentHeader(rubricDomAccess.getComponentIdFromDOMElement(me), true)
        .catch((err) => {
            console.error(err);
            alert(`Error updating component! ${err.message}`);
        });
}

/**
 * Called when the mark stats button is pressed
 * @param me DOM Element of the mark stats button
 */
export function onGetMarkStats(me: HTMLElement): void {
    const component_id = rubricDomAccess.getComponentIdFromDOMElement(me);
    const mark_id = rubricDomAccess.getMarkIdFromDOMElement(me);
    rubricAjax.ajaxGetMarkStats(rubricDomAccess.getGradeableId(), component_id, mark_id)
        .then((stats) => {
            const component_title = rubricDomAccess.getComponentFromDOM(component_id).title ?? '';
            const mark_dom = rubricDomAccess.getMarkFromDOM(mark_id);
            const mark_title = mark_dom ? mark_dom.title : '';

            rubricDomAccess.openMarkStatsPopup(component_title, mark_title, stats);
        })
        .catch((err) => {
            alert(`Failed to get stats for mark: ${err.message}`);
        });
}

/**
 * Called when a component gets clicked (for opening / closing)
 * @param me DOM Element of the component header div
 * @param edit_mode editing from ta grading page or instructor edit gradeable page
 */
export function onClickComponent(me: HTMLElement, edit_mode = false): void {
    const component_id = rubricDomAccess.getComponentIdFromDOMElement(me);
    rubric.toggleComponent(component_id, true, edit_mode)
        .catch((err) => {
            console.error(err);
            rubricDomAccess.setComponentInProgress(component_id, false);
            alert(`Error opening/closing component! ${err.message}`);
        });
}

/**
 * Called when the 'cancel' button is pressed on an open component
 * @param me DOM Element of the cancel button
 */
export function onCancelComponent(me: HTMLElement): void {
    const component_id = rubricDomAccess.getComponentIdFromDOMElement(me);
    const gradeable_id = rubricDomAccess.getGradeableId();
    const anon_id = rubricDomAccess.getAnonId();
    rubricAjax.ajaxGetGradedComponent(gradeable_id, component_id, anon_id).then((component) => {
        const customMarkNote = $(`#component-${component_id}`).find('.mark-note-custom').val();
        // If there is any changes made in comment of a component , prompt the TA
        if ((component && component.comment !== customMarkNote) || (!component && customMarkNote !== '')) {
            if (confirm('Are you sure you want to discard all changes to the student message?')) {
                rubric.toggleComponent(component_id, false)
                    .catch((err) => {
                        console.error(err);
                        alert(`Error closing component! ${err.message}`);
                    });
            }
        }
        // There is no change in comment, i.e it is same as the saved comment (before)
        else {
            rubric.toggleComponent(component_id, false)
                .catch((err) => {
                    console.error(err);
                    alert(`Error closing component! ${err.message}`);
                });
        }
    });
}

export function onCancelEditRubricComponent(me: HTMLElement): void {
    const component_id = rubricDomAccess.getComponentIdFromDOMElement(me);
    rubric.toggleComponent(component_id, false, true);
}

/**
 * Called when the overall comment box is changed
 */
export function onChangeOverallComment(): void {
    // Get the current grader so that we can get their comment from the dom.
    const grader = rubricDomAccess.getGraderId();
    const currentOverallComment = $(`textarea#overall-comment-${grader}`).val() as string;
    const previousOverallComment = $(`textarea#overall-comment-${grader}`).data('previous-comment') as string;

    if (currentOverallComment !== previousOverallComment && currentOverallComment !== undefined) {
        $('.overall-comment-status').text('Saving Changes...');
        // If anything has changed, save the changes.
        rubricAjax.ajaxSaveOverallComment(rubricDomAccess.getGradeableId(), rubricDomAccess.getAnonId(), currentOverallComment).then(() => {
            $('.overall-comment-status').text('All Changes Saved');
            // Update the current comment in the DOM.
            $(`textarea#overall-comment-${grader}`).data('previous-comment', currentOverallComment);
        }).catch(() => {
            $('.overall-comment-status').text('Error Saving Changes');
        });
    }
}

/**
 * When the component order changes, update the server
 */
export function onComponentOrderChange(): void {
    rubricAjax.ajaxSaveComponentOrder(rubricDomAccess.getGradeableId(), rubricDomAccess.getComponentOrders())
        .catch((err) => {
            console.error(err);
            alert(`Error reordering components! ${err.message}`);
        });
}

/**
 * Called when a mark is clicked in grade mode
 * @param me DOM Element of the mark div
 */
export function onToggleMark(me: HTMLElement): void {
    rubric.toggleCommonMark(rubricDomAccess.getComponentIdFromDOMElement(me), rubricDomAccess.getMarkIdFromDOMElement(me))
        .catch((err) => {
            console.error(err);
            alert(`Error toggling mark! ${err.message}`);
        });
}

/**
 * Called when one of the custom mark fields changes
 * @param me DOM Element of one of the custom mark's elements
 */
export function onCustomMarkChange(me: HTMLElement): void {
    rubric.updateCustomMark(rubricDomAccess.getComponentIdFromDOMElement(me))
        .catch((err) => {
            console.error(err);
            alert(`Error updating custom mark! ${err.message}`);
        });
}

/**
 * Toggles the 'checked' state of the custom mark.  This effectively
 *  makes the 'score' and 'comment' values 0 and '' respectively when
 *  loading the graded component from the DOM, but leaves the values in
 *  the DOM if the user toggles this again.
 * @param me
 */
export function onToggleCustomMark(me: HTMLElement): void {
    const component_id = rubricDomAccess.getComponentIdFromDOMElement(me);
    const graded_component = rubricDomAccess.getGradedComponentFromDOM(component_id);
    if (graded_component.comment === '') {
        rubricDomAccess.setCustomMarkError(component_id, true);
        return;
    }
    rubricDomAccess.toggleDOMCustomMark(component_id);
    rubric.toggleCustomMark(component_id)
        .catch((err) => {
            console.error(err);
            alert(`Error toggling custom mark! ${err.message}`);
        });
}

/**
 * Callback for the 'verify' buttons
 * @param me DOM Element of the verify button
 */
export function onVerifyComponent(me: HTMLElement): void {
    rubric.verifyComponent(rubricDomAccess.getComponentIdFromDOMElement(me))
        .catch((err) => {
            console.error(err);
            alert(`Error verifying component! ${err.message}`);
        });
}

/**
 * Callback for the 'verify all' button
 */
export function onVerifyAll(): void {
    rubric.verifyAllComponents()
        .catch((err) => {
            console.error(err);
            alert(`Error verifying all components! ${err.message}`);
        });
}

/**
 * Callback for the 'edit mode' checkbox changing states
 */
export function onToggleEditMode(): void {
    // Get the open components so we know which one to open once they're all saved
    const open_component_ids = rubricDomAccess.getOpenComponentIds();
    let reopen_component_id = NO_COMPONENT_ID;

    // This prevents multiple sequential toggles from screwing things up
    rubricDomAccess.disableEditModeBox(true);

    if (open_component_ids.length !== 0) {
        reopen_component_id = open_component_ids[0];
    }
    else {
        rubricDomAccess.updateEditModeEnabled();
        rubricDomAccess.disableEditModeBox(false);
        return;
    }

    rubricDomAccess.setComponentInProgress(reopen_component_id);

    // Build a sequence to save open component
    let sequence = Promise.resolve();
    sequence = sequence.then(() => {
        return rubric.saveComponent(reopen_component_id);
    });
    // Once components are saved, reload the component in edit mode
    sequence.catch((err) => {
        console.error(err);
        alert(`Error saving component! ${err.message}`);
    })
        .then(() => {
            rubricDomAccess.updateEditModeEnabled();
            if (reopen_component_id !== NO_COMPONENT_ID) {
                return rubric.reloadGradingComponent(reopen_component_id, rubricDomAccess.isEditModeEnabled(), true);
            }
        })
        .catch((err) => {
            console.error(err);
            alert(`Error reloading component! ${err.message}`);
        })
        .then(() => {
            rubricDomAccess.disableEditModeBox(false);
        });
}

/**
 * Callback for the 'count up' option of a component in instructor edit mode
 * @param me DOM element of the 'count up' radio button
 */
export function onClickCountUp(me: HTMLElement): void {
    const component_id = rubricDomAccess.getComponentIdFromDOMElement(me);
    const mark_id = rubricDomAccess.getComponentFirstMarkId(component_id);
    rubricDomAccess.setMarkTitle(mark_id, 'No Credit');
    $.get('Mark.twig', () => {
        $("input[id^='mark-editor-']").each((index, element) => {
            const inputElement = element as HTMLInputElement;
            $(inputElement).attr('overall', 'No Credit');
            if (parseFloat(inputElement.value) < 0) {
                inputElement.style.backgroundColor = 'var(--standard-vibrant-yellow)';
            }
            else {
                inputElement.style.backgroundColor = 'var(--default-white)';
            }
        });
    });
}

/**
 * Callback for the 'count down' option of a component in instructor edit mode
 * @param me DOM element of the 'count down' radio button
 */
export function onClickCountDown(me: HTMLElement): void {
    const component_id = rubricDomAccess.getComponentIdFromDOMElement(me);
    const mark_id = rubricDomAccess.getComponentFirstMarkId(component_id);
    rubricDomAccess.setMarkTitle(mark_id, 'Full Credit');
    $.get('Mark.twig', () => {
        $("input[id^='mark-editor-']").each((_index, element) => {
            const inputElement = element as HTMLInputElement;
            $(inputElement).attr('overall", "Full Credit');
            if (parseFloat(inputElement.value) > 0) {
                inputElement.style.backgroundColor = 'var(--standard-vibrant-yellow)';
            }
            else {
                inputElement.style.backgroundColor = 'var(--default-white)';
            }
        });
    });
}

/**
 * Callback for changing on the point values for a component
 * Does not change point value if not divisible by precision
 * @param me DOM element of the input box
 */
export function onComponentPointsChange(me: HTMLElement): void {
    if (dividesEvenly(parseInt($(me).val() as string), rubricDomAccess.getPointPrecision())) {
        $(me).css('background-color', 'var(--standard-input-background)');
        rubric.refreshInstructorEditComponentHeader(rubricDomAccess.getComponentIdFromDOMElement(me), true)
            .catch((err) => {
                console.error(err);
                alert(`Failed to refresh component! ${err.message}`);
            });
    }
    else {

        // Make box red to indicate error
        $(me).css('background-color', '#ff7777');
    }
}

/**
 * Callback for changing the title for a component
 * @param me DOM element of the input box
 */
export function onComponentTitleChange(me: HTMLElement): void {
    rubricDomAccess.getComponentJQuery(rubricDomAccess.getComponentIdFromDOMElement(me)).find('.component-title-text').text($(me).val() as string);
}

/**
 * Callback for changing the page number for a component
 * @param me DOM element of the input box
 */
export function onComponentPageNumberChange(me: HTMLElement): void {
    rubricDomAccess.getComponentJQuery(rubricDomAccess.getComponentIdFromDOMElement(me)).find('.component-page-number-text').text($(me).val() as string);
}

/**
 * Callback for changing the 'publish' setting of a mark
 * @param me DOM element of the check box
 */
export function onMarkPublishChange(me: HTMLElement): void {
    rubricDomAccess.getMarkJQuery(rubricDomAccess.getMarkIdFromDOMElement(me)).toggleClass('mark-publish');
}

// Remove this if MarkdownArea.twig and the Twig files that
// pass in 'textarea_onchange' are moved into TS
window.onChangeOverallComment = onChangeOverallComment;

window.onToggleEditMode = onToggleEditMode;

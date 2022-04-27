import { Component } from './types/Component';
import { GradedComponent } from './types/GradedComponent';
import { Mark } from './types/Mark';
import { RubricTotal } from './types/RubricTotal';
import { Score } from './types/Score';

import * as rubricDomAccess from './rubric-dom-access';
import * as rubricAjax from './rubric-ajax';
import * as rubricConflict from './rubric-conflict';
import { PDF_PAGE_INSTRUCTOR, OLD_MARK_LIST, OLD_GRADED_COMPONENT_LIST, getNewMarkId, COUNT_DIRECTION_DOWN, setTAGradedPeer, marksEqual, getTAGradedPeer, getMarkFromMarkArray } from './rubric-base';
import { ConflictMarks } from './types/ConflictMark';
import { GradeableRubric } from './types/GradeableRubric';

// TODO: This should be removed as code that uses these functions are moved
// to modules.
declare global {
    interface Window{
        showVerifyComponent(graded_component: GradedComponent | undefined, grader_id: string): boolean;
        setPdfPageAssignment(page: number): Promise<void>;
        reloadInstructorEditRubric(gradeable_id: string, itempool_available: boolean, itempool_options: string[]): Promise<void>;
        closeAllComponents(save_changes: boolean, edit_mode: boolean): Promise<void>;
        toggleComponent(component_id: number, saveChanges: boolean, edit_mode: boolean): Promise<void>;
        toggleCommonMark(component_id: number, mark_id: number): Promise<void>;
        scrollToComponent(component_id: number): void;
        closeComponent(component_id: number, saveChanges: boolean, edit_mode: boolean): Promise<void>;
        scrollToOverallComment(): void;
    }
}

/**
* Gets if the 'verify' button should show up for a component
* @param {Object} graded_component
* @param {string} grader_id
* @returns {boolean}
*/
export function showVerifyComponent(graded_component: GradedComponent | undefined, grader_id: string): boolean {
    return graded_component !== undefined && graded_component.grader_id !== '' && grader_id !== graded_component.grader_id;
}

/**
 * Verifies a component with the grader and reloads the component
 * @param {number} component_id
 * @returns {Promise}
 */
export function verifyComponent(component_id: number): Promise<void> {
    const gradeable_id = rubricDomAccess.getGradeableId();
    return rubricAjax.ajaxVerifyComponent(gradeable_id, component_id, rubricDomAccess.getAnonId())
        .then(() => {
            return reloadGradingComponent(component_id);
        })
        .then(() => {
            rubricDomAccess.updateVerifyAllButton();
        });
}

/**
 * Verifies all graded components and reloads the rubric
 * @returns {Promise}
 */
export function verifyAllComponents(): Promise<void> {
    const gradeable_id = rubricDomAccess.getGradeableId();
    const anon_id = rubricDomAccess.getAnonId();
    return rubricAjax.ajaxVerifyAllComponents(gradeable_id, anon_id)
        .then(() => {
            return reloadGradingRubric(gradeable_id, anon_id);
        })
        .then(() => {
            rubricDomAccess.updateVerifyAllButton();
        });
}

/**
 * Adds a blank component to the gradeable
 * @return {Promise}
 */
export function addComponent(peer: boolean): Promise<{ 'component_id': number }> {
    return rubricAjax.ajaxAddComponent(rubricDomAccess.getGradeableId(), peer);
}

/**
 * Deletes a component from the server
 * @param {number} component_id
 * @returns {Promise}
 */
export function deleteComponent(component_id: number): Promise<void> {
    return rubricAjax.ajaxDeleteComponent(rubricDomAccess.getGradeableId(), component_id);
}

/**
 * Sets the gradeable-wide page setting
 * @param {number} page PDF_PAGE_INSTRUCTOR, PDF_PAGE_STUDENT, or PDF_PAGE_NONE
 * @return {Promise}
 */
export function setPdfPageAssignment(page: number): Promise<void> {
    if (page === PDF_PAGE_INSTRUCTOR) {
        page = 1;
    }

    return closeAllComponents(true, true)
        .then(() => {
            return rubricAjax.ajaxSaveComponentPages(rubricDomAccess.getGradeableId(), { 'page': page });
        })
        .then(() => {
            // Reload the gradeable to refresh all the component's display
            return reloadInstructorEditRubric(rubricDomAccess.getGradeableId(), rubricDomAccess.isItempoolAvailable(), rubricDomAccess.getItempoolOptions());
        });
}

/**
 * Call this once on page load to load the rubric for grading a submitter
 * Note: This takes 'gradeable_id' and 'anon_id' parameters since it gets called
 *  in the 'RubricPanel.twig' server template
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @return {Promise}
 */
export function reloadGradingRubric(gradeable_id: string, anon_id: string): Promise<void> {
    let gradeable_tmp: GradeableRubric | null = null;
    return rubricAjax.ajaxGetGradeableRubric(gradeable_id)
        .catch((err) => {
            alert(`Could not fetch gradeable rubric: ${err.message}`);
        })
        .then((gradeable) => {
            gradeable_tmp = gradeable!;
            return rubricAjax.ajaxGetGradedGradeable(gradeable_id, anon_id, false);
        })
        .catch((err) => {
            alert(`Could not fetch graded gradeable: ${err.message}`);
        })
        .then((graded_gradeable) => {
            return renderGradingGradeable(rubricDomAccess.getGraderId(), gradeable_tmp!, graded_gradeable!,
                rubricDomAccess.isGradingDisabled(), rubricDomAccess.canVerifyGraders(), rubricDomAccess.getDisplayVersion());
        })
        .then((elements) => {
            rubricDomAccess.setRubricDOMElements(elements);
            return openCookieComponent();
        })
        .catch((err) => {
            alert(`Could not render gradeable: ${err.message}`);
            console.error(err);
        });

}
/**
 * Call this to update the totals and subtotals once a grader is done grading a component
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @return {Promise}
 */

export function updateTotals(gradeable_id: string, anon_id: string): Promise<void> {
    let gradeable_tmp: GradeableRubric | null = null;
    return rubricAjax.ajaxGetGradeableRubric(gradeable_id)
        .catch((err) => {
            alert(`Could not fetch gradeable rubric: ${err.message}`);
        })
        .then((gradeable) => {
            gradeable_tmp = gradeable!;
            return rubricAjax.ajaxGetGradedGradeable(gradeable_id, anon_id, false);
        })
        .catch((err) => {
            alert(`Could not fetch graded gradeable: ${err.message}`);
        })
        .then((graded_gradeable) => {
            return renderGradingGradeable(rubricDomAccess.getGraderId(), gradeable_tmp!, graded_gradeable!,
                rubricDomAccess.isGradingDisabled(), rubricDomAccess.canVerifyGraders(), rubricDomAccess.getDisplayVersion());
        })
        .then((elements) => {
            rubricDomAccess.setRubricDOMElements(elements);
        });
}

/**
 * Call this once on page load to load the peer panel.
 * Note: This takes 'gradeable_id' and 'anon_id' parameters since it gets called
 *  in the 'PeerPanel.twig' server template
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @return {Promise}
 */
export function reloadPeerRubric(gradeable_id: string, anon_id: string): Promise<void> {
    setTAGradedPeer(true);
    let gradeable_tmp: GradeableRubric | null = null;
    return rubricAjax.ajaxGetGradeableRubric(gradeable_id)
        .catch((err) => {
            alert(`Could not fetch gradeable rubric: ${err.message}`);
        })
        .then((gradeable) => {
            gradeable_tmp = gradeable!;
            return rubricAjax.ajaxGetGradedGradeable(gradeable_id, anon_id, true);
        })
        .catch((err) => {
            alert(`Could not fetch graded gradeable: ${err.message}`);
        })
        .then((graded_gradeable) => {
            return renderPeerGradeable(rubricDomAccess.getGraderId(), gradeable_tmp!, graded_gradeable!,
                true, false, rubricDomAccess.getDisplayVersion());
        })
        .catch((err) => {
            alert(`Could not render gradeable: ${err.message}`);
            console.error(err);
        })
        .then((elements) => {
            const gradingBox = $('#peer-grading-box');
            gradingBox.html(elements!);
        });
}

/**
 * Call this once on page load to load the rubric instructor editing
 * @return {Promise}
 */
export function reloadInstructorEditRubric(gradeable_id: string, itempool_available: boolean, itempool_options: string[]): Promise<void> {
    return rubricAjax.ajaxGetGradeableRubric(gradeable_id)
        .catch((err) => {
            alert(`Could not fetch gradeable rubric: ${err.message}`);
        })
        .then((gradeable) => {
            return renderInstructorEditGradeable(gradeable, itempool_available, itempool_options);
        })
        .then((elements) => {
            rubricDomAccess.setRubricDOMElements(elements);
            return refreshRubricTotalBox();
        })
        .then(() => {
            return openCookieComponent();
        })
        .catch((err) => {
            alert(`Could not render gradeable: ${err.message}`);
            console.error(err);
        });
}

/**
 * Reloads the provided component with the grader view
 * @param {number} component_id
 * @param {boolean} editable True to load component in edit mode
 * @param {boolean} showMarkList True to show mark list as open
 * @returns {Promise}
 */
export function reloadGradingComponent(component_id: number, editable = false, showMarkList = false): Promise<void> {
    let component_tmp: Component | null = null;
    const gradeable_id = rubricDomAccess.getGradeableId();
    return rubricAjax.ajaxGetComponentRubric(gradeable_id, component_id)
        .then((component) => {
            // Set the global mark list data for this component for conflict resolution
            OLD_MARK_LIST[component_id] = component.marks;

            component_tmp = component;
            return rubricAjax.ajaxGetGradedComponent(gradeable_id, component_id, rubricDomAccess.getAnonId());
        })
        .then((graded_component) => {
            // Set the global graded component list data for this component to detect changes
            OLD_GRADED_COMPONENT_LIST[component_id] = graded_component;

            // Render the grading component with edit mode if enabled,
            //  and 'true' to show the mark list
            return injectGradingComponent(component_tmp!, graded_component, editable, showMarkList);
        });
}

/**
 * Opens the component in the cookie
 * @returns {Promise}
 */
function openCookieComponent(): Promise<void> {
    const cookieComponent = rubricDomAccess.getOpenComponentIdFromCookie();
    if (!rubricDomAccess.componentExists(cookieComponent)) {
        return Promise.resolve();
    }
    return toggleComponent(cookieComponent, false)
        .then(() => {
            scrollToComponent(cookieComponent);
        });
}

/**
 * Closes all open components and the overall comment
 * @param {boolean} save_changes
 * @param {boolean} edit_mode editing from ta grading page or instructor edit gradeable page
 * @return {Promise<void>}
 */
export function closeAllComponents(save_changes: boolean, edit_mode = false): Promise<void> {
    let sequence = Promise.resolve();

    // Close all open components.  There shouldn't be more than one,
    //  but just in case there is...
    rubricDomAccess.getOpenComponentIds().forEach((id) => {
        sequence = sequence.then(() => {
            return closeComponent(id, save_changes, edit_mode);
        });
    });
    return sequence;
}

/**
 * Toggles the open/close state of a component
 * @param {number} component_id the component's id
 * @param {boolean} saveChanges
 * @param {edit_mode} editing from ta grading page or instructor edit gradeable page
 * @return {Promise}
 */
export function toggleComponent(component_id: number, saveChanges: boolean, edit_mode = false): Promise<void> {
    let action = Promise.resolve();
    // Component is open, so close it
    if (rubricDomAccess.isComponentOpen(component_id)) {
        action = action.then(() => {
            return closeComponent(component_id, saveChanges, edit_mode);
        });
    }
    else {
        action = action.then(() => {
            return closeAllComponents(saveChanges, edit_mode)
                .then(() => {
                    return openComponent(component_id);
                });
        });
    }

    // Save the open component in the cookie
    return action.then(() => {
        rubricDomAccess.updateCookieComponent();
    });
}

export function open_overall_comment_tab(user: string): void {
    const textarea = $(`#overall-comment-${user}`);
    const comment_root = textarea.closest('.general-comment-entry');

    $('#overall-comments').children().hide();
    $('#overall-comment-tabs').children().removeClass('active-btn');
    comment_root.show();
    $(`#overall-comment-tab-${user}`).addClass('active-btn');

    //if the tab is for the main user of the page
    if (!textarea.hasClass('markdown-preview')) {
        if ($(`#overall-comment-markdown-preview-${user}`).is(':hidden')) {
            textarea.show();
        }
    }
    else {
        textarea.show();
    }

    const attachmentsListUser = $(`#attachments-list-${user}`);
    if (attachmentsListUser.length !== 0) {
        const attachmentsList = $('#attachments-list');
        $(`#attachments-list-${attachmentsList.attr('data-active-user')}`).css('display', 'none');

        let isUser = false;
        if (attachmentsList.attr('data-user') === user) {
            $('#attachment-upload-form').css('display', '');
            $('#overall-comments-attachments').css('display', '');
            isUser = true;
        }
        else {
            $('#attachment-upload-form').css('display', 'none');
        }
        if (attachmentsListUser.children().length === 0) {
            attachmentsListUser.css('display', 'none');
            $('#attachments-header').css('display', 'none');
            if (!isUser) {
                $('#overall-comments-attachments').css('display', 'none');
            }
        }
        else {
            attachmentsListUser.css('display', '');
            $('#attachments-header').css('display', '');
            $('#overall-comments-attachments').css('display', '');
        }

        attachmentsList.attr('data-active-user', user);
    }

}

/**
 * Adds a new mark to the DOM and refreshes the display
 * @param {number} component_id
 * @return {Promise}
 */
export function addNewMark(component_id: number): Promise<void> {
    const component = rubricDomAccess.getComponentFromDOM(component_id);
    component.marks.push({
        id: getNewMarkId(),
        title: '',
        points: 0.0,
        publish: false,
        order: component.marks.length,
    });
    let promise = Promise.resolve();
    if (!rubricDomAccess.isInstructorEditEnabled()) {
        const graded_component = rubricDomAccess.getGradedComponentFromDOM(component_id);
        promise = promise.then(() => {
            return injectGradingComponent(component, graded_component, true, true);
        });
    }
    else {
        promise = promise.then(() => {
            return injectInstructorEditComponent(component, true);
        });
    }
    return promise;
}

/**
 * Toggles the state of a mark in grade mode
 * @return {Promise}
 */
export function toggleCommonMark(component_id: number, mark_id: number): Promise<void> {
    return rubricDomAccess.isMarkChecked(mark_id) ? unCheckMark(component_id, mark_id) : checkMark(component_id, mark_id);
}

/**
 * Call to update the custom mark state when any of the custom mark fields change
 * @param {number} component_id
 * @return {Promise}
 */
export function updateCustomMark(component_id: number): Promise<void> {
    if (rubricDomAccess.hasCustomMark(component_id)) {
        // Check the mark if it isn't already
        rubricDomAccess.checkDOMCustomMark(component_id);

        // Uncheck the first mark just in case it's checked
        return unCheckFirstMark(component_id);
    }
    else {
        // Automatically uncheck the custom mark if it's no longer relevant
        rubricDomAccess.unCheckDOMCustomMark(component_id);

        // Note: this is in the else block since `unCheckFirstMark` calls this function
        return refreshGradedComponent(component_id, true);
    }
}

/**
 * Call to toggle the custom mark 'checked' state without removing its data
 * @param {number} component_id
 * @return {Promise}
 */
export function toggleCustomMark(component_id: number): Promise<void> {
    if (rubricDomAccess.isCustomMarkChecked(component_id)) {
        // Uncheck the first mark just in case it's checked
        return unCheckFirstMark(component_id);
    }
    else {
        // Note: this is in the else block since `unCheckFirstMark` calls this function
        return refreshGradedComponent(component_id, true);
    }
}
/**
 * Opens a component for instructor edit mode
 * NOTE: don't call this function on its own.  Call 'openComponent' Instead
 * @param {number} component_id
 * @return {Promise}
 */
function openComponentInstructorEdit(component_id: number): Promise<void> {
    const gradeable_id = rubricDomAccess.getGradeableId();
    return rubricAjax.ajaxGetComponentRubric(gradeable_id, component_id)
        .then((component) => {
            // Set the global mark list data for this component for conflict resolution
            OLD_MARK_LIST[component_id] = component.marks;

            // Render the component in instructor edit mode
            //  and 'true' to show the mark list
            return injectInstructorEditComponent(component, true, true);
        });
}

/**
 * Opens a component for grading mode (including normal edit mode)
 * NOTE: don't call this function on its own.  Call 'openComponent' Instead
 * @param {number} component_id
 * @return {Promise}
 */
function openComponentGrading(component_id: number): Promise<void> {
    return reloadGradingComponent(component_id, rubricDomAccess.isEditModeEnabled(), true)
        .then(() => {
            const page = rubricDomAccess.getComponentPageNumber(component_id);
            if (page) {
                scrollToPage(page);
            }
        });
}

/**
 * Scrolls the submission panel to the page number specified by the component
 * TODO: This is currently very clunky, and only works with test files (aka upload.pdf).
 * @param {number} page_num
 * @return {void}
 */
function scrollToPage(page_num: number): void {
    const files = $('.openable-element-submissions');
    for (let i = 0; i < files.length; i++) {
        if (files[i].innerText.trim() == 'upload.pdf') {
            page_num = Math.min($('#viewer > .page').length, page_num);
            const page = $(`#pageContainer${page_num}`);
            if ($('#file-view').is(':visible')) {
                if (page.length) {
                    $('#submission_browser').scrollTop(page[0].offsetTop);
                }
            }
            else {
                viewFileFullPanel('upload.pdf', files[i].getAttribute('file-url'), page_num - 1);
            }
        }
    }
}

/**
 * Opens the requested component
 * Note: This does not close the currently open component
 * @param {number} component_id
 * @return {Promise}
 */
export function openComponent(component_id: number): Promise<void> {
    rubricDomAccess.setComponentInProgress(component_id);
    // Achieve polymorphism in the interface using this `isInstructorEditEnabled` flag
    return (rubricDomAccess.isInstructorEditEnabled() ? openComponentInstructorEdit(component_id) : openComponentGrading(component_id))
        .then(resizeNoScrollTextareas);
}

/**
 * Scroll such that a given component is visible
 * @param component_id
 */
export function scrollToComponent(component_id: number): void {
    const component = rubricDomAccess.getComponentJQuery(component_id);
    component[0].scrollIntoView();
}

/**
 * Closes a component for instructor edit mode and saves changes
 * NOTE: don't call this function on its own.  Call 'closeComponent' Instead
 * @param {number} component_id
 * @param {boolean} saveChanges If the changes to the component should be saved or discarded
 * @return {Promise}
 */
function closeComponentInstructorEdit(component_id: number, saveChanges: boolean): Promise<void> {
    let sequence = Promise.resolve();
    const component = rubricDomAccess.getComponentFromDOM(component_id);
    const countUp = rubricDomAccess.getCountDirection(component_id) !== COUNT_DIRECTION_DOWN;
    if (saveChanges) {
        sequence = sequence
            .then(() => {
                if (component.max_value == 0 && component.upper_clamp == 0 && component.lower_clamp < 0) {
                    const mark_title = 'No Penalty Points';
                    component.marks[0].title = mark_title;
                    ($(`#mark-${component.marks[0].id.toString()}`).find(':input')[1] as HTMLInputElement).value = 'No Penalty Points';

                }
                else if (component.max_value == 0 && component.upper_clamp > 0 && component.lower_clamp == 0) {
                    const mark_title = 'No Extra Credit Awarded';
                    component.marks[0].title = mark_title;
                    ($(`#mark-${component.marks[0].id.toString()}`).find(':input')[1] as HTMLInputElement).value = 'No Extra Credit Awarded';
                }
                else if (countUp) {
                    const mark_title = 'No Credit';
                    component.marks[0].title = mark_title;
                    ($(`#mark-${component.marks[0].id.toString()}`).find(':input')[1] as HTMLInputElement).value = 'No Credit';

                }
                return saveMarkList(component_id);
            })
            .then(() => {
                // Save the component title and comments
                return rubricAjax.ajaxSaveComponent(rubricDomAccess.getGradeableId(), component_id, component.title, component.ta_comment,
                    component.student_comment, component.page, component.lower_clamp,
                    component.default, component.max_value, component.upper_clamp, component.is_itempool_linked, component.itempool_option);
            });
    }
    return sequence
        .then(() => {
            return rubricAjax.ajaxGetComponentRubric(rubricDomAccess.getGradeableId(), component_id);
        })
        .then((component) => {
            // Render the component with a hidden mark list
            return injectInstructorEditComponent(component, false);
        });
}

/**
 * Closes a component for grading mode and saves changes
 * NOTE: don't call this function on its own.  Call 'closeComponent' Instead
 * @param {number} component_id
 * @param {boolean} saveChanges If the changes to the (graded) component should be saved or discarded
 * @return {Promise}
 */
function closeComponentGrading(component_id: number, saveChanges: boolean): Promise<void> {
    let sequence = Promise.resolve();
    const gradeable_id = rubricDomAccess.getGradeableId();
    const anon_id = rubricDomAccess.getAnonId();
    let component_tmp: Component | null = null;

    if (saveChanges) {
        sequence = sequence.then(() => {
            return saveComponent(component_id);
        });
    }

    // Finally, render the graded component in non-edit mode with the mark list hidden
    return sequence
        .then(() => {
            return rubricAjax.ajaxGetComponentRubric(gradeable_id, component_id);
        })
        .then((component) => {
            component_tmp = component;
        })
        .then(() => {
            return rubricAjax.ajaxGetGradedComponent(gradeable_id, component_id, anon_id);
        })
        .then((graded_component) => {
            return injectGradingComponent(component_tmp!, graded_component, false, false);
        });

}

/**
 * Closes the requested component and saves any changes if requested
 * @param {number} component_id
 * @param {boolean} saveChanges If the changes to the (graded) component should be saved or discarded
 * @param {boolean} edit_mode editing from ta grading page or instructor edit gradeable page
 * @return {Promise}
 */
function closeComponent(component_id: number, saveChanges = true, edit_mode = false): Promise<void> {
    rubricDomAccess.setComponentInProgress(component_id);
    // Achieve polymorphism in the interface using this `isInstructorEditEnabled` flag
    return (rubricDomAccess.isInstructorEditEnabled()
        ? closeComponentInstructorEdit(component_id, saveChanges)
        : closeComponentGrading(component_id, saveChanges))
        .then(() => {
            rubricDomAccess.setComponentInProgress(component_id, false);
        })
        .then(() => {
            if (!edit_mode) {
                const gradeable_id = rubricDomAccess.getGradeableId();
                const anon_id = rubricDomAccess.getAnonId();
                return updateTotals(gradeable_id, anon_id);
            }
        });
}

/**
 * Scroll such that the overall comment is visible
 */
function scrollToOverallComment(): void {
    const comment = rubricDomAccess.getOverallCommentJQuery();
    comment[0].scrollIntoView();
}

/**
 * Checks the requested mark and refreshes the component
 * @param {number} component_id
 * @param {number} mark_id
 * @return {Promise}
 */
function checkMark(component_id: number, mark_id: number): Promise<void> {
    // Don't let them check a disabled mark
    if (rubricDomAccess.isMarkDisabled(mark_id)) {
        return Promise.resolve();
    }

    // First fetch the necessary information from the DOM
    const gradedComponent = rubricDomAccess.getGradedComponentFromDOM(component_id);

    // Uncheck the first mark if it's checked
    const firstMarkId = rubricDomAccess.getComponentFirstMarkId(component_id);
    if (rubricDomAccess.isMarkChecked(firstMarkId)) {
        // If first mark is checked, it will be the first element in the array
        gradedComponent.mark_ids.splice(0, 1);
    }

    // Then add the mark id to the array
    gradedComponent.mark_ids.push(mark_id);

    // Finally, re-render the component
    return injectGradingComponent(rubricDomAccess.getComponentFromDOM(component_id), gradedComponent, false, true);
}

/**
 * Un-checks the requested mark and refreshes the component
 * @param {number} component_id
 * @param {number} mark_id
 * @return {Promise}
 */
function unCheckMark(component_id: number, mark_id: number): Promise<void> {
    // First fetch the necessary information from the DOM
    const gradedComponent = rubricDomAccess.getGradedComponentFromDOM(component_id);

    // Then remove the mark id from the array
    for (let i = 0; i < gradedComponent.mark_ids.length; ++i) {
        if (gradedComponent.mark_ids[i] === mark_id) {
            gradedComponent.mark_ids.splice(i, 1);
            break;
        }
    }

    // Finally, re-render the component
    return injectGradingComponent(rubricDomAccess.getComponentFromDOM(component_id), gradedComponent, false, true);
}

/**
 * Un-checks the full credit / no credit mark of a component
 * @param {number} component_id
 * @return {Promise}
 */
function unCheckFirstMark(component_id: number): Promise<void> {
    return unCheckMark(component_id, rubricDomAccess.getComponentFirstMarkId(component_id));
}

/**
 * Saves the mark list to the server for a component and handles any conflicts.
 * Properties that are saved are: mark point values, mark titles, and mark order
 * @param {number} component_id
 * @return {Promise}
 */
function saveMarkList(component_id: number): Promise<void> {
    const gradeable_id = rubricDomAccess.getGradeableId();
    return rubricAjax.ajaxGetComponentRubric(gradeable_id, component_id)
        .then((component) => {
            const domMarkList = rubricDomAccess.getMarkListFromDOM(component_id);
            const serverMarkList = component.marks;
            const oldServerMarkList = OLD_MARK_LIST[component_id];

            // associative array of associative arrays of marks with conflicts {<mark_id>: {domMark, serverMark, oldServerMark}, ...}
            const conflictMarks: ConflictMarks = {};

            let sequence = Promise.resolve();

            // For each DOM mark, try to save it
            domMarkList.forEach((domMark) => {
                const serverMark = getMarkFromMarkArray(serverMarkList, domMark.id);
                const oldServerMark = getMarkFromMarkArray(oldServerMarkList, domMark.id);
                sequence = sequence
                    .then(() => {
                        return tryResolveMarkSave(gradeable_id, component_id, domMark, serverMark, oldServerMark);
                    })
                    .then((success) => {
                        // success of false counts as conflict
                        if (success === false) {
                            conflictMarks[domMark.id] = {
                                domMark: domMark,
                                serverMark: serverMark,
                                oldServerMark: oldServerMark,
                                localDeleted: rubricDomAccess.isMarkDeleted(domMark.id),
                            };
                        }
                    });
            });

            return sequence
                .then(() => {
                    // No conflicts, so don't open the popup
                    if (Object.keys(conflictMarks).length === 0) {
                        return;
                    }

                    // Prompt the user with any conflicts
                    return rubricConflict.openMarkConflictPopup(component_id, conflictMarks);
                })
                .then(() => {
                    const markOrder: { [key: number]: number | undefined } = {};
                    domMarkList.forEach((mark) => {
                        markOrder[mark.id] = mark.order;
                    });
                    // Finally, save the order
                    return rubricAjax.ajaxSaveMarkOrder(gradeable_id, component_id, markOrder);
                });
        });
}

/**
 * Determines what to do when trying to save a mark provided the mark
 *  before edits, the DOM mark, and the server's up-to-date mark
 *  @return {Promise<boolean>} Resolves true on success, false on conflict
 */
function tryResolveMarkSave(gradeable_id: string, component_id: number, domMark: { id: number; title: string; points: number; publish: boolean; }, serverMark: Mark | null, oldServerMark: Mark | null): Promise<number | boolean> {
    const markDeleted = rubricDomAccess.isMarkDeleted(domMark.id);
    if (oldServerMark !== null) {
        if (serverMark !== null) {
            // Mark edited under normal conditions
            if ((marksEqual(domMark, serverMark) || marksEqual(domMark, oldServerMark)) && !markDeleted) {
                // If the domMark is not unique, then we don't need to do anything
                return Promise.resolve(true);
            }
            else if (!marksEqual(serverMark, oldServerMark)) {
                // The domMark is unique, and the serverMark is also unique,
                // which means all 3 versions are different, which is a conflict state
                return Promise.resolve(false);
            }
            else if (markDeleted) {
                // domMark was deleted and serverMark hasn't changed from oldServerMark,
                //  so try to delete the mark
                return rubricAjax.ajaxDeleteMark(gradeable_id, component_id, domMark.id)
                    .catch((err) => {
                        err.message = `Could not delete mark: ${err.message}`;
                        throw err;
                    })
                    .then(() => {
                        // Success, then resolve success
                        return Promise.resolve(true);
                    });
            }
            else {
                // The domMark is unique and the serverMark is the same as the oldServerMark
                //  so we should save the domMark to the server
                return rubricAjax.ajaxSaveMark(gradeable_id, component_id, domMark.id, domMark.title, domMark.points, domMark.publish)
                    .then(() => {
                        // Success, then resolve success
                        return Promise.resolve(true);
                    });
            }
        }
        else {
            // This means it was deleted from the server.
            if (!marksEqual(domMark, oldServerMark) && !markDeleted) {
                // And the mark changed and wasn't deleted, which is a conflict state
                return Promise.resolve(false);
            }
            else {
                // And the mark didn't change or it was deleted, so don't do anything
                return Promise.resolve(domMark.id);
            }
        }
    }
    else {
        // This means it didn't exist when we started editing, so serverMark must also be null
        if (markDeleted) {
            // The mark was marked for deletion, but never existed... so do nothing
            return Promise.resolve(true);
        }
        else {
            // The mark never existed and isn't deleted, so its new
            return rubricAjax.ajaxAddNewMark(gradeable_id, component_id, domMark.title, domMark.points, domMark.publish)
                .then((data) => {
                    // Success, then resolve true
                    domMark.id = data.mark_id;
                    return Promise.resolve(true);
                })
                .catch((err) => {
                    // This means the user's mark was invalid
                    err.message = `Failed to add mark: ${err.message}`;
                    throw err;
                });
        }
    }
}

/**
 * Checks if two graded components are equal
 * @param {Object} gcDOM Must not be undefined
 * @param {Object} gcOLD May be undefined
 * @returns {boolean}
 */
function gradedComponentsEqual(gcDOM: GradedComponent, gcOLD: GradedComponent | undefined): boolean {
    // If the OLD component is undefined, they are only equal if no marks have been assigned
    if (gcOLD === undefined) {
        return gcDOM.mark_ids.length === 0 && (!gcDOM.custom_mark_selected || (gcDOM.score === 0.0 && gcDOM.comment === ''));
    }

    // If its not the same version, then we want to save
    if (gcDOM.graded_version !== gcOLD.graded_version) {
        return false;
    }

    // Simple check, if the mark list isn't the same length
    if (gcDOM.mark_ids.length !== gcOLD.mark_ids.length) {
        return false;
    }

    // Check that they have the same marks assigned
    for (let i = 0; i < gcDOM.mark_ids.length; i++) {
        let found = false;
        for (let j = 0; j < gcOLD.mark_ids.length; j++) {
            if (gcOLD.mark_ids[j] === gcDOM.mark_ids[i]) {
                found = true;
            }
        }
        if (!found) {
            return false;
        }
    }

    // Since the custom mark can be unchecked with text / point value, treat unchecked as blank score / point values
    if (gcDOM.custom_mark_selected) {
        return gcDOM.score === gcOLD.score && gcDOM.comment === gcOLD.comment;
    }
    else {
        return gcOLD.score === 0.0 && gcOLD.comment === '';
    }
}

export function saveComponent(component_id: number): Promise<void> {
    // We are saving changes...
    if (rubricDomAccess.isEditModeEnabled()) {
        // We're in edit mode, so save the component and fetch the up-to-date grade / rubric data
        return saveMarkList(component_id);
    }
    else {
        // The grader unchecked the custom mark, but didn't delete the text.  This shouldn't happen too often,
        //  so prompt the grader if this is what they really want since it will delete the text / score.
        const gradedComponent = rubricDomAccess.getGradedComponentFromDOM(component_id);
        //only show error if custom marks are allowed
        if (gradedComponent.custom_mark_enabled && gradedComponent.comment !== '' && !gradedComponent.custom_mark_selected && rubricDomAccess.getAllowCustomMarks()) {

            if (!confirm('Are you sure you want to delete the custom mark?')) {
                return Promise.reject();
            }
        }
        // We're in grade mode, so save the graded component
        return saveGradedComponent(component_id);
    }
}

/**
 * Saves the component grade information to the server
 * Note: if the mark was deleted remotely, but the submitter was assigned it locally, the mark
 *  will be resurrected with a new id
 * @param {number} component_id
 * @return {Promise}
 */
function saveGradedComponent(component_id: number): Promise<void> {
    const gradeable_id = rubricDomAccess.getGradeableId();
    const gradedComponent = rubricDomAccess.getGradedComponentFromDOM(component_id);
    gradedComponent.graded_version = rubricDomAccess.getDisplayVersion();

    // The grader didn't change the grade at all, so don't save (don't put our name on a grade we didn't contribute to)
    if (gradedComponentsEqual(gradedComponent, OLD_GRADED_COMPONENT_LIST[component_id])) {
        return Promise.resolve();
    }
    return rubricAjax.ajaxGetComponentRubric(rubricDomAccess.getGradeableId(), component_id)
        .then((component) => {
            const missingMarks: Mark[] = [];
            const domComponent = rubricDomAccess.getComponentFromDOM(component_id);

            // Check each mark the submitter was assigned
            gradedComponent.mark_ids.forEach((mark_id) => {
                // Mark exists remotely, so no action required
                if (getMarkFromMarkArray(component.marks, mark_id) !== null) {
                    return;
                }
                missingMarks.push(getMarkFromMarkArray(domComponent.marks, mark_id)!);
            });

            // For each mark missing from the server, add it
            let sequence = Promise.resolve();
            missingMarks.forEach((mark) => {
                sequence = sequence
                    .then(() => {
                        return rubricAjax.ajaxAddNewMark(gradeable_id, component_id, mark.title, mark.points, mark.publish);
                    })
                    .then((data) => {
                        // Make sure to add it to the grade.  We don't bother removing the deleted mark ids
                        //  however, because the server filters out non-existent mark ids
                        gradedComponent.mark_ids.push(data.mark_id);
                    });
            });
            return sequence;
        })
        .then(() => {
            return rubricAjax.ajaxSaveGradedComponent(
                rubricDomAccess.getGradeableId(), component_id, rubricDomAccess.getAnonId(),
                gradedComponent.graded_version,
                gradedComponent.custom_mark_selected ? gradedComponent.score : 0.0,
                gradedComponent.custom_mark_selected ? gradedComponent.comment : '',
                rubricDomAccess.isSilentEditModeEnabled(),
                gradedComponent.mark_ids);
        });
}

/**
 * Re-renders the graded component header with the data in the DOM
 *  and preserves the edit/grade mode display
 * @param {number} component_id
 * @param {boolean} showMarkList Whether the header should be styled like the component is open
 * @return {Promise}
 */
function refreshGradedComponentHeader(component_id: number, showMarkList: boolean): Promise<void> {
    return injectGradingComponentHeader(
        rubricDomAccess.getComponentFromDOM(component_id),
        rubricDomAccess.getGradedComponentFromDOM(component_id), showMarkList);
}


/**
 * Re-renders the graded component with the data in the DOM
 *  and preserves the edit/grade mode display
 * @param {number} component_id
 * @param {boolean} showMarkList Whether the mark list should be visible
 * @return {Promise}
 */
function refreshGradedComponent(component_id: number, showMarkList: boolean): Promise<void> {
    return injectGradingComponent(
        rubricDomAccess.getComponentFromDOM(component_id),
        rubricDomAccess.getGradedComponentFromDOM(component_id),
        rubricDomAccess.isEditModeEnabled(), showMarkList);
}

/**
 * Re-renders the component header with the data in the DOM
 * @param {number} component_id
 * @param {boolean} showMarkList Whether the header should be styled like the component is open
 * @return {Promise}
 */
export function refreshInstructorEditComponentHeader(component_id: number, showMarkList: boolean): Promise<void> {
    return injectInstructorEditComponentHeader(rubricDomAccess.getComponentFromDOM(component_id), showMarkList);
}

/**
 * Re-renders the component's header block with the data in the DOM
 * @param {number} component_id
 * @param {boolean} showMarkList Whether the header should be styled like the component is open
 * @return {Promise}
 */
export function refreshComponentHeader(component_id: number, showMarkList: boolean): Promise<void> {
    return rubricDomAccess.isInstructorEditEnabled() ? refreshInstructorEditComponentHeader(component_id, showMarkList) : refreshGradedComponentHeader(component_id, showMarkList);
}

/**
 * Refreshes the 'total scores' box at the bottom of the gradeable
 * @return {Promise}
 */
function refreshTotalScoreBox(): Promise<void> {
    return injectTotalScoreBox(rubricDomAccess.getScoresFromDOM());
}

/**
 * Refreshes the 'rubric total' box at the top of the rubric editor
 * @returns {Promise}
 */
function refreshRubricTotalBox(): Promise<void> {
    return injectRubricTotalBox(rubricDomAccess.getRubricTotalFromDOM());
}

/**
 * Renders the provided component object for instructor edit mode
 * @param {Object} component
 * @param {boolean} showMarkList Whether the mark list should be visible
 * @param {boolean} loadItempoolOptions whether to load the itempool options or not
 * @return {Promise}
 */
function injectInstructorEditComponent(component: Component, showMarkList: boolean, loadItempoolOptions = false): Promise<void> {
    return renderEditComponent(component, rubricDomAccess.getPointPrecision(), showMarkList)
        .then((elements) => {
            rubricDomAccess.setComponentContents(component.id, elements);
        })
        .then(() => {
            return refreshRubricTotalBox();
        }).then(() => {
            if (rubricDomAccess.isItempoolAvailable() && loadItempoolOptions) {
                addItempoolOptions(component.id);
            }
        });
}

/**
 * Renders the provided component object for instructor edit mode header
 * @param {boolean} showMarkList Whether to style the header like the mark list is open
 * @return {Promise}
 */
function injectInstructorEditComponentHeader(component: Component, showMarkList: boolean): Promise<void> {
    return renderEditComponentHeader(component, showMarkList)
        .then((elements) => {
            rubricDomAccess.setComponentHeaderContents(component.id, elements);
        })
        .then(() => {
            return refreshRubricTotalBox();
        });
}

/**
 * Renders the provided component/graded_component object for grading/editing
 * @param {boolean} editable Whether the component should appear in edit or grade mode
 * @param {boolean} showMarkList Whether to show the mark list or not
 * @return {Promise}
 */
function injectGradingComponent(component: Component, graded_component: GradedComponent, editable: boolean, showMarkList: boolean): Promise<void> {
    const is_student_grader = !!($('#student-grader').attr('is-student-grader'));
    return renderGradingComponent(rubricDomAccess.getGraderId(), component, graded_component, rubricDomAccess.isGradingDisabled(), rubricDomAccess.canVerifyGraders(), rubricDomAccess.getPointPrecision(), editable, showMarkList, rubricDomAccess.getComponentVersionConflict(graded_component), is_student_grader, getTAGradedPeer(), rubricDomAccess.getAllowCustomMarks())
        .then((elements) => {
            rubricDomAccess.setComponentContents(component.id, elements);
        });
}

/**
 * Renders the provided component/graded_component header
 * @param showMarkList Whether to style the header like the mark list is open
 */
function injectGradingComponentHeader(component: Component, graded_component: GradedComponent, showMarkList: boolean): Promise<void> {
    return renderGradingComponentHeader(rubricDomAccess.getGraderId(), component, graded_component, rubricDomAccess.isGradingDisabled(), rubricDomAccess.canVerifyGraders(), showMarkList, rubricDomAccess.getComponentVersionConflict(graded_component))
        .then((elements) => {
            rubricDomAccess.setComponentHeaderContents(component.id, elements);
        })
        .then(() => {
            return refreshTotalScoreBox();
        });
}

/**
 * Renders the total scores box
 */
function injectTotalScoreBox(scores: Score): Promise<void> {
    return renderTotalScoreBox(scores)
        .then((elements) => {
            rubricDomAccess.setTotalScoreBoxContents(elements);
        });
}

/**
 * Renders the rubric total box (instructor edit mode)
 * @returns {Promise<string>}
 */
function injectRubricTotalBox(scores: RubricTotal): Promise<void> {
    return renderRubricTotalBox(scores)
        .then((elements) => {
            rubricDomAccess.setRubricTotalBoxContents(elements);
        });
}

function addItempoolOptions(componentId: number): void {
    // create option elements for the itempool options
    const itempools = rubricDomAccess.getItempoolOptions();
    const select_ele = $(`#component-itempool-select-${componentId}`);
    const selected_value = select_ele.attr('data-selected') ? select_ele.attr('data-selected') as string : 'null';
    // const itempool_options = ['<option value="null">NONE</option>'];
    let itempool_options = '<option value="null">NONE</option>';

    for (const key in itempools) {
        itempool_options += `<option value='${key}'>${key} (${itempools[key]})</option>`;
    }
    select_ele.html(itempool_options);
    select_ele.val(selected_value).change();
}

window.showVerifyComponent = showVerifyComponent;
window.setPdfPageAssignment = setPdfPageAssignment;
window.reloadInstructorEditRubric = reloadInstructorEditRubric;
window.closeAllComponents = closeAllComponents;
//TODO: Should be removed after migrating ta-grading.js
const orig_toggleComponent = window.toggleComponent;
if (typeof orig_toggleComponent === 'function') {
    window.toggleComponent = function(component_id: number, saveChanges: boolean, edit_mode: boolean): Promise<void> {
        const ret = toggleComponent(component_id, saveChanges, edit_mode);
        return ret.then(() => {
            orig_toggleComponent(component_id, saveChanges, edit_mode);
        });
    };
}
else {
    window.toggleComponent = toggleComponent;
}
window.toggleCommonMark = toggleCommonMark;
window.scrollToComponent = scrollToComponent;
window.closeComponent = closeComponent;
window.scrollToOverallComment = scrollToOverallComment;

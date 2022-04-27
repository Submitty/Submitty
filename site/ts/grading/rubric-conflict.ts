/**
 * Note: The in-use callbacks use buttons to select a row, but this code supports
 *  individually selecting the point value / title of the resolutions
 */

import { ajaxDeleteMark, ajaxAddNewMark, ajaxSaveMark } from './rubric-ajax';
import { getComponentJQuery, getGradeableId, isMarkDeleted } from './rubric-dom-access';
import { ConflictMarks } from './types/ConflictMark';

/**
 * Gets the JQuery selector for the conflict mark
 */
function getConflictMarkJQuery(mark_id: number): JQuery {
    return $(`#mark-conflict-${mark_id}`);
}

/**
 * Gets the mark id from its dom element or a child dom element
 */
function getConflictMarkIdFromDOMElement(me: HTMLElement): number {
    return $(me).hasClass('mark-conflict-row')
        ? parseInt($(me).attr('data-mark_id')!)
        : parseInt($(me).parents('.mark-conflict-row').attr('data-mark_id')!);
}

/**
 * Gets if the mark is deleted from the server
 */
function isMarkServerDeleted(mark_id: number): boolean {
    return getConflictMarkJQuery(mark_id).find('.mark-resolve-server').find('.mark-deleted-message').length > 0;
}

/**
 * Gets if any marks are unresolved
 */
function anyUnresolvedConflicts(): boolean {
    return $('#mark-conflict-popup').find('.mark-conflict-row:not(.mark-resolved)').length > 0;
}

/**
 * Gets the number of mark conflicts that have been resolved
 */
function getNumResolvedConflicts(): number {
    return $('#mark-conflict-popup').find('.mark-conflict-row.mark-resolved').length;
}

/**
 * Tags a mark as resolved
 * @param {int} mark_id
 */
function tagMarkConflictResolved(mark_id: number): void {
    getConflictMarkJQuery(mark_id).addClass('mark-resolved');
}

/**
 * Shows the next unresolved mark conflict
 */
function showNextConflict(): void {
    $('.mark-conflict-row').hide();
    $('.mark-conflict-row:not(.mark-resolved)').first().show();
    $('.conflict-resolve-progress-indicator').text(getNumResolvedConflicts() + 1);
}

/**
 * Prepares the conflict marks to be rendered
 * @param {Object} conflictMarks
 */
function prepConflictMarks(conflictMarks: ConflictMarks): void {
    for (const id in conflictMarks) {
        if (Object.prototype.hasOwnProperty.call(conflictMarks, id)) {
            conflictMarks[id].localDeleted = isMarkDeleted(parseInt(id));
        }
    }
}

/**
 * Prompts the user with an array of conflict marks so they can individually resolve them
 */
export function openMarkConflictPopup(component_id: number, conflictMarks: ConflictMarks): Promise<void> {
    const gradeable_id = getGradeableId();
    const popup = $('#mark-conflict-popup');

    // Set the component title
    popup.find('.component-title').html(getComponentJQuery(component_id).attr('data-title')!);

    // Prep the conflict marks
    prepConflictMarks(conflictMarks);

    // Generate the list of marks
    return renderConflictMarks(conflictMarks)
        .then((elements) => {
            // Insert the rendered elements
            popup.find('.conflict-marks-container').html(elements);

            // Open the popup
            popup.show();

            // Setup the promise so that resolving the last mark will resolve the promise
            return new Promise<void>((resolve) => {
                // Function to tag a mark as resolved and move to the next mark
                const resolveMark = (mark_id: number): void => {
                    tagMarkConflictResolved(mark_id);
                    if (!anyUnresolvedConflicts()) {
                        popup.hide();
                        resolve();
                    }
                    else {
                        showNextConflict();
                    }
                };

                // In order for the event handlers to have the power to resolve the promise,
                //  they need to be established within the promise
                popup.find('.mark-resolve-dom .btn').on('click', function() {
                    const id = getConflictMarkIdFromDOMElement(this);
                    const mark = conflictMarks[id].domMark;

                    Promise.resolve()
                        .then(() => {
                            if (conflictMarks[id].localDeleted) {
                                return ajaxDeleteMark(gradeable_id, component_id ,id)
                                    .catch((err) => {
                                        // Don't let this error hold up the whole operation
                                        alert(`Could not delete mark: ${err.message}`);
                                    });
                            }
                            else {
                                // If the mark was deleted from the server, but we want to keep our changes,
                                //  we need to re-add the mark
                                if (isMarkServerDeleted(id)) {
                                    return ajaxAddNewMark(gradeable_id, component_id, mark.title, mark.points, mark.publish)
                                        .then((data) => {
                                            mark.id = data.mark_id;
                                        });
                                }
                                else {
                                    return ajaxSaveMark(gradeable_id, component_id, id, mark.title, mark.points, mark.publish);
                                }
                            }
                        })
                        .then(() => {
                            resolveMark(id);
                        })
                        .catch((err) => {
                            console.error(err);
                            alert(`Failed to resolve conflict! ${err.message}`);
                        });
                });
                popup.find('.mark-resolve-old-server .btn').on('click', function() {
                    const id = getConflictMarkIdFromDOMElement(this);
                    const mark = conflictMarks[id].oldServerMark!;
                    ajaxSaveMark(gradeable_id, component_id, id, mark.title, mark.points, mark.publish)
                        .then(() => {
                            resolveMark(id);
                        })
                        .catch((err) => {
                            console.error(err);
                            alert(`Failed to resolve conflict! ${err.message}`);
                        });
                });
                popup.find('.mark-resolve-server .btn').on('click', function() {
                    // If we choose the server mark, we don't do anything
                    resolveMark(getConflictMarkIdFromDOMElement(this));
                });
            });
        });
}

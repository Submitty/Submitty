import { ajaxAddNewMark, ajaxDeleteMark, ajaxSaveMark, getComponentJQuery, getGradeableId, isMarkDeleted, MarkConflicts } from './ta-grading-rubric';

/**
 * Note: The in-use callbacks use buttons to select a row, but this code supports
 *  individually selecting the point value / title of the resolutions
 */

/**
 * Gets the JQuery selector for the conflict mark
 * @param {int} mark_id
 * @returns {jQuery}
 */
function getConflictMarkJQuery(mark_id: number) {
    return $(`#mark-conflict-${mark_id}`);
}

/**
 * Gets the mark id from its dom element or a child dom element
 * @param me
 * @return {int}
 */
function getConflictMarkIdFromDOMElement(me: HTMLElement) {
    return $(me).hasClass('mark-conflict-row')
        ? parseInt($(me).attr('data-mark_id')!)
        : parseInt($(me).parents('.mark-conflict-row').attr('data-mark_id')!);
}

/**
 * Gets if the mark is deleted from the server
 * @param {int} mark_id
 * @return {boolean}
 */
function isMarkServerDeleted(mark_id: number) {
    return getConflictMarkJQuery(mark_id).find('.mark-resolve-server').find('.mark-deleted-message').length > 0;
}

/**
 * Gets if any marks are unresolved
 * @returns {boolean}
 */
function anyUnresolvedConflicts() {
    return $('#mark-conflict-popup').find('.mark-conflict-row:not(.mark-resolved)').length > 0;
}

/**
 * Gets the number of mark conflicts that have been resolved
 * @returns {int}
 */
function getNumResolvedConflicts() {
    return $('#mark-conflict-popup').find('.mark-conflict-row.mark-resolved').length;
}

/**
 * Tags a mark as resolved
 * @param {int} mark_id
 */
function tagMarkConflictResolved(mark_id: number) {
    getConflictMarkJQuery(mark_id).addClass('mark-resolved');
}

/**
 * Shows the next unresolved mark conflict
 */
function showNextConflict() {
    $('.mark-conflict-row').hide();
    $('.mark-conflict-row:not(.mark-resolved)').first().show();
    $('.conflict-resolve-progress-indicator').text(getNumResolvedConflicts() + 1);
}

/**
 * Prepares the conflict marks to be rendered
 * @param {Object} conflictMarks
 */
function prepConflictMarks(conflictMarks: MarkConflicts) {
    for (const id in conflictMarks) {
        if (Object.prototype.hasOwnProperty.call(conflictMarks, id)) {
            conflictMarks[id].localDeleted = isMarkDeleted(parseInt(id));
        }
    }
}

/**
 * Prompts the user with an array of conflict marks so they can individually resolve them
 * @param {int} component_id
 * @param {{domMark, serverMark, oldServerMark, localDeleted}[]} conflictMarks
 * @return {Promise<Object>} Promise resolves with an array of marks to save indexed by mark id.
 *                              The mark will be null if it should be deleted
 */
export async function openMarkConflictPopup(component_id: number, conflictMarks: MarkConflicts): Promise<void> {
    const gradeable_id = getGradeableId();
    const popup = $('#mark-conflict-popup');

    // Set the component title
    popup.find('.component-title').html(getComponentJQuery(component_id).attr('data-title')!);

    // Prep the conflict marks
    prepConflictMarks(conflictMarks);

    // Generate the list of marks
    // Insert the rendered elements
    popup.find('.conflict-marks-container').html(await renderConflictMarks(conflictMarks));

    // Open the popup
    popup.show();

    // Setup the promise so that resolving the last mark will resolve the promise
    // Function to tag a mark as resolved and move to the next mark
    const resolveMark = function (mark_id: number) {
        tagMarkConflictResolved(mark_id);
        if (!anyUnresolvedConflicts()) {
            popup.hide();
            return;
        }
        else {
            showNextConflict();
        }
    };

    // In order for the event handlers to have the power to resolve the promise,
    //  they need to be established within the promise
    popup.find('.mark-resolve-dom .btn').click(async function () {
        const id = getConflictMarkIdFromDOMElement(this);
        const mark = conflictMarks[id].domMark;

        if (conflictMarks[id].localDeleted) {
            try {
                await ajaxDeleteMark(gradeable_id, component_id, id);
            }
            catch (err) {
                // Don't let this error hold up the whole operation
                alert(`Could not delete mark: ${(err as Error).message}`);
            }
        }
        else {
            // If the mark was deleted from the server, but we want to keep our changes,
            //  we need to re-add the mark
            if (isMarkServerDeleted(id)) {
                const data = await ajaxAddNewMark(gradeable_id, component_id, mark.title!, mark.points, mark.publish);
                mark.id = data.mark_id;
            }
            else {
                return await ajaxSaveMark(gradeable_id, component_id, id, mark.title!, mark.points, mark.publish);
            }
        }
        try {
            resolveMark(id);
        }
        catch (err) {
            console.error(err);
            alert(`Failed to resolve conflict! ${(err as Error).message}`);
        }
    });
    popup.find('.mark-resolve-old-server .btn').click(async function () {
        const id = getConflictMarkIdFromDOMElement(this);
        const mark = conflictMarks[id].oldServerMark;
        if (!mark) {
            alert('No old server mark to resolve to!');
            return;
        }
        try {
            await ajaxSaveMark(gradeable_id, component_id, id, mark.title!, mark.points, mark.publish);
            resolveMark(id);
        }
        catch (err) {
            console.error(err);
            alert(`Failed to resolve conflict! ${(err as Error).message}`);
        }
    });
    popup.find('.mark-resolve-server .btn').click(function () {
        // If we choose the server mark, we don't do anything
        resolveMark(getConflictMarkIdFromDOMElement(this));
    });
}

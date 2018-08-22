/**
 * Note: The in-use callbacks use buttons to select a row, but this code supports
 *  individually selecting the point value / title of the resolutions
 */

/**
 * Gets the minimal mark information for a resolved mark (id, title, points)
 * @return {Object[]} points, id and title
 */
function getResolvedMarksFromDOM() {
    let markList = [];
    $('#mark-conflict-popup .mark-conflict-row').each(function () {
        let id = parseInt($(this).attr('data-mark_id'));

        let chosenPointsElement = $(this).find('.points-selected');
        if (chosenPointsElement.length === 0) {
            throw new Error('Resolution must be selected for ' + id);
        }

        let chosenTitleElement = $(this).find('.title-selected');
        if (chosenTitleElement.length === 0) {
            throw new Error('Resolution must be selected for ' + id);
        }

        // Is the mark tagged to be deleted?
        if (isMarkResolutionDelete(this)) {
            // Mark is tagged to be deleted, but was it deleted from the server?
            if (isMarkServerDeleted(this)) {
                // Deleted from the server already, so we need not do anything
                markList.push({
                    id: id,
                    resolution: 'nothing'
                });
            } else {
                // Not deleted from the server, so we should delete it
                markList.push({
                    id: id,
                    resolution: 'delete'
                });
            }
        } else {
            // Normal operation, so save
            markList.push({
                id: id,
                points: parseFloat(chosenPointsElement.attr('data-points')),
                title: chosenTitleElement.attr('data-title'),
                resolution: isMarkServerDeleted(this) ? 'add' : 'save'
            });
        }
    });
    return markList;
}

/**
 * Gets if the mark is tagged for deletion
 * @param me DOM element of the mark-conflict-row
 * @returns {boolean}
 */
function isMarkResolutionDelete(me) {
    return $(me).find('.title-selected').hasClass('mark-deleted-message');
}

/**
 * Gets if the mark is deleted from the server
 * @param me DOM element of the mark-conflict-row
 * @return {boolean}
 */
function isMarkServerDeleted(me) {
    return $(me).find('.mark-resolve-server').find('.title-selected').length > 0
}

/**
 * Gets the JQuery selector for the mark 'me' belongs to
 * @param me
 * @return {jQuery}
 */
function getConflictMarkJQuery(me) {
    return $(me).parents('.mark-conflict-row');
}

/**
 * Gets the JQuery selector for the mark resolution 'me' belongs to
 * @param me
 * @returns {jQuery}
 */
function getConflictMarkResolutionJQuery(me) {
    return $(me).parents('.mark-resolve');
}

/**
 * DOM Callback for the resolve buttons
 * @param me
 */
function onResolutionClick(me) {
    onConflictPointsClick(me);
    onConflictTitleClick(me);
}

/**
 * Un-selects the 'delete' row for the resolution
 * @param me
 */
function unSelectDeleteResolve(me) {
    let deleteItem = getConflictMarkJQuery(me).find('mark-deleted-message');
    deleteItem.removeClass('points-selected');
    deleteItem.removeClass('title-selected');
}

/**
 * DOM Callback for clicking one of the 'points' values
 * @param me
 */
function onConflictPointsClick(me) {
    unSelectDeleteResolve(me);
    getConflictMarkJQuery(me).find('.points').removeClass('points-selected');
    getConflictMarkResolutionJQuery(me).find('.points').addClass('points-selected');
}

/**
 * DOM Callback for clicking one of the 'title' values
 * @param me
 */
function onConflictTitleClick(me) {
    unSelectDeleteResolve(me);
    getConflictMarkJQuery(me).find('.title').removeClass('title-selected');
    getConflictMarkResolutionJQuery(me).find('.title').addClass('title-selected');
}

/**
 * DOM Callback for clicking on the mark deleted row
 * @param me
 */
function onConflictDeleteClick(me) {
    let container = getConflictMarkJQuery(me);
    container.find('.title').removeClass('title-selected');
    container.find('.points').removeClass('points-selected');
    $(me).addClass('title-selected');
    $(me).addClass('points-selected');
}

/**
 * Prepares the conflict marks to be rendered
 * @param {Object[]} conflictMarks
 */
function prepConflictMarks(conflictMarks) {
    conflictMarks.forEach(function(mark) {
        mark.local_deleted = isMarkDeleted(mark.domMark.id);
    });
}

/**
 * Prompts the user with an array of conflict marks so they can individually resolve them
 * @param {int} component_id
 * @param {{domMark, serverMark, oldServerMark, localDeleted}[]} conflictMarks
 * @return {Promise<Object>} Promise resolves with an array of marks to save indexed by mark id.
 *                              The mark will be null if it should be deleted
 */
function openMarkConflictPopup(component_id, conflictMarks) {
    let popup = $('#mark-conflict-popup');

    // Set the component title
    popup.find('.component-title').html(getComponentJQuery(component_id).attr('data-title'));

    // Prep the conflict marks
    prepConflictMarks(conflictMarks);

    // Generate the list of marks
    return renderConflictMarks(conflictMarks)
        .then(function (elements) {
            // Insert the rendered elements
            popup.find('.conflict-marks-container').html(elements);

            // Open the popup
            popup.show();

            // Setup the 'submit' button to resolve the promise
            //  and the 'cancel' button to reject the promise
            return new Promise(function (resolve, reject) {
                popup.find('.resolve-button').click(function () {
                    // Use the dom marks as the starting point
                    let resolvedMarks = {};
                    conflictMarks.forEach(function (conflictMark) {
                        resolvedMarks[conflictMark.domMark.id] = conflictMark.domMark;
                    });

                    try {
                        // For each mark in the resolution, update points and title
                        getResolvedMarksFromDOM().forEach(function (mark) {
                            resolvedMarks[mark.id].resolution = mark.resolution;
                            resolvedMarks[mark.id].title = mark.title;
                            resolvedMarks[mark.id].points = mark.points;
                        });
                    } catch (err) {
                        console.error(err);
                        alert('Failed to get resolutions! ' + err.message);

                        // We don't reject the promise, we just force the user to fix the problem
                        return;
                    }
                    popup.hide();

                    // Finally, resolve the promise
                    resolve(resolvedMarks);
                });
                popup.find('.close-button').click(function () {
                    popup.hide();

                    // If the user cancels, don't reject, just resolve a blank array of resolutions
                    resolve([]);
                });
            })
        });
}

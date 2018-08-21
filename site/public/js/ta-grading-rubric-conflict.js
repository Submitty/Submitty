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
            throw new Error('Point resolution must be selected for ' + id);
        }

        let chosenTitleElement = $(this).find('.title-selected');
        if (chosenTitleElement.length === 0) {
            throw new Error('Title resolution must be selected for ' + id);
        }

        // If the user chooses to delete the mark, set the mark null
        if ($(this).find('.title-selected').hasClass('mark-deleted-message')) {
            markList[id] = null;
            return;
        }

        // Otherwise, get the points / title value chosen
        markList.push({
            id: id,
            points: parseFloat(chosenPointsElement.attr('data-points')),
            title: chosenTitleElement.attr('data-title')
        });
    });
    return markList;
}

/**
 * Gets the DOM element for the mark 'me' belongs to
 * @param me
 * @return {jQuery}
 */
function getConflictMarkJQuery(me) {
    return $(me).parents('.mark-conflict-row');
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
    $(me).addClass('points-selected');
}

/**
 * DOM Callback for clicking one of the 'title' values
 * @param me
 */
function onConflictTitleClick(me) {
    unSelectDeleteResolve(me);
    getConflictMarkJQuery(me).find('.title').removeClass('title-selected');
    $(me).addClass('title-selected');
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
                    conflictMarks.forEach(function(conflictMark) {
                        resolvedMarks[conflictMark.domMark.id] = conflictMark.domMark;
                    });

                    try {
                        // For each mark in the resolution, update points and title
                        getResolvedMarksFromDOM().forEach(function(mark) {
                            resolvedMarks[mark.id].title = mark.title;
                            resolvedMarks[mark.id].points = mark.points;
                        });
                    } catch (err) {
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
/**
 * Global variables.  Add these very sparingly
 */

/**
 * An associative object of <component-id> : <mark[]>
 * Each 'mark' has at least properties 'id', 'points', 'title', which is sufficient
 *  to determine conflict resolutions.  These are updated when a component is opened.
 * @type {Object}
 */
OLD_MARK_LIST = {};

/**
 * Keep All of the ajax functions at the top of this file
 *
 */

/**
 * Called internally when an ajax function irrecoverably fails before rejecting
 * @param err
 */
function displayAjaxError(err) {
    console.error("Failed to parse response.  The server isn't playing nice...");
    console.error(err);
    // alert("There was an error communicating with the server. Please refresh the page and try again.");
}

/**
 * ajax call to fetch the gradeable's rubric
 * @param {string} gradeable_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxGetGradeableRubric(gradeable_id) {
    return new Promise(function (resolve, reject) {
        $.getJSON({
            type: "GET",
            url: buildUrl({
                'component': 'grading',
                'page': 'electronic',
                'action': 'get_gradeable_rubric',
                'gradeable_id': gradeable_id
            })
        }).then(function (response) {
            if (response.status !== 'success') {
                console.error('Something went wrong fetching the gradeable rubric: ' + response.message);
                reject(new Error(response.message));
            } else {
                resolve(response.data)
            }
        }).catch(function (err) {
            displayAjaxError(err);
            reject(err);
        });
    });
}

/**
 * ajax call to fetch the component's rubric
 * @param {string} gradeable_id
 * @param {int} component_id
 * @returns {Promise}
 */
function ajaxGetComponentRubric(gradeable_id, component_id) {
    return new Promise(function (resolve, reject) {
        $.getJSON({
            type: "GET",
            url: buildUrl({
                'component': 'grading',
                'page': 'electronic',
                'action': 'get_gradeable_rubric',
                'gradeable_id': gradeable_id,
                'component_id': component_id,
            })
        }).then(function (response) {
            if (response.status !== 'success') {
                console.error('Something went wrong fetching the component rubric: ' + response.message);
                reject(new Error(response.message));
            } else {
                resolve(response.data)
            }
        }).catch(function (err) {
            displayAjaxError(err);
            reject(err);
        });
    });
}

/**
 * ajax call to get the entire graded gradeable for a user
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxGetGradedGradeable(gradeable_id, anon_id) {
    return new Promise(function (resolve, reject) {
        $.getJSON({
            type: "GET",
            url: buildUrl({
                'component': 'grading',
                'page': 'electronic',
                'action': 'get_graded_gradeable',
                'gradeable_id': gradeable_id,
                'anon_id': anon_id
            }),
        }).then(function (response) {
            if (response.status !== 'success') {
                console.error('Something went wrong fetching the gradeable grade: ' + response.message);
                reject(new Error(response.message));
            } else {
                resolve(response.data)
            }
        }).catch(function (err) {
            displayAjaxError(err);
            reject(err);
        });
    });
}

/**
 * ajax call to fetch an updated Graded Component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} anon_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxGetGradedComponent(gradeable_id, component_id, anon_id) {
    return new Promise(function (resolve, reject) {
        $.getJSON({
            url: buildUrl({
                'component': 'grading',
                'page': 'electronic',
                'action': 'get_graded_component',
                'gradeable_id': gradeable_id,
                'anon_id': anon_id,
                'component_id': component_id
            }),
        }).then(function (response) {
            if (response.status !== 'success') {
                console.error('Something went wrong fetching the component grade: ' + response.message);
                reject(new Error(response.message));
            } else {
                resolve(response.data)
            }
        }).catch(function (err) {
            displayAjaxError(err);
            reject(err);
        });
    });
}

/**
 * ajax call to save the grading information for a component and submitter
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} anon_id
 * @param {int} active_version
 * @param {float} custom_points
 * @param {string} custom_message
 * @param {boolean} overwrite True to overwrite the component's grader
 * @param {int[]} mark_ids
 * @param {boolean} async
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxSaveGradedComponent(gradeable_id, component_id, anon_id, active_version, custom_points, custom_message, overwrite, mark_ids, async) {
    return new Promise(function (resolve, reject) {
        $.getJSON({
            type: "POST",
            url: buildUrl({
                'component': 'grading',
                'page': 'electronic',
                'action': 'save_graded_component'
            }),
            async: async,
            data: {
                'gradeable_id': gradeable_id,
                'component_id': component_id,
                'anon_id': anon_id,
                'active_version': active_version,
                'custom_points': custom_points,
                'custom_message': custom_message,
                'overwrite': overwrite,
                'mark_ids': mark_ids
            },
        }).then(function (response) {
            if (response.status !== 'success') {
                console.error('Something went wrong saving the component grade: ' + response.message);
                reject(new Error(response.message));
            } else {
                resolve(response.data)
            }
        }).catch(function (err) {
            displayAjaxError(err);
            reject(err);
        });
    });
}

/**
 * ajax call to fetch the overall comment for the gradeable
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxGetOverallComment(gradeable_id, anon_id) {
    return new Promise(function (resolve, reject) {
        $.getJSON({
            type: "POST",
            url: buildUrl({
                'component': 'grading',
                'page': 'electronic',
                'action': 'get_gradeable_comment'
            }),
            data: {
                'gradeable_id': gradeable_id,
                'anon_id': anon_id
            },
        }).then(function (response) {
            if (response.status !== 'success') {
                console.error('Something went wrong fetching the gradeable comment: ' + response.message);
                reject(new Error(response.message));
            } else {
                resolve(response.data)
            }
        }).catch(function (err) {
            displayAjaxError(err);
            reject(err);
        });
    });
}

/**
 * ajax call to save the general comment for the graded gradeable
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @param {string} gradeable_comment
 * @param {boolean} async
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxSaveOverallComment(gradeable_id, anon_id, gradeable_comment, async = true) {
    return new Promise(function (resolve, reject) {
        $.getJSON({
            type: "POST",
            url: buildUrl({
                'component': 'grading',
                'page': 'electronic',
                'action': 'save_general_comment'
            }),
            async: async,
            data: {
                'gradeable_id': gradeable_id,
                'anon_id': anon_id,
                'gradeable_comment': gradeable_comment
            },
        }).then(function (response) {
            if (response.status !== 'success') {
                console.error('Something went wrong saving the overall comment: ' + response.message);
                reject(new Error(response.message));
            } else {
                resolve(response.data)
            }
        }).catch(function (err) {
            displayAjaxError(err);
            reject(err);
        });
    });
}

/**
 * ajax call to add a new mark to the component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} title
 * @param {number} points
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxAddNewMark(gradeable_id, component_id, title, points) {
    return new Promise(function (resolve, reject) {
        $.getJSON({
            type: "POST",
            url: buildUrl({
                'component': 'grading',
                'page': 'electronic',
                'action': 'add_new_mark'
            }),
            data: {
                'gradeable_id': gradeable_id,
                'component_id': component_id,
                'title': title,
                'points': points
            },
        }).then(function (response) {
            if (response.status !== 'success') {
                console.error('Something went wrong adding a new mark: ' + response.message);
                reject(new Error(response.message));
            } else {
                resolve(response.data)
            }
        }).catch(function (err) {
            displayAjaxError(err);
            reject(err);
        });
    });
}

/**
 * ajax call to delete a mark
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {int} mark_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxDeleteMark(gradeable_id, component_id, mark_id) {
    return new Promise(function (resolve, reject) {
        $.getJSON({
            type: "POST",
            url: buildUrl({
                'component': 'grading',
                'page': 'electronic',
                'action': 'delete_one_mark'
            }),
            data: {
                'gradeable_id': gradeable_id,
                'component_id': component_id,
                'mark_id': mark_id
            },
        }).then(function (response) {
            if (response.status !== 'success') {
                console.error('Something went wrong deleting the mark: ' + response.message);
                reject(new Error(response.message));
            } else {
                resolve(response.data)
            }
        }).catch(function (err) {
            displayAjaxError(err);
            reject(err);
        });
    });
}

/**
 * ajax call to save mark point value / title
 * TODO: support setting 'isPublish' through here too
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {int} mark_id
 * @param {float} points
 * @param {string} title
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxSaveMark(gradeable_id, component_id, mark_id, points, title) {
    return new Promise(function (resolve, reject) {
        $.getJSON({
            type: "POST",
            url: buildUrl({
                'component': 'grading',
                'page': 'electronic',
                'action': 'save_mark'
            }),
            data: {
                'gradeable_id': gradeable_id,
                'component_id': component_id,
                'mark_id': mark_id,
                'points': points,
                'title': title,
            },
        }).then(function (response) {
            if (response.status !== 'success') {
                console.error('Something went wrong saving the mark: ' + response.message);
                reject(new Error(response.message));
            } else {
                resolve(response.data)
            }
        }).catch(function (err) {
            displayAjaxError(err);
            reject(err);
        });
    });
}

/**
 * ajax call to get the stats about a mark
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {int} mark_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxGetMarkStats(gradeable_id, component_id, mark_id) {
    return new Promise(function (resolve, reject) {
        $.getJSON({
            type: "POST",
            url: buildUrl({
                'component': 'grading',
                'page': 'electronic',
                'action': 'get_mark_stats'
            }),
            data: {
                'gradeable_id': gradeable_id,
                'component_id': component_id,
                'mark_id': mark_id
            },
        }).then(function (response) {
            if (response.status !== 'success') {
                console.error('Something went wrong getting mark stats: ' + response.message);
                reject(new Error(response.message));
            } else {
                resolve(response.data)
            }
        }).catch(function (err) {
            displayAjaxError(err);
            reject(err);
        });
    });
}

/**
 * ajax call to update the order of marks in a component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {*} order format: { <mark0-id> : <order0>, <mark1-id> : <order1>, ... }
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxSaveMarkOrder(gradeable_id, component_id, order) {
    return new Promise(function (resolve, reject) {
        $.getJSON({
            type: "POST",
            url: buildUrl({
                'component': 'grading',
                'page': 'electronic',
                'action': 'save_mark_order'
            }),
            data: {
                'gradeable_id': gradeable_id,
                'component_id': component_id,
                'order': JSON.stringify(order)
            },
        }).then(function (response) {
            if (response.status !== 'success') {
                console.error('Something went wrong saving the mark order: ' + response.message);
                reject(new Error(response.message));
            } else {
                resolve(response.data)
            }
        }).catch(function (err) {
            displayAjaxError(err);
            reject(err);
        });
    });
}

/**
 * ajax call to verify the grader of a component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} anon_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxVerifyComponent(gradeable_id, component_id, anon_id) {
    return new Promise(function (resolve, reject) {
        var action = (verifyAll) ? 'verify_all' : 'verify_grader';
        $.ajax({
            type: "POST",
            url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': action}),
            async: true,
            data: {
                'gradeable_id': gradeable_id,
                'component_id': component_id,
                'anon_id': anon_id,
            },
        }).then(function (response) {
            if (response.status !== 'success') {
                console.error('Something went wrong verifying the component: ' + response.message);
                reject(new Error(response.message));
            } else {
                resolve(response.data)
            }
        }).catch(function (err) {
            displayAjaxError(err);
            reject(err);
        });
    });
}

/**
 * ajax call to verify all graders of a component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} anon_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxAllVerifyComponents() {

}

/**
 * Put all DOM accessing methods here to abstract the DOM from the other function
 *  of the interface
 */

/**
 * Gets the id of the open gradeable
 * @return {string}
 */
function getGradeableId() {

}

/**
 * Gets the anon_id of the submitter being graded
 * @return {string}
 */
function getAnonId() {

}

/**
 * Used to determine if the interface displayed is for
 *  instructor edit mode (i.e. in the Edit Gradeable page)
 *  @return {boolean}
 */
function isInstructorEditEnabled() {

}

/**
 * Used to determine if the mark list should be displayed
 *  in 'edit' mode for grading
 *  @return {boolean}
 */
function isEditModeEnabled() {

}

/**
 * Gets if grader overwrite mode is enabled
 * @return {boolean}
 */
function isOverwriteGraderEnabled() {

}

/**
 * Sets the DOM elements to render for the entire rubric
 * @param elements
 */
function setRubricDOMElements(elements) {
    $("#grading-box").append(elements);
}

/**
 * Gets the DOM element for a component
 * @param component_id
 * @return {TODO}
 * @throws Error if the component id doesn't exist
 */
function getComponentDOMElement(component_id) {

}

/**
 * Extracts a component object from the DOM
 * @param {int} component_id
 * @return {Object}
 * @throws Error if the component id doesn't exist
 */
function getComponentFromDOM(component_id) {

}

/**
 * Extracts a graded component object from the DOM
 * @param {int} component_id
 * @return {Object}
 * @throws Error if the component id doesn't exist
 */
function getGradedComponentFromDOM(component_id) {

}

/**
 * Gets the overall comment message stored in the DOM
 * @return {string} This will always be blank in instructor edit mode
 */
function getOverallCommentFromDOM() {

}

/**
 * Gets the ids of all open components
 * @return {Array}
 */
function getOpenComponentIds() {

}

/**
 * Gets the id of the open component from the cookie
 * @return {int} Returns zero of no open component exists
 */
function getOpenComponentIdFromCookie() {

}

/**
 * Gets the id of the no credit / full credit mark of a component
 * @param {int} component_id
 * @return {int}
 * @throws Error if the component id doesn't exist
 */
function getComponentFirstMarkId(component_id) {

}

/**
 * Shows the mark list for a provided component
 *  Note: this is NOT the same as openComponent.
 * @param {int} component_id
 * @throws Error if the component id doesn't exist
 */
function showMarkList(component_id) {
    $(getComponentDOMElement(component_id)).find("TODO").show();
}

/**
 * Gets if a component is open
 * @param component_id
 * @return {boolean}
 * @throws Error if the component id doesn't exist
 */
function isComponentOpen(component_id) {

}

/**
 * Gets if the overall comment is open
 * @return {boolean}
 */
function isOverallCommentOpen() {

}

/**
 * Gets if a mark is 'checked'
 * @param {int} mark_id
 * @return {boolean}
 * @throws Error If the mark id doesn't exist
 */
function isMarkChecked(mark_id) {

}

/**
 * Gets if a mark was marked for deletion
 * @param {int} mark_id
 */
function isMarkDeleted(mark_id) {

}

/**
 * Gets if the state of the custom mark is such that it should appear checked
 * @param {int} component_id
 * @throws Error if the component id doesn't exist
 * @throws Error if the component is opened in edit mode
 */
function isCustomMarkChecked(component_id) {

}

/**
 * DOM Callback methods
 * TODO:
 */

/**
 * Called when a mark is marked for deletion
 * @param me DOM Element of the delete button
 */
function onDeleteMark(me) {

}

/**
 * Called when the point value of a common mark changes
 * @param me DOM Element of the mark point entry
 */
function onMarkPointsChange(me) {

}

/**
 * Called when the mark stats button is pressed
 * @param me DOM Element of the mark stats button
 */
function onGetMarkStats(me) {

}

/**
 * Called when a component gets clicked (for opening / closing)
 * @param me DOM Element of the component div
 */
function onClickComponent(me) {

}

/**
 * Called when the 'cancel' button is pressed on an open component
 * @param me DOM Element of the cancel button
 */
function onCancelComponent(me) {

}

/**
 * Called when the 'save' button is pressed on an open component
 * @param me DOM Element of the save button
 */
function onSaveComponent(me) {

}

/**
 * Called when the overall comment box get clicked (for opening / closing)
 * @param me DOM Element of the overall comment box
 */
function onClickOverallComment(me) {

}

/**
 * Called when the 'cancel' button is pressed on the overall comment box
 * @param me DOM element of the cancel button
 */
function onCancelOverallComment(me) {

}

/**
 * Called when the 'save' button is pressed on the overall comment box
 * @param me DOM element of the save button
 */
function onSaveOverallComment(me) {

}

/**
 * Called when a mark is clicked in grade mode
 * @param me DOM Element of the mark row
 */
function onToggleMark(me) {

}

/**
 * Called when one of the custom mark fields changes
 * @param me DOM Element of one of the custom mark's elements
 */
function onCustomMarkChange(me) {

}

/**
 * Callback for the 'verify' buttons
 * @param me DOM Element of the verify button
 */
function onVerifyComponent(me) {

}

/**
 * Callback for the 'verify all' button
 * @param me DOM Element of the verify all button
 */
function onVerifyAll(me) {

}

/**
 * Put all of the primary logic of the TA grading rubric here
 *
 */

/**
 * Searches a array of marks for a mark with an id
 * @param {Object[]} marks
 * @param {int} mark_id
 * @return {Object}
 */
function getMarkFromMarkArray(marks, mark_id) {
    for (let mark in marks) {
        if (mark.id === mark_id) {
            return mark;
        }
    }
    return null;
}

/**
 * Call this once on page load to load the rubric for grading a submitter
 * @param {string} gradeable_id
 * @param {string} anon_id
 */
function initializeGradingRubric(gradeable_id, anon_id) {
    let gradeable_tmp = null;
    ajaxGetGradeableRubric(gradeable_id)
        .catch(function (err) {
            alert('Could not fetch gradeable rubric: ' + err.message);
        })
        .then(function (gradeable) {
            gradeable_tmp = gradeable;
            return ajaxGetGradedGradeable(gradeable_id, anon_id);
        })
        .catch(function (err) {
            alert('Could not fetch graded gradeable: ' + err.message);
        })
        .then(function (graded_gradeable) {
            return renderGradingGradeable(gradeable_tmp, graded_gradeable);
        })
        .then(function (elements) {
            setRubricDOMElements(elements);
            openCookieComponent();
        })
        .catch(function (err) {
            alert("Could not render gradeable: " + err.message);
            console.error(err);
        });
}

/**
 * Call this once on page load to load the rubric instructor editing
 * @param {string} gradeable_id
 */
function initializeInstructorEditRubric(gradeable_id) {
    let gradeable_tmp = null;
    ajaxGetGradeableRubric(gradeable_id)
        .catch(function (err) {
            alert('Could not fetch gradeable rubric: ' + err.message);
        })
        .then(function (graded_gradeable) {
            return renderInstructorEditGradeable(gradeable_tmp, graded_gradeable);
        })
        .then(function (elements) {
            setRubricDOMElements(elements);
            openCookieComponent();
        })
        .catch(function (err) {
            alert("Could not render gradeable: " + err.message);
            console.error(err);
        });
}

/**
 * Opens the component that was stored in a cookie
 */
function openCookieComponent() {
    showMarkList(getOpenComponentIdFromCookie());
}

/**
 * Toggles a the open/close state of a component
 * @param {int} component_id the component's id
 * @param {boolean} saveChanges
 * @return {Promise}
 */
function toggleComponent(component_id, saveChanges) {
    // Component is open, so close it
    if (isComponentOpen(component_id)) {
        return closeComponent(component_id);
    }
    let sequence = Promise.resolve();

    // Overall Comment box is open, so close it
    if (isOverallCommentOpen()) {
        sequence = sequence.then(function () {
            return closeOverallComment(true);
        });
    }

    // Close all open components.  There shouldn't be more than one,
    //  but just in case there is...
    getOpenComponentIds().forEach(function (id) {
        sequence = sequence.then(function () {
            return closeComponent(id);
        });
    });

    // Finally, open the component
    return sequence.then(function () {
        return openComponent(component_id);
    });
}

/**
 * Toggles a the open/close state of the general comment
 * @param {boolean} saveChanges
 * @return {Promise}
 */
function toggleOverallComment(saveChanges) {
    // Overall comment open, so close it
    if (isOverallCommentOpen()) {
        return closeOverallComment(saveChanges);
    }

    // Close all open components.  There shouldn't be more than one,
    //  but just in case there is...
    let sequence = Promise.resolve();
    getOpenComponentIds().forEach(function (id) {
        sequence = sequence.then(function () {
            return closeComponent(id);
        });
    });

    // Finally, open the overall comment
    return sequence.then(openOverallComment);
}

/**
 * Toggles the state of a mark in grade mode
 * @return {Promise}
 */
function toggleCommonMark(component_id, mark_id) {
    return isMarkChecked(mark_id) ? unCheckMark(component_id, mark_id) : checkMark(component_id, mark_id);
}

/**
 * Call to update the custom mark state when any of the custom mark fields change
 * @param {int} component_id
 * @return {Promise}
 */
function updateCustomMark(component_id) {
    if (isCustomMarkChecked(component_id)) {
        // Uncheck the first mark just in case it's checked
        return unCheckFirstMark(component_id);
    } else {
        // Note: this is in the else block since `unCheckFirstMark` calls this function
        return refreshGradedComponent(component_id, true);
    }
}

/**
 * Opens a component for instructor edit mode
 * NOTE: don't call this function on its own.  Call 'openComponent' Instead
 * @param {int} component_id
 * @return {Promise}
 */
function openComponentInstructorEdit(component_id) {
    let gradeable_id = getGradeableId();
    return ajaxGetComponentRubric(gradeable_id, component_id)
        .then(function (component) {
            // Set the global mark list data for this component for conflict resolution
            OLD_MARK_LIST[component_id] = component.marks;

            // Render the component in instructor edit mode
            //  and 'true' to show the mark list
            return renderInstructorEditComponent(component, true);
        });
}

/**
 * Opens a component for grading mode (including normal edit mode)
 * NOTE: don't call this function on its own.  Call 'openComponent' Instead
 * @param {int} component_id
 * @return {Promise}
 */
function openComponentGrading(component_id) {
    let component_tmp = null;
    let gradeable_id = getGradeableId();
    let anon_id = getAnonId();
    let edit_mode = isEditModeEnabled();
    return ajaxGetComponentRubric(gradeable_id, component_id)
        .then(function (component) {
            // Set the global mark list data for this component for conflict resolution
            OLD_MARK_LIST[component_id] = component.marks;

            component_tmp = component;
            return ajaxGetGradedComponent(gradeable_id, component_id, anon_id);
        })
        .then(function (graded_component) {
            // Render the grading component with edit mode if enabled,
            //  and 'true' to show the mark list
            return renderGradingComponent(component_tmp, graded_component, edit_mode, true);
        });
}

/**
 * Opens the requested component
 * Note: This does not close the currently open component
 * @param {int} component_id
 * @return {Promise}
 */
function openComponent(component_id) {
    // Achieve polymorphism in the interface using this `isInstructorEditEnabled` flag
    return (isInstructorEditEnabled() ? openComponentInstructorEdit(component_id) : openComponentGrading(component_id))
        .catch(function (err) {
            console.error(err);
            alert('Error loading component!  Refresh the page and try again');
        });
}

/**
 * Closes a component for instructor edit mode and saves changes
 * NOTE: don't call this function on its own.  Call 'closeComponent' Instead
 * @param {int} component_id
 * @param {boolean} saveChanges If the changes to the component should be saved or discarded
 * @return {Promise}
 */
function closeComponentInstructorEdit(component_id, saveChanges) {
    let sequence = Promise.resolve();
    if (saveChanges) {
        sequence = sequence.then(function () {
            return saveMarkList(component_id);
        });
    }
    return sequence
        .then(function () {
            return ajaxGetComponentRubric(getGradeableId(), component_id);
        })
        .then(function (component) {
            // Render the component with a hidden mark list
            return renderInstructorEditComponent(component, false);
        });
}

/**
 * Closes a component for grading mode and saves changes
 * NOTE: don't call this function on its own.  Call 'closeComponent' Instead
 * @param {int} component_id
 * @param {boolean} saveChanges If the changes to the (graded) component should be saved or discarded
 * @return {Promise}
 */
function closeComponentGrading(component_id, saveChanges) {
    let sequence = Promise.resolve();
    let gradeable_id = getGradeableId();
    let component_tmp = null;
    let anon_id = getAnonId();

    if (!saveChanges) {
        // We aren't saving changes, so fetch the up-to-date grade / rubric data
        sequence = sequence
            .then(function () {
                return ajaxGetComponentRubric(gradeable_id, component_id);
            })
            .then(function (component) {
                component_tmp = component;
                return ajaxGetGradedComponent(gradeable_id, component_id, anon_id);
            });
    } else {
        // We are saving changes...
        if (isEditModeEnabled()) {
            // We're in edit mode, so save the component and fetch the up-to-date grade / rubric data
            sequence = sequence
                .then(function () {
                    return saveMarkList(component_id);
                })
                .then(function () {
                    return ajaxGetComponentRubric(gradeable_id, component_id);
                })
                .then(function (component) {
                    component_tmp = component;
                    return ajaxGetGradedComponent(gradeable_id, component_id, anon_id);
                });
        } else {
            // We're in grade mode, so save the graded component
            sequence = sequence
                .then(function () {
                    return saveGradedComponent(component_id);
                })
                .then(function () {
                    // This 'then' statement is here since the final 'then' expects a 'graded_component'
                    //  'resolve' parameter
                    return Promise.resolve(getGradedComponentFromDOM(component_id));
                });
        }
    }

    // Finally, render the graded component in non-edit mode with the mark list hidden
    return sequence.then(function (graded_component) {
        return renderGradingComponent(component_tmp, graded_component, false, false);
    });
}

/**
 * Closes the requested component and saves any changes if requested
 * @param component_id
 * @param {boolean} saveChanges If the changes to the (graded) component should be saved or discarded
 * @return {Promise}
 */
function closeComponent(component_id, saveChanges = true) {
    // Achieve polymorphism in the interface using this `isInstructorEditEnabled` flag
    return (isInstructorEditEnabled()
        ? closeComponentInstructorEdit(component_id, saveChanges)
        : closeComponentGrading(component_id, saveChanges))
        .catch(function (err) {
            console.error(err);
            alert('Error saving component!  Refresh the page and try again');
        });
}

/**
 * Fetches the up-to-date overall comment and opens it for editing
 * @return {Promise}
 */
function openOverallComment() {
    return ajaxGetOverallComment(getGradeableId(), getAnonId())
        .then(function (comment) {
            return renderOverallComment(comment, true);
        })
        .catch(function (err) {
            console.error(err);
            alert('Error fetching overall comment! Refresh the page and try again');
        });
}

/**
 * Closes and saves the overall comment
 * @param {boolean} saveChanges
 * @return {Promise}
 */
function closeOverallComment(saveChanges = true) {
    return ajaxSaveOverallComment(getGradeableId(), getAnonId(), getOverallCommentFromDOM())
        .then(function () {
            return refreshOverallComment(false);
        })
        .catch(function (err) {
            console.error(err);
            alert('Error saving overall comment! Refresh the page and try again!');
        });
}

/**
 * Checks the requested mark and refreshes the component
 * @param {int} component_id
 * @param {int} mark_id
 * @return {Promise}
 */
function checkMark(component_id, mark_id) {

}

/**
 * Un-checks the requested mark and refreshes the component
 * @param {int} component_id
 * @param {int} mark_id
 * @return {Promise}
 */
function unCheckMark(component_id, mark_id) {
    // First fetch the necessary information from the DOM
    let gradedComponent = getGradedComponentFromDOM();

    // Then remove the mark id from the array
    for(let i = 0; i < gradedComponent.mark_ids.length; ++i) {
        if(gradedComponent.mark_ids[i] === mark_id) {
            gradedComponent.mark_ids.splice(i, 1);
            break;
        }
    }

    // Finally, re-render the component
    return renderGradingComponent(getComponentFromDOM(component_id), gradedComponent, false, true);
}

/**
 * Un-checks the full credit / no credit mark of a component
 * @param {int} component_id
 * @return {Promise}
 */
function unCheckFirstMark(component_id) {
    return unCheckMark(component_id, getComponentFirstMarkId(component_id));
}

/**
 * Saves the mark list to the server for a component and handles any conflicts.
 * Properties that are saved are: mark point values, mark titles, and mark order
 * @param {int} component_id
 * @return {Promise}
 */
function saveMarkList(component_id) {
    return ajaxGetComponentRubric(getGradeableId(), component_id)
        .then(function (component) {
            let domMarkList = getComponentFromDOM(component_id).marks;
            let serverMarkList = component.marks;
            let oldServerMarkList = OLD_MARK_LIST[component_id];

            // associative array of associative arrays of marks with conflicts {<mark_id>: {domMark, serverMark, oldServerMark}, ...}
            let conflictMarks = {};

            let sequence = Promise.resolve();

            // For each DOM mark, try to save it
            domMarkList.forEach(function (domMark) {
                let serverMark = getMarkFromMarkArray(serverMarkList, domMark.id);
                let oldServerMark = getMarkFromMarkArray(oldServerMarkList, domMark.id);
                sequence = sequence
                    .then(function () {
                        return tryResolveMarkSave(domMark, serverMark, oldServerMark);
                    })
                    .then(function (success) {
                        // Success of false counts as a conflict
                        if (success !== true) {
                            conflictMarks[domMark.id] = {
                                domMark: domMark,
                                serverMark: serverMark,
                                oldServerMark: oldServerMark
                            };
                        }
                    });
            });

            return sequence
                .then(function () {
                    // Prompt the user with any conflicts
                    return promptUserMarkConflicts(conflictMarks);
                })
                .then(function (marks) {
                    // Get the resolution of those conflicts and save each
                    let sequence1 = Promise.resolve();
                    let gradeable_id = getGradeableId();

                    marks.forEach(function (mark) {
                        sequence1 = sequence1.then(function () {
                            return ajaxSaveMark(gradeable_id, component_id, mark.id, mark.points, mark.title);
                        });
                    });
                    return sequence1;
                });
        })
        .catch(function (err) {
            console.log(err);
            alert('Error Saving Rubric! Please refresh the page and try again');
        });
}

/**
 * Prompts the user with an array of conflict marks so they can individually resolve them
 * @param {{domMark, ServerMark, oldServerMark}[]} conflictMarks
 * @return {Promise} Promise resolves with an array of marks to save
 */
function promptUserMarkConflicts(conflictMarks) {
    // TODO: this needs to handle conflicts where a mark was deleted when someone
    // TODO: else changed it  See all paths where tryResolveMarkSave returns false
}

/**
 * Used to check if two marks are equal
 * @param {Object} mark0
 * @param {Object} mark1
 * @return {boolean}
 */
function marksEqual(mark0, mark1) {
    return mark0.points === mark1.points && mark0.title === mark1.title;
}

/**
 * Determines what to do when trying to save a mark provided the mark
 *  before edits, the DOM mark, and the server's up-to-date mark
 *  @return {Promise<boolean>}
 */
function tryResolveMarkSave(gradeable_id, component_id, domMark, serverMark, oldServerMark) {
    if (oldServerMark !== null) {
        if (serverMark !== null) {
            // Mark edited under normal conditions
            if (marksEqual(domMark, serverMark) || marksEqual(domMark, oldServerMark)) {
                // If the domMark is not unique, then we don't need to do anything
                return Promise.resolve(true);
            } else if (!marksEqual(serverMark, oldServerMark)) {
                // The domMark is unique, and the serverMark is also unique,
                // which means all 3 versions are different, which is a conflict state
                return Promise.resolve(false);
            } else if (isMarkDeleted(domMark.id)) {
                // domMark was deleted and serverMark hasn't changed from oldServerMark,
                //  so try to delete the mark
                return ajaxDeleteMark(gradeable_id, component_id, domMark.id)
                    .catch(function (err) {
                        // Catch here so failed deletions (because someone has received it)
                        //  don't make everything else fail
                        alert('Could not delete mark! ' + err.message);
                    })
                    .then(function () {
                        // Success, then return true
                        return Promise.resolve(true);
                    });
            } else {
                // The domMark is unique and the serverMark is the same as the oldServerMark
                //  so we should save the domMark to the server
                return ajaxSaveMark(gradeable_id, component_id, domMark.id, domMark.points, domMark.title)
                    .then(function () {
                        // Success, then return true
                        return Promise.resolve(true);
                    });
            }
        } else {
            // This means it was deleted from the server.
            if (!marksEqual(domMark, oldServerMark)) {
                // And the mark changed, which is a conflict state
                return Promise.resolve(false);
            } else {
                // And the mark didn't change, so don't do anything
                return Promise.resolve(true);
            }
        }
    } else {
        // This means it didn't exist when we started editing, so serverMark must also be null
        if (isMarkDeleted(domMark.id)) {
            // The mark was marked for deletion, but never existed... so do nothing
            return Promise.resolve(true);
        } else {
            // The mark never existed and isn't deleted, so its new
            return ajaxAddNewMark(gradeable_id, component_id, domMark.title, domMark.points)
                .then(function () {
                    // Success, then return true
                    return Promise.resolve(true);
                });
        }
    }
}

/**
 * Saves the component grade information to the server
 * @param component_id
 * @return {Promise}
 */
function saveGradedComponent(component_id) {
    let gradedComponent = getGradedComponentFromDOM(component_id);
    return ajaxSaveGradedComponent(
        getGradeableId(), component_id, getAnonId(),
        gradedComponent.active_version,
        gradedComponent.custom_points,
        gradedComponent.custom_message,
        isOverwriteGraderEnabled(),
        gradedComponent.mark_ids, true);
}

/**
 * Re-renders the graded component with the data in the DOM
 *  and preserves the edit/grade mode display
 * @param {int} component_id
 * @param {boolean} showMarkList Whether the mark list should be visible
 * @return {Promise}
 */
function refreshGradedComponent(component_id, showMarkList) {
    return renderGradingComponent(
        getComponentFromDOM(component_id),
        getGradedComponentFromDOM(component_id),
        isEditModeEnabled(), showMarkList);
}

/**
 * Re-renders the component with the data in the DOM
 * Note: This is only for instructor edit mode.  For grade mode,
 *  use `refreshGradedComponent`
 * @param component_id
 * @param {boolean} showMarkList Whether the mark list should be visible
 * @return {Promise}
 */
function refreshComponent(component_id, showMarkList) {
    return renderInstructorEditComponent(getComponentFromDOM(component_id), showMarkList);
}

/**
 * Re-renders the overall comment with the data in the DOM
 * @param {boolean} showEditable Whether the mark list should be visible
 * @return {Promise}
 */
function refreshOverallComment(showEditable) {
    return renderOverallComment(getOverallCommentFromDOM(), showEditable);
}

/**
 * Renders the provided component object for instructor edit mode
 * @param {Object} component
 * @param {boolean} showMarkList Whether the mark list should be visible
 * @return {Promise}
 */
function renderInstructorEditComponent(component, showMarkList) {

}

/**
 * Renders the provided component/graded_component object for grading/editing
 * @param {Object} component
 * @param {Object} graded_component
 * @param {boolean} edit_mode Whether the component should appear in edit or grade mode
 * @param {boolean} showMarkList Whether to show the mark list or not
 * @return {Promise}
 */
function renderGradingComponent(component, graded_component, edit_mode, showMarkList) {

}

/**
 * Renders the overall comment
 * @param {string} comment
 * @param {boolean} editable If the comment should be rendered in edit mode
 */
function renderOverallComment(comment, editable) {

}
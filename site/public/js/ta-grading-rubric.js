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

NO_COMPONENT_ID = -1;

CUSTOM_MARK_ID = 0;

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
            }),
            success: function (response) {
                if (response.status !== 'success') {
                    console.error('Something went wrong fetching the gradeable rubric: ' + response.message);
                    reject(new Error(response.message));
                } else {
                    resolve(response.data)
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            }
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
                'action': 'get_component_rubric',
                'gradeable_id': gradeable_id,
                'component_id': component_id,
            }),
            success: function (response) {
                if (response.status !== 'success') {
                    console.error('Something went wrong fetching the component rubric: ' + response.message);
                    reject(new Error(response.message));
                } else {
                    resolve(response.data)
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            }
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
            success: function (response) {
                if (response.status !== 'success') {
                    console.error('Something went wrong fetching the gradeable grade: ' + response.message);
                    reject(new Error(response.message));
                } else {
                    resolve(response.data)
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            }
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
            success: function (response) {
                if (response.status !== 'success') {
                    console.error('Something went wrong fetching the component grade: ' + response.message);
                    reject(new Error(response.message));
                } else {
                    // null is not the same as undefined, so we need to make that conversion before resolving
                    if(response.data === null) {
                        response.data = undefined;
                    }
                    resolve(response.data)
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            }
        });
    });
}

/**
 * ajax call to save the grading information for a component and submitter
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} anon_id
 * @param {int} graded_version
 * @param {float} custom_points
 * @param {string} custom_message
 * @param {boolean} overwrite True to overwrite the component's grader
 * @param {int[]} mark_ids
 * @param {boolean} async
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxSaveGradedComponent(gradeable_id, component_id, anon_id, graded_version, custom_points, custom_message, overwrite, mark_ids, async) {
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
                'graded_version': graded_version,
                'custom_points': custom_points,
                'custom_message': custom_message,
                'overwrite': overwrite,
                'mark_ids': mark_ids
            },
            success: function (response) {
                if (response.status !== 'success') {
                    console.error('Something went wrong saving the component grade: ' + response.message);
                    reject(new Error(response.message));
                } else {
                    resolve(response.data)
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            }
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
                'action': 'get_overall_comment'
            }),
            data: {
                'gradeable_id': gradeable_id,
                'anon_id': anon_id
            },
            success: function (response) {
                if (response.status !== 'success') {
                    console.error('Something went wrong fetching the gradeable comment: ' + response.message);
                    reject(new Error(response.message));
                } else {
                    resolve(response.data)
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            }
        });
    });
}

/**
 * ajax call to save the general comment for the graded gradeable
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @param {string} overall_comment
 * @param {boolean} async
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxSaveOverallComment(gradeable_id, anon_id, overall_comment, async = true) {
    return new Promise(function (resolve, reject) {
        $.getJSON({
            type: "POST",
            url: buildUrl({
                'component': 'grading',
                'page': 'electronic',
                'action': 'save_overall_comment'
            }),
            async: async,
            data: {
                'gradeable_id': gradeable_id,
                'anon_id': anon_id,
                'overall_comment': overall_comment
            },
            success: function (response) {
                if (response.status !== 'success') {
                    console.error('Something went wrong saving the overall comment: ' + response.message);
                    reject(new Error(response.message));
                } else {
                    resolve(response.data)
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            }
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
            success: function (response) {
                if (response.status !== 'success') {
                    console.error('Something went wrong adding a new mark: ' + response.message);
                    reject(new Error(response.message));
                } else {
                    resolve(response.data)
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            }
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
            success: function (response) {
                if (response.status !== 'success') {
                    console.error('Something went wrong deleting the mark: ' + response.message);
                    reject(new Error(response.message));
                } else {
                    resolve(response.data)
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            }
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
            success: function (response) {
                if (response.status !== 'success') {
                    console.error('Something went wrong saving the mark: ' + response.message);
                    reject(new Error(response.message));
                } else {
                    resolve(response.data)
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            }
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
            success: function (response) {
                if (response.status !== 'success') {
                    console.error('Something went wrong getting mark stats: ' + response.message);
                    reject(new Error(response.message));
                } else {
                    resolve(response.data)
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            }
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
            success: function (response) {
                if (response.status !== 'success') {
                    console.error('Something went wrong saving the mark order: ' + response.message);
                    reject(new Error(response.message));
                } else {
                    resolve(response.data)
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            }
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
            success: function (response) {
                if (response.status !== 'success') {
                    console.error('Something went wrong verifying the component: ' + response.message);
                    reject(new Error(response.message));
                } else {
                    resolve(response.data)
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            }
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
    // TODO:
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
    return $('#gradeable-rubric').attr('data-gradeable_id');
}

/**
 * Gets the anon_id of the submitter being graded
 * @return {string}
 */
function getAnonId() {
    return $('#anon-id').attr('data-anon_id');
}

/**
 * Used to determine if the interface displayed is for
 *  instructor edit mode (i.e. in the Edit Gradeable page)
 *  @return {boolean}
 */
function isInstructorEditEnabled() {
    return false; // TODO
}

/**
 * Used to determine if the mark list should be displayed
 *  in 'edit' mode for grading
 *  @return {boolean}
 */
function isEditModeEnabled() {
    return $('#edit-mode-enabled').is(':checked');
}

/**
 * Gets if grader overwrite mode is enabled
 * @return {boolean}
 */
function isOverwriteGraderEnabled() {
    return $('#overwrite-id').is(':checked');
}

/**
 * Sets the DOM elements to render for the entire rubric
 * @param elements
 */
function setRubricDOMElements(elements) {
    let gradingBox = $("#grading-box");
    gradingBox.html(elements);
}

/**
 * Gets the component id of a DOM element inside a component
 * @param me DOM element
 * @return {int}
 */
function getComponentIdFromDOMElement(me) {
    if ($(me).hasClass('component')) {
        return parseInt($(me).attr('data-component_id'));
    }
    return parseInt($(me).parents('.component').attr('data-component_id'));
}

/**
 * Gets the mark id of a DOM element inside a mark
 * @param me DOM element
 * @return {int}
 */
function getMarkIdFromDOMElement(me) {
    if ($(me).hasClass('mark-container')) {
        return parseInt($(me).attr('data-mark_id'));
    }
    return parseInt($(me).parents('.mark-container').attr('data-mark_id'));
}

/**
 * Gets the JQuery selector for the component id
 * Note: This is not the component container
 * @param component_id
 * @return {jQuery}
 */
function getComponentDOMElement(component_id) {
    return $('#component-' + component_id);
}

/**
 * Gets the JQuery selector for the mark id
 * @param mark_id
 * @return {jQuery}
 */
function getMarkDOMElement(mark_id) {
    return $('#mark-' + mark_id);
}

/**
 * Gets the JQuery selector for the component's custom mark
 * @param {int} component_id
 * @return {jQuery}
 */
function getCustomMarkDOMElement(component_id) {
    return getComponentDOMElement(component_id).find('.custom-mark-container');
}

/**
 * Gets the JQuery selector for the overall comment container
 * @return {jQuery}
 */
function getOverallCommentDOMElement() {
    return $('#overall-comment-container');
}

/**
 * Sets the HTML contents of the overall comment container
 * @param {string} contents
 */
function setOverallCommentContents(contents) {
    getOverallCommentDOMElement().html(contents);
}

/**
 * Sets the HTML contents of the specified component container
 * @param {int} component_id
 * @param {string} contents
 */
function setComponentContents(component_id, contents) {
    getComponentDOMElement(component_id).parent('.component-container').html(contents);
}

/**
 * Sets the HTMl contents of the total scores box
 * @param {string} contents
 */
function setTotalScoreBoxContents(contents) {
    $('#total-score-container').html(contents);
}

/**
 * Extracts a component object from the DOM
 * TODO: support instructor edit mode
 * @param {int} component_id
 * @return {Object}
 */
function getComponentFromDOM(component_id) {
    let domElement = getComponentDOMElement(component_id);
    return {
        id: component_id,
        title: domElement.attr('data-title'),
        ta_comment: domElement.attr('data-ta_comment'),
        student_comment: domElement.attr('data-student_comment'),
        page: parseInt(domElement.attr('data-page')),
        lower_clamp: parseFloat(domElement.attr('data-lower_clamp')),
        default: parseFloat(domElement.attr('data-default')),
        max_value: parseFloat(domElement.attr('data-max_value')),
        upper_clamp: parseFloat(domElement.attr('data-upper_clamp')),
        marks: getMarkListFromDOM(component_id)
    };
}

/**
 * Extracts an array of marks from the DOM
 * TODO: support publish
 * @param component_id
 * @return {Array}
 */
function getMarkListFromDOM(component_id) {
    let domElement = getComponentDOMElement(component_id);
    let markList = [];
    let i = 0;
    domElement.find('.ta-rubric-table .mark-container').each(function () {
        if (isEditModeEnabled()) {
            markList.push({
                id: parseInt($(this).attr('data-mark_id')),
                points: parseFloat($(this).find('input[type=number]').val()),
                title: $(this).find('textarea').val(),
                order: i
            });
        } else {
            // Don't add the custom mark
            if ($(this).hasClass('custom-mark-container')) {
                return;
            }
            markList.push({
                id: parseInt($(this).attr('data-mark_id')),
                points: parseFloat($(this).find('.mark-points').attr('data-points')),
                title: $(this).find('.mark-title').attr('data-title'),
                order: i
            });
        }
        i++;
    });
    return markList;
}

/**
 * Extracts a graded component object from the DOM
 * @param {int} component_id
 * @return {Object}
 */
function getGradedComponentFromDOM(component_id) {
    let domElement = getComponentDOMElement(component_id);
    let customMarkContainer = domElement.find('.custom-mark-container');

    // Get all of the marks that are 'selected'
    let mark_ids = [];
    let customMarkSelected = false;
    domElement.find('span.mark-selected').each(function () {
        let mark_id = parseInt($(this).attr('data-mark_id'));
        if(mark_id === CUSTOM_MARK_ID) {
            customMarkSelected = true;
        } else {
            mark_ids.push(mark_id);
        }
    });

    let dataDOMElement = domElement.find('.graded-component-data')
    return {
        score: parseFloat(customMarkContainer.find('input[type=number]').val()),
        comment: customMarkContainer.find('textarea').val(),
        custom_mark_selected: customMarkSelected,
        mark_ids: mark_ids,
        graded_version: parseInt(dataDOMElement.attr('data-graded_version')),
        grade_time: dataDOMElement.attr('data-grade_time'),
        grader_id: dataDOMElement.attr('data-grader_id'),
        verifier_id: dataDOMElement.attr('data-verifier_id')
    };
}

/**
 * Gets the scores data from the DOM (auto grading earned/possible and ta grading possible)
 * @return {Object}
 */
function getScoresFromDOM() {
    let dataDOMElement = $('#gradeable-scores-id');

    // Get the TA grading scores
    let scores = {
        ta_grading_earned: getTaGradingEarned(),
        ta_grading_total: getTaGradingTotal()
    };

    // Then check if auto grading scorse exist before adding them
    let autoGradingTotal = dataDOMElement.attr('data-auto_grading_total');
    if (autoGradingTotal !== '') {
        scores.auto_grading_earned = parseInt(dataDOMElement.attr('data-auto_grading_earned'));
        scores.auto_grading_total = parseInt(autoGradingTotal);
    }

    return scores;
}

/**
 * Gets the number of ta grading points the student has been awarded
 * @return {number|undefined} Undefined if no score data exists
 */
function getTaGradingEarned() {
    let total = 0.0;
    let anyPoints = false;
    $('.graded-component-data').each(function () {
        let pointsEarned = $(this).attr('data-total_score');
        if (pointsEarned === '') {
            return;
        }
        total += parseFloat(pointsEarned);
        anyPoints = true;
    });
    if (!anyPoints) {
        return undefined;
    }
    return total;
}

/**
 * Gets the number of ta grading points that can be earned
 * @return {number}
 */
function getTaGradingTotal() {
    let total = 0.0;
    $('.component').each(function () {
        total += parseFloat($(this).attr('data-max_value'));
    });
    return total;
}

/**
 * Gets the overall comment message stored in the DOM
 * @return {string} This will always be blank in instructor edit mode
 */
function getOverallCommentFromDOM() {
    let staticComment = $('span#overall-comment');
    let editComment = $('textarea#overall-comment');

    if (editComment.length > 0) {
        return editComment.val();
    } else if (editComment.length > 0) {
        return staticComment.html();
    }
    return '';
}

/**
 * Gets the ids of all open components
 * @return {Array}
 */
function getOpenComponentIds() {
    let component_ids = [];
    $('.ta-rubric-table:visible').each(function () {
        component_ids.push($(this).attr('data-component_id'));
    });
    return component_ids;
}

/**
 * Gets the component id from its order on the page
 * @param order
 * @return {int}
 */
function getComponentIdByOrder(order) {
    return $('.component-container')[order].find('.component').attr('data-component_id');
}

/**
 * Gets the order of a component in the list
 * @param {int} component_id
 * @return {int}
 */
function getComponentOrderById(component_id) {
    let i = 0;
    let order = 0;
    $('.component-container').each(function () {
        if ($(this).find('#component-' + component_id)) {
            order = i;
        }
        i++;
    });
    return order;
}

/**
 * Gets the id of the next component in the list
 * @param {int} component_id
 * @return {int}
 */
function getNextComponentId(component_id) {
    return $('#component-' + component_id).parent().next().child().attr('data-component_id');
}

/**
 * Gets the id of the previous component in the list
 * @param {int} component_id
 * @return {int}
 */
function getPrevComponentId(component_id) {
    return $('#component-' + component_id).parent().prev().child().attr('data-component_id');
}

/**
 * Gets the first open component on the page
 * @return {int}
 */
function getFirstOpenComponentId() {
    let component_ids = getOpenComponentIds();
    if (component_ids.length === 0) {
        return NO_COMPONENT_ID;
    }
    return component_ids[0];
}

/**
 * Gets the number of components on the page
 * @return {int}
 */
function getComponentCount() {
    return $('.component-container').length;
}

/**
 * Gets the id of the open component from the cookie
 * @return {int} Returns zero of no open component exists
 */
function getOpenComponentIdFromCookie() {
    return 0; //TODO:
}

/**
 * Gets the id of the no credit / full credit mark of a component
 * @param {int} component_id
 * @return {int}
 * @throws Error if the component id doesn't exist
 */
function getComponentFirstMarkId(component_id) {
    return parseInt(getComponentDOMElement(component_id).find('.mark-container').first().attr('data-mark_id'));
}

/**
 * Shows the mark list for a provided component
 *  Note: this is NOT the same as openComponent.
 * @param {int} component_id
 * @throws Error if the component id doesn't exist
 */
function showMarkList(component_id) {
    getComponentDOMElement(component_id).find('.ta-rubric-table').show();
}

/**
 * Gets if a component is open
 * @param component_id
 * @return {boolean}
 * @throws Error if the component id doesn't exist
 */
function isComponentOpen(component_id) {
    return !getComponentDOMElement(component_id).find('.ta-rubric-table').is(':hidden');
}

/**
 * Gets if the overall comment is open
 * @return {boolean}
 */
function isOverallCommentOpen() {
    return $('textarea#overall-comment').length > 0;
}

/**
 * Gets if a mark is 'checked'
 * @param {int} mark_id
 * @return {boolean}
 */
function isMarkChecked(mark_id) {
    return getMarkDOMElement(mark_id).find('span.mark-selected').length > 0;
}

/**
 * Toggles the state of a mark
 * @param {int} mark_id
 */
function toggleMarkChecked(mark_id) {
    if (isEditModeEnabled()) {
        return;
    }
    getMarkDOMElement(mark_id).find('.mark-selector').toggleClass('mark-selected');
}

/**
 * Gets if a mark was marked for deletion
 * @param {int} mark_id
 * @return {boolean}
 */
function isMarkDeleted(mark_id) {
    return getMarkDOMElement(mark_id).hasClass('mark-deleted');
}

/**
 * Gets if the state of the custom mark is such that it should appear checked
 * Note: if the component is in edit mode, this will never return true
 * @param {int} component_id
 * @return {boolean}
 */
function hasCustomMark(component_id) {
    if (isEditModeEnabled()) {
        return false;
    }
    let gradedComponent = getGradedComponentFromDOM(component_id);
    return gradedComponent.comment !== '';
}

/**
 * Gets if the custom mark on a component is 'checked'
 * @param {int} component_id
 * @return {boolean}
 */
function isCustomMarkChecked(component_id) {
    return getCustomMarkDOMElement(component_id).find('.mark-selected').length > 0;
}

/**
 * Checks the custom mark checkbox
 * @param {int} component_id
 */
function checkDOMCustomMark(component_id) {
    getCustomMarkDOMElement(component_id).find('.mark-selector').addClass('mark-selected');
}

/**
 * Un-checks the custom mark checkbox
 * @param {int} component_id
 */
function unCheckDOMCustomMark(component_id) {
    getCustomMarkDOMElement(component_id).find('.mark-selector').removeClass('mark-selected');
}

/**
 * Toggles the state of the custom mark checkbox in the DOM
 * @param {int} component_id
 */
function toggleDOMCustomMark(component_id) {
    getCustomMarkDOMElement(component_id).find('.mark-selector').toggleClass('mark-selected');
}

/**
 * DOM Callback methods
 *
 */

/**
 * Called when the 'add new mark' div gets pressed
 * @param me DOM element of the 'add new mark' div
 */
function onAddNewMark(me) {
    addNewMark(getComponentIdFromDOMElement(me))
        .catch(function (err) {
            console.error(err);
            alert('Error adding mark!');
        });
}

/**
 * Called when a mark is marked for deletion
 * @param me DOM Element of the delete button
 */
function onDeleteMark(me) {
    $(me).parents('.mark-container').toggleClass('mark-deleted');
    $(me).hide();
    $(me).sibling('.restore-mark-container').show();
}

/**
 * Called when a mark marked for deletion gets restored
 * @param me DOM Element of the restore button
 */
function onRestoreMark(me) {
    $(me).parents('.mark-container').toggleClass('mark-deleted');
    $(me).hide();
    $(me).sibling('.delete-mark-container').show();
}

/**
 * Called when the point value of a common mark changes
 * @param me DOM Element of the mark point entry
 */
function onMarkPointsChange(me) {
    refreshGradedComponent(getComponentIdFromDOMElement(me), true)
        .catch(function (err) {
            console.err(err);
            alert('Error updating component!');
        });
}

/**
 * Called when the mark stats button is pressed
 * @param me DOM Element of the mark stats button
 */
function onGetMarkStats(me) {
    //TODO:
}

/**
 * Called when a component gets clicked (for opening / closing)
 * @param me DOM Element of the component header div
 */
function onClickComponent(me) {
    toggleComponent(getComponentIdFromDOMElement(me), true)
        .catch(function (err) {
            console.error(err);
            alert('Error opening/closing component!');
        });
}

/**
 * Called when the 'cancel' button is pressed on an open component
 * @param me DOM Element of the cancel button
 */
function onCancelComponent(me) {
    toggleComponent(getComponentIdFromDOMElement(me), false)
        .catch(function (err) {
            console.error(err);
            alert('Error closing component!');
        });
}

/**
 * Called when the overall comment box get clicked (for opening / closing)
 * @param me DOM Element of the overall comment box
 */
function onClickOverallComment(me) {
    toggleOverallComment(true)
        .catch(function (err) {
            console.error(err);
            alert('Error opening/closing overall comment!');
        });
}

/**
 * Called when the 'cancel' button is pressed on the overall comment box
 * @param me DOM element of the cancel button
 */
function onCancelOverallComment(me) {
    toggleOverallComment(false)
        .catch(function (err) {
            console.error(err);
            alert('Error closing overall comment!');
        });
}

/**
 * Called when a mark is clicked in grade mode
 * @param me DOM Element of the mark div
 */
function onToggleMark(me) {
    toggleCommonMark(getComponentIdFromDOMElement(me), getMarkIdFromDOMElement(me))
        .catch(function (err) {
            console.error(err);
            alert('Error toggling mark!');
        });
}

/**
 * Called when one of the custom mark fields changes
 * @param me DOM Element of one of the custom mark's elements
 */
function onCustomMarkChange(me) {
    updateCustomMark(getComponentIdFromDOMElement(me))
        .catch(function (err) {
            console.error(err);
            alert('Error updating custom mark!');
        });
}

/**
 * Toggles the 'checked' state of the custom mark.  This effectively
 *  makes the 'score' and 'comment' values 0 and '' respectively when
 *  loading the graded component from the DOM, but leaves the values in
 *  the DOM if the user toggles this again.
 * @param me
 */
function onToggleCustomMark(me) {
    let component_id = getComponentIdFromDOMElement(me);
    toggleDOMCustomMark(component_id);
    toggleCustomMark(component_id)
        .catch(function (err) {
            console.error(err);
            alert('Error toggling custom mark!');
        });
}

/**
 * Callback for the 'verify' buttons
 * @param me DOM Element of the verify button
 */
function onVerifyComponent(me) {
    // TODO:
}

/**
 * Callback for the 'verify all' button
 * @param me DOM Element of the verify all button
 */
function onVerifyAll(me) {
    // TODO:
}

/**
 * Callback for the 'edit mode' checkbox changing states
 * @param me DOM Element of the checkbox
 */
function onToggleEditMode(me) {
    // Get the open components so we know which one to open once they're all saved
    let open_component_ids = getOpenComponentIds();
    let reopen_component_id = NO_COMPONENT_ID;
    if (open_component_ids.length !== 0) {
        reopen_component_id = open_component_ids[0];
    }

    closeAllComponents(true).then(function () {
        if (reopen_component_id !== NO_COMPONENT_ID) {
            return openComponent(reopen_component_id);
        }
    });
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
    ajaxGetGradeableRubric(gradeable_id)
        .catch(function (err) {
            alert('Could not fetch gradeable rubric: ' + err.message);
        })
        .then(function (gradeable) {
            return renderInstructorEditGradeable(gradeable);
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
 * Closes all open components and the overall comment
 * @param save_changes
 * @return {Promise<void>}
 */
function closeAllComponents(save_changes) {
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
    return sequence;
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
        return closeComponent(component_id, saveChanges);
    }

    return closeAllComponents(saveChanges)
        .then(function () {
            return openComponent(component_id);
        });
}

/**
 * Toggles a the open/close state of the overall comment
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
 * Adds a new mark to the DOM and refreshes the display
 * @param {int} component_id
 * @return {Promise}
 */
function addNewMark(component_id) {
    let component = getComponentFromDOM(component_id);
    component.marks.push({
        id: 0,
        title: '',
        score: 0.0,
        publish: false,
        order: component.marks.length
    });
    let promise = Promise.resolve();
    if (!isInstructorEditEnabled()) {
        let graded_component = getGradedComponentFromDOM(component_id);
        promise = promise.then(function () {
            return injectGradingComponent(component, graded_component, true, true);
        });
    } else {
        promise = promise.then(function () {
            return injectInstructorEditComponent(component, true);
        });
    }
    return promise;
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
    if (hasCustomMark(component_id)) {
        // Check the mark if it isn't already
        checkDOMCustomMark(component_id);

        // Uncheck the first mark just in case it's checked
        return unCheckFirstMark(component_id);
    } else {
        // Automatically uncheck the custom mark if it's no longer relevant
        unCheckDOMCustomMark(component_id);

        // Note: this is in the else block since `unCheckFirstMark` calls this function
        return refreshGradedComponent(component_id, true);
    }
}

/**
 * Call to toggle the custom mark 'checked' state without removing its data
 * @param {int} component_id
 * @return {Promise}
 */
function toggleCustomMark(component_id) {
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
            return injectInstructorEditComponent(component, true);
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
    return ajaxGetComponentRubric(gradeable_id, component_id)
        .then(function (component) {
            // Set the global mark list data for this component for conflict resolution
            OLD_MARK_LIST[component_id] = component.marks;

            component_tmp = component;
            return ajaxGetGradedComponent(gradeable_id, component_id, getAnonId());
        })
        .then(function (graded_component) {
            // Render the grading component with edit mode if enabled,
            //  and 'true' to show the mark list
            return injectGradingComponent(component_tmp, graded_component, isEditModeEnabled(), true);
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
            return injectInstructorEditComponent(component, false);
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
    let anon_id = getAnonId();
    let component_tmp = null;

    if (!saveChanges) {
        // We aren't saving changes, so fetch the up-to-date grade / rubric data
        sequence = sequence
            .then(function () {
                return ajaxGetComponentRubric(gradeable_id, component_id);
            })
            .then(function (component) {
                component_tmp = component;
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
                });
        } else {
            // We're in grade mode, so save the graded component
            sequence = sequence
                .then(function () {
                    return saveGradedComponent(component_id);
                });
        }
    }

    // Finally, render the graded component in non-edit mode with the mark list hidden
    return sequence
        .then(function () {
            return ajaxGetGradedComponent(gradeable_id, component_id, anon_id);
        })
        .then(function (graded_component) {
            // If this wasn't set (fetched from the remote), just load it from the DOM
            if (component_tmp === null) {
                component_tmp = getComponentFromDOM(component_id);
            }

            return injectGradingComponent(component_tmp, graded_component, false, false);
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
            return injectOverallComment(comment, true);
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
    if (saveChanges) {
        return ajaxSaveOverallComment(getGradeableId(), getAnonId(), getOverallCommentFromDOM())
            .then(function () {
                return refreshOverallComment(false);
            })
            .catch(function (err) {
                console.error(err);
                alert('Error saving overall comment! Refresh the page and try again!');
            });
    } else {
        return ajaxGetOverallComment(getGradeableId(), getAnonId())
            .then(function (comment) {
                return injectOverallComment(comment, false);
            })
            .catch(function (err) {
                console.error(err);
                alert('Error fetching overall comment! Refresh the page and try again!');
            });
    }
}

/**
 * Checks the requested mark and refreshes the component
 * @param {int} component_id
 * @param {int} mark_id
 * @return {Promise}
 */
function checkMark(component_id, mark_id) {
    // First fetch the necessary information from the DOM
    let gradedComponent = getGradedComponentFromDOM(component_id);

    // Uncheck the first mark if it's checked
    let firstMarkId = getComponentFirstMarkId(component_id);
    if (isMarkChecked(firstMarkId)) {
        // If first mark is checked, it will be the first element in the array
        // TODO: probably
        gradedComponent.mark_ids.splice(0, 1);
    }

    // Then add the mark id to the array
    gradedComponent.mark_ids.push(mark_id);

    // Finally, re-render the component
    return injectGradingComponent(getComponentFromDOM(component_id), gradedComponent, false, true);
}

/**
 * Un-checks the requested mark and refreshes the component
 * @param {int} component_id
 * @param {int} mark_id
 * @return {Promise}
 */
function unCheckMark(component_id, mark_id) {
    // First fetch the necessary information from the DOM
    let gradedComponent = getGradedComponentFromDOM(component_id);

    // Then remove the mark id from the array
    for (let i = 0; i < gradedComponent.mark_ids.length; ++i) {
        if (gradedComponent.mark_ids[i] === mark_id) {
            gradedComponent.mark_ids.splice(i, 1);
            break;
        }
    }

    // Finally, re-render the component
    return injectGradingComponent(getComponentFromDOM(component_id), gradedComponent, false, true);
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
            let domMarkList = getMarkListFromDOM(component_id);
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
                            if (isMarkDeleted(mark.id)) {
                                // This should be a pretty rare case
                                return ajaxDeleteMark(gradeable_id, component_id, mark.id)
                                    .catch(function (err) {
                                        // Catch here so failed deletions (because someone has received it)
                                        //  don't make everything else fail
                                        alert('Could not delete mark! ' + err.message);
                                    });
                            } else {
                                return ajaxSaveMark(gradeable_id, component_id, mark.id, mark.points, mark.title);
                            }
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

    let acceptMarks = [];
    for (let marks in conflictMarks) {
        acceptMarks.push(marks.domMark);
    }
    return Promise.resolve(acceptMarks);
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
        gradedComponent.graded_version,
        gradedComponent.custom_mark_selected ? gradedComponent.score : 0.0,
        gradedComponent.custom_mark_selected ? gradedComponent.comment : '',
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
    return injectGradingComponent(
        getComponentFromDOM(component_id),
        getGradedComponentFromDOM(component_id),
        isEditModeEnabled(), showMarkList);
}

/**
 * Re-renders the component with the data in the DOM
 * @param component_id
 * @param {boolean} showMarkList Whether the mark list should be visible
 * @return {Promise}
 */
function refreshInstructorEditComponent(component_id, showMarkList) {
    return injectInstructorEditComponent(getComponentFromDOM(component_id), showMarkList);
}

/**
 * Re-renders the overall comment with the data in the DOM
 * @param {boolean} showEditable Whether the mark list should be visible
 * @return {Promise}
 */
function refreshOverallComment(showEditable) {
    return injectOverallComment(getOverallCommentFromDOM(), showEditable);
}

/**
 * Refreshes the 'total scores' box at the bottom of the gradeable
 * @return {Promise}
 */
function refreshTotalScoreBox() {
    return injectTotalScoreBox(getScoresFromDOM());
}

/**
 * Renders the provided component object for instructor edit mode
 * @param {Object} component
 * @param {boolean} showMarkList Whether the mark list should be visible
 * @return {Promise}
 */
function injectInstructorEditComponent(component, showMarkList) {

}

/**
 * Renders the provided component/graded_component object for grading/editing
 * @param {Object} component
 * @param {Object} graded_component
 * @param {boolean} editable Whether the component should appear in edit or grade mode
 * @param {boolean} showMarkList Whether to show the mark list or not
 * @return {Promise}
 */
function injectGradingComponent(component, graded_component, editable, showMarkList) {
    return renderGradingComponent(component, graded_component, editable, showMarkList)
        .then(function (elements) {
            setComponentContents(component.id, elements);
        })
        .then(function () {
            return refreshTotalScoreBox();
        });
}

/**
 * Renders the overall comment
 * @param {string} comment
 * @param {boolean} editable If the comment should be rendered in edit mode
 * @return {Promise}
 */
function injectOverallComment(comment, editable) {
    return renderOverallComment(comment, editable)
        .then(function (elements) {
            setOverallCommentContents(elements);
        });
}

/**
 * Renders the total scores box
 * @param {Object} scores
 * @return {Promise}
 */
function injectTotalScoreBox(scores) {
    return renderTotalScoreBox(scores)
        .then(function (elements) {
            setTotalScoreBoxContents(elements);
        });
}
/// When no component is selected, the current ID will be this value
NO_COMPONENT_ID = -1;

/// Component ID of the "General Comment" box at the bottom
GENERAL_MESSAGE_ID = -2;

OPENEDCOMPONENTS = [];

gradeable = null;
anon_id = null;

/**
 * Get the page-wide Gradeable object
 * @returns {Object} Gradeable data
 */
function getGradeable() {
    return gradeable;
}

/**
 * Get the page-wide Anon-id object
 * @returns {string} Anonymous id of the submitter
 */
function getAnonId() {
    return anon_id;
}

/**
 * Fetches a given component from the server
 * @param {int} component_id
 * @returns {Object} Component data
 */
function fetchComponent(component_id) {
    let components = getGradeable().components;
    for (let i = 0; i < components.length; i++) {
        if (components[i].id === component_id) {
            return components[i];
        }
    }
    return null;
}

/**
 * Gets a mark instance from a component instance and mark id
 *  Note: This component can come from `fetchComponent` or from `getComponentFromDOM`
 * @param {Object} component
 * @param {int} mark_id
 * @returns {*}
 */
function getMark(component, mark_id) {
    let marks = component.marks;
    for (let i = 0; i < marks.length; i++) {
        if (marks[i].id === mark_id) {
            return marks[i];
        }
    }
    return null;
}

/**
 * Gets a Component instance with data extracted from the DOM
 * @param {int} component_id
 * @return {Object}
 */
function getComponentFromDOM(component_id) {

}

/**
 * Sets the custom mark for a component in the DOM
 * @param {int} component_id
 * @param {number} points
 * @param {string} text
 */
function setCustomMarkToDOM(component_id, points, text) {

    if(text === '') {
        //uncheck mark and set points to zero
    }
    // TODO: do something
    let table_row = $('#mark_custom_id-' + component_id);
    let is_selected = false;
    let icon = table_row.find(".mark");
    let number_input = table_row.find("input");
    let text_input = table_row.find("textarea");

    if (parseFloat(number_input.val()) !== 0.0 || text_input.val() !== "") {
        is_selected = true;
    }

    icon.toggleClass("mark-has", is_selected);
}

/**
 * Removes a mark from the DOM
 * @param {int} component_id
 * @param {int} mark_id
 */
function removeMarkFromDOM(component_id, mark_id) {

}

function getMarkFromDOM(component_id, mark_id) {

}

function getGradedComponentFromDOM(component_id) {

}

/**
 * DOM callback for changing the note for the custom mark
 * @param {int} component_id
 */
function updateCustomMarkText(component_id) {
    let component = getComponentFromDOM(component_id);
    setCustomMarkToDOM(component.id, component.score, component.comment);

    updateFirstMarkState(component.id);
    updateProgressPoints(component.id);
}

/**
 * Render and return a view for the given mark
 * @param {int} component_id Unique component id
 * @param {int} mark_id Unique mark id
 * @param {boolean} gradeEnabled If the mark is in 'grade' mode
 * @param {boolean} editable If the mark is in 'edit' mode and can be edited
 * @returns DOM structure for the mark
 */
function getMarkView(component_id, mark_id, gradeEnabled, editable) {
    let template = gradeEnabled ? 'GradeMark' : 'EditMark';
    return Twig.twig({ref: template}).render({
        mark: getMark(component_id, mark_id),
        precision: getGradeable().precision,
        component_id: component_id,
        editable: editable
    });
}

/**
 * ajax call to fetch the gradeable's rubric
 * @param {string} gradeable_id
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function ajaxGetGradeableRubric(gradeable_id, successCallback = undefined, errorCallback = undefined) {
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
                if (typeof(errorCallback) === 'function') {
                    errorCallback(response.message, response.data);
                    return;
                }
                alert('Something went wrong fetching the gradeable rubric: ' + response.message);
            }
            else if (typeof(successCallback) === "function") {
                successCallback(response.data);
            }
        },
        error: function (err) {
            console.error("Failed to parse response.  The server isn't playing nice...");
            console.error(err);
            alert("There was an error with fetching marks. Please refresh the page and try again.");
        }
    });
}

/**
 * ajax call to get the entire graded gradeable for a user
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @param {function} successCallback
 * @param {function} errorCallback
 */
function ajaxGetGradedGradeable(gradeable_id, anon_id, successCallback = undefined, errorCallback = undefined) {
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
                if (typeof(errorCallback) === 'function') {
                    errorCallback(response.message, response.data);
                    return;
                }
                alert('Something went wrong fetching the graded gradeable: ' + response.message);
            }
            else if (typeof(successCallback) === "function") {
                successCallback(response.data);
            }
        },
        error: function (err) {
            console.error("Failed to parse response.  The server isn't playing nice...");
            console.error(err);
            alert("There was an error with fetching marks. Please refresh the page and try again.");
        }
    });
}

/**
 * ajax call to fetch an updated Graded Component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} anon_id
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function ajaxGetGradedComponent(gradeable_id, component_id, anon_id, successCallback = undefined, errorCallback = undefined) {
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
                if (typeof(errorCallback) === 'function') {
                    errorCallback(response.message, response.data);
                    return;
                }
                alert('Something went wrong fetching the component grade: ' + response.message);
            }
            else if (typeof(successCallback) === "function") {
                successCallback(response.data);
            }
        },
        error: function (err) {
            console.error("Failed to parse response.  The server isn't playing nice...");
            console.error(err);
            alert("There was an error with fetching marks. Please refresh the page and try again.");
        }
    })
}

/**
 * ajax call to fetch the general comment for the gradeable
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function ajaxGetGeneralComment(gradeable_id, anon_id, successCallback = undefined, errorCallback = undefined) {
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
        success: function (response) {
            if (response.status !== 'success') {
                if (typeof(errorCallback) === 'function') {
                    errorCallback(response.message, response.data);
                    return;
                }
                alert('Something went wrong saving the comment: ' + response.message);
            }
            else if (typeof(successCallback) === "function") {
                successCallback(response.data);
            }
        },
        error: function (err) {
            console.error("Failed to parse response.  The server isn't playing nice...");
            console.error(err);
            alert("There was an error with fetching marks. Please refresh the page and try again.");
        }
    })
}

/**
 * ajax call to add a new mark to the component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} note
 * @param {float} points
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function ajaxAddNewMark(gradeable_id, component_id, note, points, successCallback = undefined, errorCallback = undefined) {
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
            'note': note,
            'points': points
        },
        success: function (response) {
            if (response.status !== 'success') {
                if (typeof(errorCallback) === 'function') {
                    errorCallback(response.message, response.data);
                    return;
                }
                alert('Something went wrong adding the new mark: ' + response.message);
            }
            else if (typeof(successCallback) === "function") {
                successCallback(response.data);
            }
        },
        error: function (err) {
            console.error("Failed to parse response.  The server isn't playing nice...");
            console.error(err);
            alert("There was an error with fetching marks. Please refresh the page and try again.");
        }
    })
}

/**
 * ajax call to delete a mark
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {int} mark_id
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function ajaxDeleteMark(gradeable_id, component_id, mark_id, successCallback = undefined, errorCallback = undefined) {
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
                if (typeof(errorCallback) === 'function') {
                    errorCallback(response.message, response.data);
                    return;
                }
                alert('Something went wrong deleting the mark: ' + response.message);
            }
            else if (typeof(successCallback) === "function") {
                successCallback(response.data);
            }
        },
        error: function (err) {
            console.error("Failed to parse response.  The server isn't playing nice...");
            console.error(err);
            alert("There was an error with fetching marks. Please refresh the page and try again.");
        }
    })
}

/**
 * ajax call to get the stats about a mark
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {int} mark_id
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function ajaxGetMarkStats(gradeable_id, component_id, mark_id, successCallback = undefined, errorCallback = undefined) {
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
                if (typeof(errorCallback) === 'function') {
                    errorCallback(response.message, response.data);
                    return;
                }
                alert('Something went wrong getting mark stats: ' + response.message);
            }
            else if (typeof(successCallback) === "function") {
                successCallback(response.data);
            }
        },
        error: function (err) {
            console.error("Failed to parse response.  The server isn't playing nice...");
            console.error(err);
            alert("There was an error with fetching marks. Please refresh the page and try again.");
        }
    })
}

/**
 * ajax call to save the general comment for the graded gradeable
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @param {string} gradeable_comment
 * @param {boolean} async
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function ajaxSaveOveralComment(gradeable_id, anon_id, gradeable_comment, async, successCallback = undefined, errorCallback = undefined) {
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
        success: function (response) {
            if (response.status !== 'success') {
                if (typeof(errorCallback) === 'function') {
                    errorCallback(response.message, response.data);
                    return;
                }
                alert('Something went wrong saving the general comment: ' + response.message);
            }
            else if (typeof(successCallback) === "function") {
                successCallback(response.data);
            }
        },
        error: function (err) {
            console.error("Failed to parse response.  The server isn't playing nice...");
            console.error(err);
            alert("There was an error with fetching marks. Please refresh the page and try again.");
        }
    })
}

/**
 * ajax call to update the order of marks in a component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {*} order format: { <mark0-id> : <order0>, <mark1-id> : <order1>, ... }
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function ajaxSaveMarkOrder(gradeable_id, component_id, order, successCallback = undefined, errorCallback = undefined) {
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
                if (typeof(errorCallback) === 'function') {
                    errorCallback(response.message, response.data);
                    return;
                }
                alert('Something went wrong reordering marks: ' + response.message);
            }
            else if (typeof(successCallback) === "function") {
                successCallback(response.data);
            }
        },
        error: function (err) {
            console.error("Failed to parse response.  The server isn't playing nice...");
            console.error(err);
            alert("There was an error with fetching marks. Please refresh the page and try again.");
        }
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
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function ajaxSaveGradedComponent(gradeable_id, component_id, anon_id, active_version, custom_points, custom_message, overwrite, mark_ids, async, successCallback = undefined, errorCallback = undefined) {
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
        success: function (response) {
            if (response.status !== 'success') {
                if (typeof(errorCallback) === 'function') {
                    errorCallback(response.message, response.data);
                    return;
                }
                alert('Something went wrong saving the graded component: ' + response.message);
            }
            else if (typeof(successCallback) === "function") {
                successCallback(response.data);
            }
        },
        error: function (err) {
            console.error("Failed to parse response.  The server isn't playing nice...");
            console.error(err);
            alert("There was an error with fetching marks. Please refresh the page and try again.");
        }
    })
}

/**
 * ajax call to save mark point value / note
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {int} mark_id
 * @param {float} points
 * @param {string} note
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function ajaxSaveMark(gradeable_id, component_id, mark_id, points, note, successCallback = undefined, errorCallback = undefined) {
    $.getJSON({
        type: "POST",
        url: buildUrl({
            'component': 'grading',
            'page': 'electronic',
            'action': 'save_mark'
        }),
        data: {
            'gradeable_id' : gradeable_id,
            'component_id' : component_id,
            'mark_id' : mark_id,
            'points' : points,
            'note' : note,
        },
        success: function (response) {
            if (response.status !== 'success') {
                if (typeof(errorCallback) === 'function') {
                    errorCallback(response.message, response.data);
                    return;
                }
                alert('Something went wrong saving the mark: ' + response.message);
            }
            else if (typeof(successCallback) === "function") {
                successCallback(response.data);
            }
        },
        error: function (err) {
            console.error("Failed to parse response.  The server isn't playing nice...");
            console.error(err);
            alert("There was an error with fetching marks. Please refresh the page and try again.");
        }
    });
}

/**
 * ajax call to verify the grader of a component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} anon_id
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function ajaxVerifyComponent(gradeable_id, component_id, anon_id, verifyAll, successCallback = undefined, errorCallback = undefined){
    var action = (verifyAll) ? 'verify_all' : 'verify_grader';
    $.ajax({
        type: "POST",
        url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': action}),
        async: true,
        data: {
            'gradeable_id' : gradeable_id,
            'component_id' : component_id,
            'anon_id' : anon_id,
        },
        success: function (response) {
            if (response.status !== 'success') {
                if (typeof(errorCallback) === 'function') {
                    errorCallback(response.message, response.data);
                    return;
                }
                alert('Something went wrong saving the mark: ' + response.message);
            }
            else if (typeof(successCallback) === "function") {
                successCallback(response.data);
            }
        },
        error: function (err) {
            console.error("Failed to parse response.  The server isn't playing nice...");
            console.error(err);
            alert("There was an error with fetching marks. Please refresh the page and try again.");
        }
    })
}

/**
 * ajax call to verify all graders of a component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} anon_id
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function ajaxAllVerifyComponents() {

}

/**
 * TODO: figure this out
 * Compares the initially loaded, the DOM, and the most recent remote marks for differences
 *  to determine if there is a conflict that requires user action
 * @param {int} component_id
 * @returns Object with mark_id properties each containing 'local', 'remote', and 'dom' properties, which
 *                  hold a mark's 'point' value and 'title' state, along with a conflict 'resolution' of
 *                  'dom', 'remote', and 'conflict'
 */
function getMarkDiffs(component_id) {
    var marks = $('[name=mark_'+c_index+']');
    var mark_notes = $('[name^=mark_text_'+c_index+']');
    var mark_scores = $('[name^=mark_points_'+c_index+']');
    var custom_mark_points = $('input[name=mark_points_custom_'+c_index+']');
    var custom_mark_text = $('textarea[name=mark_text_custom_'+c_index+']');

    // Check if there were added/removed marks
    if (data.marks.length != marks.length)
        return true;

    // Check to see if any note or score value is different
    for (var x = 0; x < marks.length; x++) {
        if (mark_notes[x].innerHTML != data.marks[x].title ||
              mark_scores[x].value != data.marks[x].points)
            return true;
    }
    
    // Check to see if custom mark changed
    if (data.comment != custom_mark_text.val())
        return true;
    if (data.score != custom_mark_points.val())
        return true;

    // If length is 0 we have no common marks.
    // This is Very Bad because there should always be at least the No Credit mark.
    // Only thing we can do from here though is let requests go.
    if (data.marks.length === 0) {
        return true;
    }

    return false;
}

/**
  * Determine which order two marks should be displayed in. Used
  * With the sortable list of marks
*/
function compareOrder(mark1, mark2){
    if(mark1.order>mark2.order){
        return 1;
    }
    if(mark1.order<mark2.order){
        return -1;
    }
    return 0;
}

/**
 * TODO:Reload marks for a component and render them in the list
 * @param {int} component_id
 */
function refreshComponentMarkList(component_id) {
    let gradeable = getGradeable();
    let component = getComponent(component_id);
    let parent = $('#marks-parent-'+component_id);

    //Disabling reorder marks for now, renable by replacing the if statement with if(EditModeEnabled==true)
    if(false){
        var sortableMarks=$('#marks-parent-'+c_index);
        var sortEvent = function (event, ui){
            var rows=sortableMarks.find("tr:not(.ui-sortable-placeholder)");
            var listValues = [];
            for(var i=0; i<rows.length; i++){
                var row=rows[i];
                var id=row.id;
                if(typeof(row.dataset.mark_index) !== 'undefined') {
                    getMark(c_index, row.dataset.mark_index).order=i;
                    $(row).find(".mark-selector").text(i + 1);
                }
            }
            getComponent(c_index).marks.sort(compareOrder);
        };
        sortableMarks.sortable( { 
            items: 'tr:not(:first)',
            stop: sortEvent,
            disabled: false
        });
        sortableMarks.on('sortchange', sortEvent);
        sortableMarks.disableSelection();
    }
    else{
        var sortableMarks=$('#marks-parent-'+c_index);
        var sortEvent = function (event, ui){
        };
        sortableMarks.sortable( { 
            items: 'tr:not(:first)',
            stop: sortEvent,
            disabled: true 
        });
        sortableMarks.off('sortchange');
    }
    parent.children().remove();
    parent.append("<tr><td colspan='4'>Loading...</td></tr>");
    ajaxGetGradedComponent(gradeable.id, gradeable.user_id, component.id, function(data) {
        // If nothing has changed, then don't update
        if (!haveMarksChanged(c_index, data)){
            return;
        }
        // Clear away all marks
        parent.children().remove();

        // Custom mark
        {
            let note = data.comment;
            let score = data.score;

            var score_el = $('input[name=mark_points_custom_'+c_index+']');
            var note_el = $('textarea[name=mark_text_custom_'+c_index+']');
            score_el.val(score);
            note_el.val(note);
            var icon = $('i[name=mark_icon_'+c_index+'_custom]');
            icon.toggleClass("mark-has", (note !== "" && note !== undefined));

            getComponent(c_index).score = score;
            getComponent(c_index).comment = note;
        }
        //Clear extra marks
        getComponent(c_index).marks = [];
        
        OPENEDMARKS.splice(0,OPENEDMARKS.length);
        // Add all marks back
        for (var m_index = 0; m_index < data.marks.length; m_index++) {
            let mark = {};
            mark.publish  = data.marks[m_index].publish;
            mark.id       = data.marks[m_index].id;
            mark.has      = data.marks[m_index].has_mark;
            mark.points   = data.marks[m_index].points;
            mark.name     = data.marks[m_index].title;
            mark.order    = data.marks[m_index].order;

            if (mark.id === undefined) {
                continue;
            }

            getComponent(c_index).marks.push(mark);
            
            OPENEDMARKS.push(mark);
            parent.append(getMarkView(c_index, m_index, mark.id, editModeEnabled));
            if((editModeEnabled==null || editModeEnabled==false)){
                var current_mark = $('#mark_id-'+c_index+'-'+mark.id);
                $('#marks-extra-'+c_index)[0].style.display="none";
            }
        }
    });
}

/**
 * Saves the DOM state of a component to the server.  If there are conflicts in mark configs,
 *  those will be indicated TODO
 * @param {int} component_id
 */
function saveComponent(component_id) {

}

/**
 * Fetches the up-to-date overall comment for the graded gradeable and displays it
 */
function updateGeneralComment() {
    ajaxGetGeneralComment(getGradeable().id, getAnonId(), function (data) {
        $('#comment-id-general').val(data);
    });
}

/**
 * Opens the add mark popup
 * @param {int} component_id
 */
function addMark(component_id) {
    // Hide all other (potentially) open popups
    $('.popup-form').css('display', 'none');
    
    // Display and update the popup
    $("#mark-creation-popup").css("display", "block");
    
    $("#mark-creation-popup-points")[0].value = "0";
    $("#mark-creation-popup-note")[0].value = "";
    
    $("#mark-creation-popup-error").css("display", "none");
    
    $("#mark-creation-popup-confirm")[0].onclick = function() {
        let note = $("#mark-creation-popup-note")[0].value;
        let points = parseFloat($("#mark-creation-popup-points")[0].value);
        
        if (!note.trim()) {
            $("#mark-creation-popup-error").css("display", "inherit");
        } else {
            $('#mark-creation-popup').css('display', 'none');

            // TODO: why
            updateCookies();

            ajaxAddNewMark(getGradeable().id, getComponent(component_id).id, note, points, function() {
                updateComponent(component_id);
            });
        }
    };
}

// TODO: Ended here.  A lot of the code can be removed if we go to a model of always
// TODO:    getting the most up-to-date component after modifying the rubric then reconstructing
// TODO:    the UI with the new data.
/**
 * Deletes a mark from the server and from the mark list
 * @param {int} component_id
 * @param {int} mark_id
 */
function deleteMark(component_id, mark_id) {
    ajaxDeleteMark(getGradeable().id, component_id, mark_id, function () {
        removeMarkFromDOM(component_id, mark_id);
    });
}

/**
 * Shows the users that got a mark and some stats about that mark
 * @param {int} component_id
 * @param {int} mark_id
 */
function showMarkStats(component_id, mark_id) {
    ajaxGetMarkStats(getGradeable().id, component_id, mark_id, function (data) {
        // Calculate total and graded component amounts
        let graded = 0, total = 0;
        for (let x in data.sections) {
            graded += parseInt(data.sections[x]['graded_components']);
            total += parseInt(data.sections[x]['total_components']);
        }

        // Set information in the popup
        $("#student-marklist-popup-question-name")[0].innerHTML = $("#component_name-" + component_id).text();
        $("#student-marklist-popup-mark-note")[0].innerHTML = $("textarea[name=mark_text_" + component_id + "_" + mark_id + "]").val();

        $("#student-marklist-popup-student-amount")[0].innerHTML = data.submitter_ids.length;
        $("#student-marklist-popup-graded-components")[0].innerHTML = graded;
        $("#student-marklist-popup-total-components")[0].innerHTML = total;

        // Create list of students
        let students_html = "";
        for (let x = 0; x < data.submitter_ids.length; x++) {
            let id = data.submitter_ids[x];

            let href = window.location.href.replace(/&who_id=([a-z0-9_]*)/, "&who_id=" + id);
            students_html +=
                "<a " + (id != null ? "href='" + href + "'" : "") + ">" +
                id + (x !== data.submitter_ids.length - 1 ? ", " : "") +
                "</a>";
        }

        // Hide all other (potentially) open popups
        $('.popup-form').css('display', 'none');

        // Display and update the popup
        $("#student-marklist-popup").css("display", "block");
        $("#student-marklist-popup-student-names")[0].innerHTML = students_html;
    })
}

/**
 * Checks if the first mark (full/no credit) should be selected, and selects it if it should
 * @param {int} component_id
 * @returns {boolean}
 */
function updateFirstMarkState(component_id) {
    var mark_table = $('#marks-parent-'+component_id);
    var targetId=getComponent(component_id).marks[0].id;
    var first_mark = mark_table.find('span[name=mark_icon_'+component_id+'_'+targetId+']');
    var all_false = true; //ignores the first mark
    mark_table.find('.mark').each(function() {
        if($(this).attr('name') == 'mark_icon_'+component_id+'_0')
        {
            return;
        }
        if($(this)[0].classList.contains('mark-has')) {
            all_false = false;
            return false;
        }
    });
    var current_row = $('#mark_custom_id-'+component_id);
    var custom_message = current_row.find('textarea[name=mark_text_custom_'+component_id+']').val();
    if(custom_message !== "" && custom_message !== undefined){
        all_false = false;
        first_mark.toggleClass("mark-has", false);
        getMark(c_index, targetId).has = false;
        return false;
    }
    if(all_false === false) {
        first_mark.toggleClass("mark-has", false);
        getMark(c_index, targetId).has = false;
    }
}

/**
 * Calculate the number of points a component has with the given selected marks
 * @param {int} component_id
 * @returns {int|null}
 */
function calculateMarksPoints(component_id) {
    let component = getComponentFromDOM(component_id);
    var lower_clamp = component.lower_clamp;
    var current_points = component.default;
    var upper_clamp = component.upper_clamp;
    var arr_length = component.marks.length;
    var any_selected=false;

    for (var m_index = 0; m_index < arr_length; m_index++) {
        var is_selected = false;
        if (component.marks[m_index].has) {
            is_selected = true;
        }
        if (is_selected === true) {
            any_selected = true;
            current_points += component.marks[m_index].points;
        }
    }

    var custom_points = component.score;
    if (component.comment !== "") {
        if (isNaN(custom_points)) {
            current_points += 0;
        } else {
            current_points += custom_points;
            any_selected = true;
        }
    }

    if(any_selected == false){
        return "None Selected";
    }
    if(current_points < lower_clamp) {
        current_points = lower_clamp;
    }
    if(current_points > upper_clamp) {
        current_points = upper_clamp;
    }

    return current_points;
}

/**
 * TODO: most of this should be moved to the DOM-access layer
 * Update the display of a component's score, marks, and background
 * @param c_index 1-indexed component index
 */
function updateProgressPoints(c_index) {
    c_index = parseInt(c_index);
    var current_progress = $('#progress_points-' + c_index);
    var current_points = calculateMarksPoints(c_index);
    var current_question_text = $('#rubric-textarea-' + c_index);
    var component = getComponent(c_index);
    var max_points = parseFloat(component.max_value);
    var summary_text = "Click me to grade!";

    updateBadge($('#gradebar-' + c_index), current_points, max_points);

    if(current_points=="None Selected"){
        $('#summary-' + c_index)[0].style.backgroundColor = "#E9EFEF";
        $('#title-' + c_index)[0].style.backgroundColor = "#E9EFEF";
    }
    else{
        $('#summary-' + c_index)[0].style.backgroundColor = "#F9F9F9";
        $('#title-' + c_index)[0].style.backgroundColor = "#F9F9F9";
        summary_text = "";
        for (var m_index = 0; m_index < component.marks.length; m_index ++) {
            var mark = component.marks[m_index];
            if (mark.has) {
                if (summary_text.length > 0) {
                    summary_text += "<br>";
                }

                var points = mark.points !== 0 ? "(" + mark.points + ") " : "";
                summary_text += "* " + points + escapeHTML(mark.name);
            }
        }
        if (component.comment !== "") {
            var custom_message = escapeHTML(component.comment);
            if (summary_text.length > 0) {
                summary_text += "<br>";
            }

            var points = component.score !== 0 ? "(" + component.score + ") " : "";
            summary_text += "* " + points + custom_message;
        }
    }
    current_question_text.html(summary_text);

    var custom_message = $('textarea[name=mark_text_custom_'+c_index+']').val();
    $('#mark_points_custom-' + c_index)[0].disabled=true;
    $('#mark_points_custom-' + c_index)[0].style.cursor="not-allowed";
    $('#mark_text_custom-' + c_index)[0].style.cursor="not-allowed";
    $('#mark_icon_custom-' + c_index)[0].style.cursor="not-allowed";
    if(!editModeEnabled){
        $('#mark_text_custom-'+c_index)[0].disabled=false;
        $('#mark_text_custom-' + c_index)[0].style.cursor="text";
        if(!custom_message == ""){
            $('#mark_points_custom-' + c_index)[0].disabled=false;
            $('#mark_points_custom-' + c_index)[0].style.cursor="default";
            $('#mark_icon_custom-' + c_index)[0].style.cursor="pointer";
        }  
    }
    else{
        $('#mark_text_custom-'+c_index)[0].disabled=true;
    }
    if(custom_message == ""){
        $('#mark_points_custom-' + c_index)[0].value="0";
    }
    calculatePercentageTotal();
}

/**
 * Update the display of all components' scores, marks, and backgrounds
 */
function updateAllProgressPoints() {
    for (var c_index = 1; c_index <= getGradeable().components.length; c_index ++) {
        updateProgressPoints(c_index);
    }
}

/**
 * Update the Total / Auto-Grading Total labels on the bottom of the form
 */
function calculatePercentageTotal() {
    var gradeable = getGradeable();
    var total = 0;
    var earned = 0;
    var autoTotal = gradeable.total_autograder_non_extra_credit_points;
    var autoEarned = gradeable.graded_autograder_points;


    for (var c_index = 1; c_index <= gradeable.components.length; c_index ++) {
        var component = getComponent(c_index);
        total += component.max_value;

        var points = calculateMarksPoints(c_index);
        if (points !== "None Selected") {
            earned += points;
        }
    }

    total = Math.max(total, 0);

    updateBadge($("#grading_total"), earned, total);
    updateBadge($("#autograding_total"), autoEarned, autoTotal);
    updateBadge($("#score_total"), Math.max((earned + autoEarned),0), (total + autoTotal));
}

/**
 * Update text and color for a grading badge
 * @param badge Badge jQuery element
 * @param current Current point value
 * @param total Total point value
 */
function updateBadge(badge, current, total) {
    if (badge.length === 0) {
        return;
    }

    badge.removeClass("green-background yellow-background red-background");

    if (!isNaN(parseFloat(current))) {
        badge.text(current + " / " + total);
        if (current > total) {
            badge.addClass("green-background");
        } else if (current === total) {
            badge.addClass("green-background");
        } else if (current > 0) {
            badge.addClass("yellow-background");
        } else {
            badge.addClass("red-background");
        }
    } else {
        badge.html("&ndash; / " + total)
    }
}

/**
 * DOM callback for toggling a mark
 * @param {int} component_id
 * @param {int} mark_id
 */
function toggleMark(component_id, mark_id) {
    if(editModeEnabled==true){
        return;
    }
    var skip = true; //if the table is all false initially, skip check marks.
    var mark_table = $('#marks-parent-'+component_id);
    mark_table.find('.mark').each(function() {
        if($(this)[0].classList.contains('mark-has')) {
            skip = false;
            return false;
        }
    });
    
    var mark = getMark(c_index, m_id);
    mark.has = !mark.has;
    var current_row = $('#mark_custom_id-'+c_index);
    var custom_message = current_row.find('textarea[name=mark_text_custom_'+c_index+']').val();
    if(custom_message !== "" && custom_message !== undefined){
        skip = false;
    }
    //actually checks the mark then checks if the first mark is still valid
    var check = $("#mark_id-" + c_index + "-" + m_id + "-check");
    check.toggleClass("mark-has", mark.has);
    if (skip === false) {
        updateFirstMarkState(component_id);
    }

    //updates the progress points in the title
    updateProgressPoints(c_index);
}

/**
 * Loads a component from the server and displays it as open on the page
 * @param {int} component_id
 */
function openComponent(component_id) {
    var row_num = parseInt(c_index);
    var total_num = getGradeable().components.length;

    //-2 means general comment, else open the c_index with the number
    var general_comment = $('#extra-general');
    setGeneralVisible(row_num === GENERAL_MESSAGE_ID && general_comment[0].style.display === 'none');

    for (var x = 1; x <= total_num; x++) {
        var current_summary = $('#summary-' + x);
        setMarkVisible(x, x === row_num && current_summary[0].style.display === '');
    }

    updateCookies();
}

/**
 * Closes the provided component
 * @param {int} component_id
 */
function closeComponent(component_id) {

}

/**
 * Closes all open components and the comment
 */
function closeAll() {

}

/**
 * TODO: wtf.  I think this has some work for closing/opening components
 * Set if a component should be visible
 * @param c_index 1-indexed component index
 * @param show If the component should be visible
 */
function EVILsetMarkVisible(c_index, show) {
    var page = ($('#page-' + c_index)[0]).innerHTML;

    var title           = $('#title-' + c_index);
 //   var cancel_button   = $('#title-cancel-' + c_index);
    var current_summary = $('#summary-' + c_index);
    // Deprecated, saving for reference
    if (show) {
        // if the component has a page saved, open the PDF to that page
        // opening directories/frames based off of code in openDiv and openFrame functions

        // make sure submissions folder has files
        var submissions = $('#div_viewer_1');
        if (page > 0 && submissions.children().length > 0) {

            // find the first file that is a PDF
            var divs = $('#div_viewer_1 > div > div');
            var pdf_div = "";
            for (var i=0; i<divs.length; i++) {
                if ($(divs[i]).is('[data-file_url]')) {
                    file_url = $(divs[i]).attr("data-file_url");
                    if(file_url.substring(file_url.length - 3) == "pdf") {
                        pdf_div = $($(divs[i]));
                        break;
                    }
                }
            }

            // only open submissions folder + PDF is a PDF file exists within the submissions folder
            if (pdf_div != "") {
                submissions.show();
                submissions.addClass('open');
                $($($(submissions.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder').addClass('fa-folder-open');

                var file_url = pdf_div.attr("data-file_url");
                var file_name = pdf_div.attr("data-file_name");
                if (!pdf_div.hasClass('open')) {
                    openFrame(file_name,file_url,pdf_div.attr("id").substring(pdf_div.attr("id").lastIndexOf("_")+1));
                }
                var iframeId = pdf_div.attr("id") + "_iframe";
                var directory = "submissions";
                var src = $("#"+iframeId).prop('src');
                if (src.indexOf("#page=") === -1) {
                    src = src + "#page=" + page;
                }
                else {
                    src = src.slice(0,src.indexOf("#page=")) + "#page=" + page;
                }
                pdf_div.html("<iframe id='" + iframeId + "' src='" + src + "' width='95%' height='1200px' style='border: 0'></iframe>");

                if (!pdf_div.hasClass('open')) {
                    pdf_div.addClass('open');
                }
                if (!pdf_div.hasClass('shown')) {
                    pdf_div.show();
                    pdf_div.addClass('shown');
                }
            }
        }
    }

    // Updated all the background colors and displays of each element that has
    //  the corresponding data tag
    // $("[id$='-"+c_index+"'][data-changebg='true']")      .css("background-color", (show ? "#e6e6e6" : "initial"));
    $("[id$='-"+c_index+"'][data-changedisplay1='true']").css("display",          (show ? "" : "none"));
    $("[id$='-"+c_index+"'][data-changedisplay2='true']").css("display",          (show ? "none" : ""));

    title.attr('colspan', (show ? 3 : 4));
  //  cancel_button.attr('colspan', (show ? 1 : 0));
}

/**
 * Set if the overall comment box should be visible
 * @param gshow If it should be visible
 */
function setGeneralVisible(gshow) {
    var general_comment = $('#extra-general');
    var general_comment_title = $('#title-general');
    var general_comment_title_cancel = $('#title-cancel-general');

    // Updated all the background colors and displays of each element that has
    //  the corresponding data tag for the general component
    // $("[id$='-general'][data-changebg='true']")      .css("background-color", (gshow ? "#e6e6e6" : "initial"));
    $("[id$='-general'][data-changedisplay1='true']").css("display",          (gshow ? "" : "none"));
    $("[id$='-general'][data-changedisplay2='true']").css("display",          (gshow ? "none" : ""));

    general_comment_title.attr('colspan', (gshow ? 3 : 4));
    general_comment_title_cancel.attr('colspan', (gshow ? 1 : 0));

    updateCookies();
}

/**
 * Saves the overall comment for this graded gradeable
 * @param async
 * @param successCallback
 * @param errorCallback
 */
function saveOverallComment(async = true, successCallback = undefined, errorCallback = undefined) {
    var gradeable = getGradeable();

    if ($('#extra-general')[0].style.display === "none") {
        //Nothing to save so we are fine
        if (typeof(successCallback) === "function") {
            successCallback();
        }
        return;
    }
    
    var comment_row = $('#comment-id-general');
    var gradeable_comment = comment_row.val();
    var current_question_text = $('#rubric-textarea-custom');
    var overwrite = $('#overwrite-id').is(":checked");
    $(current_question_text[0]).text(gradeable_comment);
    
    ajaxSaveOveralComment(gradeable.id, gradeable.user_id, gradeable_comment, async, successCallback, errorCallback);
}

/**
 * Saves the currently opened component(s) and the overall comment to the server
 * @param {boolean} async
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function saveGradedGradeable(async, successCallback, errorCallback) {
    var gradeable = getGradeable();

    // Find open mark
    var index = 1;
    var mark = $('#marks-parent-' + index);
    while(mark.length > 0) {
        // If mark is open, then save it
        if (mark[0].style.display !== 'none') {
            var gradeable_component_id = getComponent(index).id;
            saveMark(index, sync, successCallback, errorCallback);
            return;
        }
        mark = $('#marks-parent-' + (++index));
    }
    // If no open mark was found, then save general comment
    saveGeneralComment(sync, successCallback, errorCallback);
}

/**
 * Saves a graded component to the server
 * @param {int} component_id
 * @param {boolean} async
 * @param override_grader
 * @param {function|undefined} successCallback
 * @param {function|undefined} errorCallback
 */
function saveGradedComponent(component_id, async, override_grader, successCallback, errorCallback) {
    var gradeable = grading_data.gradeable;
    if(editModeEnabled){
        ajaxGetGradedComponent(gradeable.id, gradeable.user_id, gradeable.components[c_index-1].id , function(data){
        saveMarkEditMode(c_index, sync, successCallback, errorCallback, data, override);
       }); 
        return;
    }
    else{

        if ($('#marks-parent-' + c_index)[0].style.display === "none") {
            //Nothing to save so we are fine
            if (typeof(successCallback) === "function") {
                successCallback();
            }
            return;
        }
        
        var arr_length = $('tr[name=mark_'+c_index+']').length;
        
        var mark_data = new Array(arr_length);
        var existing_marks_num = 0;
        var gradedByElement = $('#graded-by-' + c_index);
        var savingElement = $('#graded-saving-' + c_index);
        var ungraded = gradedByElement.text() === "Ungraded!";
        gradedByElement.hide();
        var current_row = $('#mark_custom_id-'+c_index);

        var current_title = $('#title-' + c_index);
        var custom_points  = current_row.find('input[name=mark_points_custom_'+c_index+']').val();
        var custom_message = current_row.find('textarea[name=mark_text_custom_'+c_index+']').val();
        if(custom_message === "" || custom_message == undefined){
            custom_points="0";
        }
        // Updates the total number of points and text
        var current_question_text = $('#rubric-textarea-' + c_index);
        var component = getComponent(c_index);
        
        var arr_length = mark_data.length;
        var mark_ids = [];
        for (var m_index = 0; m_index < arr_length; m_index++) {
            var current_mark_id=grading_data.gradeable.components[c_index-1].marks[m_index].id;
            if ((getMark(c_index, current_mark_id).has) == true) {
                mark_ids.push(current_mark_id);
            }                
        }
        if(custom_message != "") {
            custom_message = escapeHTML(custom_message);
        }
        var overwrite = ($('#overwrite-id').is(':checked')) ? ("true") : ("false");
        ajaxSaveGradedComponent(gradeable.id, gradeable.components[c_index-1].id, gradeable.user_id, gradeable.active_version, custom_points, custom_message, overwrite, mark_ids, true, function(response) {
            var hasGrade = false;
            for (var m_index = 0; m_index < arr_length; m_index++) {
                var current_mark_id=grading_data.gradeable.components[c_index-1].marks[m_index].id;
                if ((getMark(c_index, current_mark_id).has) == true) {
                    hasGrade = true;
                }                
            }
            custom_message = current_row.find('textarea[name=mark_text_custom_'+c_index+']').val();
            if (hasGrade === false && (custom_message === "" || custom_message == undefined)) {
                gradedByElement.text("Ungraded!");
                component.grader = null;
            } 
            else {
                component.grader = grading_data.you;
                component.grader.id = grading_data.your_user_id;
                gradedByElement.text("Graded by " + component.grader.id + "!");
            }
                //Just graded it
            gradedByElement.show();
            savingElement.hide();
            if (typeof(successCallback) === "function")
                successCallback(response);                           
        }, errorCallback ? errorCallback : function() {
            console.error("Something went wront with saving marks...");
       //     alert("There was an error with saving the grade. Please refresh the page and try agian.");
        });
    }
    calculateMarksPoints(c_index);
}

/**
 * Saves a component's marks to the server, checking for mark conflicts
 * @param c_index
 * @param async
 * @param successCallback
 * @param errorCallback
 * @param data
 * @param override
 */
function saveComponentMarks(c_index, async, successCallback, errorCallback, data, override){
    var gradeable = getGradeable();
    var component = grading_data.gradeable.components[c_index-1];
    var arr_length = data['marks'].length;
    var orderArray = {};
    var mark_data = new Array(arr_length);
    var existing_marks_num = 0;
    // Gathers all the mark's data (ex. points, note, etc.)
    for(var m_index=0; m_index < arr_length; m_index++){
        var current_question_text = $('#rubric-textarea-' + c_index);
        if(grading_data.gradeable.components[c_index-1].marks[m_index]==null){
            grading_data.gradeable.components[c_index-1].marks.splice(m_index, 1);
        }
        else{
            for(var m_index2=0; m_index2 < arr_length; m_index2++){
                var x=data['marks'][arr_length-m_index2-1]['id'];
            }
            var y=grading_data.gradeable.components[c_index-1].marks;
            var current_mark_id=data['marks'][arr_length-m_index-1]['id'];
            var current_row = $('#mark_id-'+c_index+'-'+getMark(c_index, current_mark_id).id);
            var info_mark   = $('#mark_info_id-'+c_index+'-'+getMark(c_index, current_mark_id).id);
            var success     = true;
            var DB_m_id       = data['marks'][arr_length-m_index-1]['id'];
            var DB_score      = data['marks'][arr_length-m_index-1]['points'];
            var DB_note       = data['marks'][arr_length-m_index-1]['title'];
            var DB_order      = data['marks'][arr_length-m_index-1]['order'];
            var id = getMark(c_index, current_mark_id).id;
            var points = getMark(c_index, current_mark_id).points;
            var note = getMark(c_index, current_mark_id).name;
            var selected = getMark(c_index, current_mark_id).has;
            var order = getMark(c_index, current_mark_id).order;
            var DBvsOR = true;
            var ORvsYours = true;
            if(arr_length - m_index - 1 < OPENEDMARKS.length){
                DBvsOR = (DB_m_id === OPENEDMARKS[arr_length - m_index - 1].id && (DB_score !== OPENEDMARKS[arr_length - m_index - 1].points || DB_note !== OPENEDMARKS[arr_length - m_index - 1].name || DB_order !== OPENEDMARKS[arr_length - m_index - 1].order));  
                ORvsYours = (id === OPENEDMARKS[arr_length - m_index - 1].id && (points !== OPENEDMARKS[arr_length - m_index - 1].points || note !== OPENEDMARKS[arr_length - m_index - 1].name || order !== OPENEDMARKS[arr_length - m_index - 1].order));              
            }
            var DBvsYours = (DB_m_id === id && (DB_score !== points || DB_note !== note || DB_order !== order));
            if(DBvsOR && DBvsYours && ORvsYours){
                //CONFLICT!
                if(confirm("There was a conflict saving the mark you call "+ note +" (another user changed this mark while you were editing). Would you like your changes to overwrite the other users?")){
                    calculatePercentageTotal();
                    var gradedByElement = $('#graded-by-' + c_index);
                    var savingElement = $('#graded-saving-' + c_index);
                    var ungraded = gradedByElement.text() === "Ungraded!";
                    var x=getMark(c_index, current_mark_id).points;
                    gradedByElement.hide();
                    savingElement.show();
                    ajaxSaveMark(gradeable.id, gradeable.components[c_index-1].id, getMark(c_index, current_mark_id).id, getMark(c_index, current_mark_id).points, escapeHTML(getMark(c_index, current_mark_id).name), true, function(response) {
                    if (response.status !== 'success') {
                            alert('Error saving marks! (' + response.message + ')');
                            return;
                        }
                        if (gradeable.components[c_index-1].has_grade === false) {
                            gradedByElement.text("Ungraded!");
                            component.grader = null;
                        } 
                        else {
                            component.grader.id = grading_data.your_user_id;
                            gradedByElement.text("Graded by " + component.grader.id + "!");
                        }
                            //Just graded it
                        gradedByElement.show();
                        savingElement.hide();
                        if (typeof(successCallback) === "function")
                            successCallback(data);
                            
                    }, errorCallback ? errorCallback : function() {
                        console.error("Something went wront with saving marks...");
             //           alert("There was an error with saving the grade. Please refresh the page and try agian.");
                    });
                }
            }
            else if ((DBvsYours && DBvsOR) || override){
                calculatePercentageTotal();
                    var gradedByElement = $('#graded-by-' + c_index);
                    var savingElement = $('#graded-saving-' + c_index);
                    var ungraded = gradedByElement.text() === "Ungraded!";
                    gradedByElement.hide();
                    savingElement.show();
                    ajaxSaveMark(gradeable.id, gradeable.components[c_index-1].id, getMark(c_index, current_mark_id).id, getMark(c_index, current_mark_id).points, escapeHTML(getMark(c_index, current_mark_id).name), true, function(response) {
                        if (gradeable.components[c_index-1].has_grade === false) {
                            gradedByElement.text("Ungraded!");
                            component.grader = null;
                        } 
                        else {
                            component.grader.id = grading_data.your_user_id;
                            gradedByElement.text("Graded by " + component.grader.id + "!");
                        }
                            //Just graded it
                        gradedByElement.show();
                        savingElement.hide();
                        if (typeof(successCallback) === "function")
                            successCallback(data);                           
                    }, errorCallback ? errorCallback : function() {
                        console.error("Something went wront with saving marks...");
               //         alert("There was an error with saving the grade. Please refresh the page and try agian.");
                    });
            }
            existing_marks_num++;
        }
        orderArray[getMark(c_index, current_mark_id).id]=getMark(c_index, current_mark_id).order;
    }
    var gradedByElement = $('#graded-by-' + c_index);
    var savingElement = $('#graded-saving-' + c_index);
    var ungraded = gradedByElement.text() === "Ungraded!";
   // gradedByElement.hide();
    var current_row = $('#mark_custom_id-'+c_index);

    var current_title = $('#title-' + c_index);
    var custom_points  = current_row.find('input[name=mark_points_custom_'+c_index+']').val();
    if (typeof custom_points != 'number') {
        custom_points=0;
    }
    var custom_message = current_row.find('textarea[name=mark_text_custom_'+c_index+']').val();

    // Updates the total number of points and text
    var current_question_text = $('#rubric-textarea-' + c_index);
    var component = getComponent(c_index);
    
    var arr_length = mark_data.length;
    var mark_ids = [];
    for (var m_index = 0; m_index < arr_length; m_index++) {
        var current_mark_id=grading_data.gradeable.components[c_index-1].marks[m_index].id;
        if ((getMark(c_index, current_mark_id).has) == true) {
            mark_ids.push(current_mark_id);
        }                
    }
    if(custom_message != "") {
        custom_message = escapeHTML(custom_message);
    }
    var overwrite = ($('#overwrite-id').is(':checked')) ? ("true") : ("false");
    calculateMarksPoints(c_index);
    //TODO save the mark order
   /* ajaxSaveMarkOrder(gradeable.id, gradeable.components[c_index-1].id, orderArray, true, function(data){
        console.log("data");
    }); */
    calculateMarksPoints(c_index);
}

function updateVerifiedStates() {

    // If no components to be verified
    document.getElementById("verifyAllButton").style.display = "none";
}

function verifyComponent(component_id) {

}

function verifyAllComponents() {

}

/**
 * Gets an array of ids of all open components
 * @returns {array}
 */
function getOpenComponentIds() {
    if ($('#grading_rubric').length === 0 || $('#summary-general').length === 0) {
        return NO_COMPONENT_ID;
    }
    if($('#grading_rubric').hasClass('empty')) {
        return -3;
    }
    var index = 1;
    var found = false;
    var doesExist = ($('#summary-' + index).length) ? true : false;
    while(doesExist) {
        if($('#summary-' + index).length) {
            if ($('#summary-' + index)[0].style.display === 'none') {
                found = true;
                doesExist = false;
                index--;
            }
        }
        else{
            doesExist = false;
        }
        index++;
    }
    if (found === true) {
        return index;
    } else {
        if ($('#summary-general')[0].style.display === 'none') {
            return GENERAL_MESSAGE_ID;
        } else {
            return NO_COMPONENT_ID;
        }
    }
}

/**
 * Gets if the overall comment box is open
 * @returns {boolean}
 */
function isOverallCommentOpen() {

}

/**
 * DOM callback to open/close a component
 * @param {int} component_id
 * @param {boolean} save If changes made to the component should be saved
 */
function toggleComponentOpen(component_id, save) {
    if (findCurrentOpenedMark() === id) {
        if(save) {
            saveComponent(component_id);
        }
        closeComponent(component_id);
        updateCookies();
    } else {
        openComponent(component_id);
    }
}

/**
 * Open the general message input (if it's not open already), saving changes on any previous mark
 */
function openOverallComment() {
    saveLastOpenedMark(true);
    saveGeneralComment(true);

    //If it's already open, then openClose() will close it
    if (findCurrentOpenedMark() !== GENERAL_MESSAGE_ID) {
        openClose(GENERAL_MESSAGE_ID);
    }
}

/**
 * Close the general message input (if it's open), optionally saving changes
 * @param save If changes should be saved
 */
function closeOverallComment(save) {
    if (save) {
        saveOverallComment();
    } else {
        updateGeneralComment();
    }
    setGeneralVisible(false);
}

/**
 * Toggle if the overall comment should be visible
 * @param {boolean} save If changes should be saved
 */
function toggleOverallComment(save) {
    if (isOverallCommentOpen()) {
        closeOverallComment(save);
    } else {
        openOverallComment();
    }
}

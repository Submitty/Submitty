/// When no component is selected, the current ID will be this value
NO_COMPONENT_ID = -1;

/// Component ID of the "General Comment" box at the bottom
GENERAL_MESSAGE_ID = -2;

/**
 * Get the page-wide Gradeable object (see Gradeable.php/getGradedData())
 * @returns Object Gradeable data
 */
function getGradeable() {
    return grading_data.gradeable;
}

/**
 * Get a specific component in the global Gradeable (see GradeableComponent.php/getGradedData())
 * @param c_index 1-indexed component index
 * @returns Object Component data
 */
function getComponent(c_index) {
    return grading_data.gradeable.components[c_index - 1];
}

/**
 * Get a specific mark in a mark in the global Gradeable (see GradeableComponentMark.php/getGradeData())
 * @param c_index 1-indexed component index
 * @param m_index 0-indexed mark index
 * @returns Object Mark data
 */
function getMark(c_index, m_index) {
    return grading_data.gradeable.components[c_index - 1].marks[m_index];
}

/**
 * DOM callback for changing the number of points for a mark
 * @param me DOM Element for the mark points entry
 */
function updateMarkPoints(me) {
    getMark(me.dataset.component_index, me.dataset.mark_index).points = parseFloat($(me).val());
    updateProgressPoints(me.dataset.component_index);
}

/**
 * DOM callback for changing the note for a mark
 * @param me DOM Element for the mark note entry
 */
function updateMarkText(me) {
    getMark(me.dataset.component_index, me.dataset.mark_index).name = $(me).val();
    updateProgressPoints(me.dataset.component_index);
}

/**
 * DOM callback for changing the number of points for a common mark
 * @param me DOM Element for the common mark points entry
 */
function updateCustomMarkPoints(me) {
    var component = getComponent(me.dataset.component_index);
    var val = $(me).val();
    component.score = parseFloat(val);
    updateProgressPoints(me.dataset.component_index);
}

/**
 * DOM callback for changing the note for a common mark
 * @param me DOM Element for the common mark note entry
 */
function updateCustomMarkText(me) {
    var component = getComponent(me.dataset.component_index);
    var val = $(me).val();
    component.comment = val;

    //If we set custom mark to empty then we're clearing it. So unset the point value too.
    if (val === "") {
        component.score = 0;
    }

    updateProgressPoints(me.dataset.component_index);
}

//if type == 0 number input, type == 1 textarea
function checkIfSelected(me) {
    var table_row = $(me.parentElement.parentElement);
    var is_selected = false;
    var icon = table_row.find("i");
    var number_input = table_row.find("input");
    var text_input = table_row.find("textarea");
    var question_num = parseInt(icon.attr('name').split('_')[2]);

    if(number_input.val() != 0 || text_input.val() != "") {
        is_selected = true;
    }

    if (is_selected === true) {
        if(icon[0].classList.contains('fa-square-o')) {
            icon.toggleClass("fa-square-o fa-square");
        }
    } else {
        if(icon[0].classList.contains('fa-square')) {
            icon.toggleClass("fa-square-o fa-square");
        }
    }

    checkMarks(question_num);
}

/**
 * Render and return a view for the given mark
 * @param c_index 1-indexed component index of the mark
 * @param m_index 0-indexed mark index
 * @returns DOM structure for the mark
 */
function getMarkView(c_index, m_index, m_id) {
    return Twig.twig({ref: "Mark"}).render({
        gradeable: getGradeable(),
        component: getComponent(c_index),
        mark: getMark(c_index, m_index),
        c_index: c_index,
        m_index: m_index,
        m_id: m_id
    });
}

function ajaxGetMarkData(gradeable_id, user_id, question_id, successCallback, errorCallback) {
    $.ajax({
            type: "POST",
            url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'get_mark_data'}),
            data: {
                'gradeable_id' : gradeable_id,
                'anon_id' : user_id,
                'gradeable_component_id' : question_id,
            },
            success: function(data) {
                if (typeof(successCallback) === "function") {
                    successCallback(data);
                }
            },
            error: (typeof(errorCallback) === "function") ? errorCallback : function(err) {
                console.error("Something went wront with fetching marks!");
                alert("There was an error with fetching marks. Please refresh the page and try agian.");
            }
    })
}

function ajaxGetGeneralCommentData(gradeable_id, user_id, successCallback, errorCallback) {
    $.ajax({
        type: "POST",
        url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'get_gradeable_comment'}),
        data: {
            'gradeable_id' : gradeable_id,
            'anon_id' : user_id
        },
        success: function(data) {
            if (typeof(successCallback) === "function") {
                successCallback(data);
            }
        },
        error: (typeof(errorCallback) === "function") ? errorCallback : function() {
            console.error("Couldn't get the general gradeable comment");
            alert("Failed to retrieve the general comment");
        }
    })
}

function ajaxAddNewMark(gradeable_id, user_id, question_id, note, points, sync, successCallback, errorCallback) {
    note = (note ? note : "");
    points = (points ? points : 0);
    if (!note.trim())
        console.error("Shouldn't add blank mark!");
    
    $.ajax({
            type: "POST",
            url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'add_one_new_mark'}),
            async: sync,
            data: {
                'gradeable_id' : gradeable_id,
                'anon_id' : user_id,
                'gradeable_component_id' : question_id,
                'note' : note,
                'points' : points
            },
            success: function(data) {
                if (typeof(successCallback) === "function") {
                    successCallback(data);
                }
            },
            error: (typeof(errorCallback) === "function") ? errorCallback : function() {
                console.error("Something went wrong with adding a mark...");
                alert("There was an error with adding a mark. Please refresh the page and try agian.");
            }
        })
}

function ajaxGetMarkedUsers(gradeable_id, gradeable_component_id, order_num, successCallback, errorCallback) {
    $.ajax({
        type: "POST",
        url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'get_marked_users'}),
        data: {
            'gradeable_id' : gradeable_id,
            'gradeable_component_id' : gradeable_component_id,
            'order_num' : order_num
        },
        success: function(data) {
            if (typeof(successCallback) === "function") {
                successCallback(data);
            }
        },
        error: (typeof(errorCallback) === "function") ? errorCallback : function() {
            console.error("Couldn't get the information on marks");
        }
    })
}

function ajaxSaveGeneralComment(gradeable_id, user_id, active_version, gradeable_comment, sync, successCallback, errorCallback) {
    $.ajax({
        type: "POST",
        url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'save_general_comment'}),
        async: sync,
        data: {
            'gradeable_id' : gradeable_id,
            'anon_id' : user_id,
            'active_version' : active_version,
            'gradeable_comment' : gradeable_comment
        },
        success: function(data) {
            if (typeof(successCallback) === "function") {
                successCallback(data);
            }
        },
        error: (typeof(errorCallback) === "function") ? errorCallback : function() {
            console.error("There was an error with saving the general gradeable comment.");
            alert("There was an error with saving the comment. Please refresh the page and try agian.");
        }
    })
}

function ajaxSaveMarks(gradeable_id, user_id, gradeable_component_id, num_mark, active_version, custom_points, custom_message, overwrite, marks, num_existing_marks, sync, successCallback, errorCallback) {
    $.ajax({
        type: "POST",
        url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'save_one_component'}),
        async: sync,
        data: {
            'gradeable_id' : gradeable_id,
            'anon_id' : user_id,
            'gradeable_component_id' : gradeable_component_id,
            'num_mark' : num_mark,
            'active_version' : active_version,
            'custom_points' : custom_points,
            'custom_message' : custom_message,
            'overwrite' : overwrite,
            'marks' : marks,
            'num_existing_marks' : num_existing_marks,
        },
        success: function(data) {
            if (typeof(successCallback) === "function") {
                successCallback(data);
            }
        },
        error: errorCallback
    })
}

function haveMarksChanged(c_index, data) {
    var marks = $('[name=mark_'+c_index+']');
    var mark_notes = $('[name^=mark_text_'+c_index+']');
    var mark_scores = $('[name^=mark_points_'+c_index+']');
    var custom_mark_points = $('input[name=mark_points_custom_'+c_index+']');
    var custom_mark_text = $('textarea[name=mark_text_custom_'+c_index+']');

    // Check if there were added/removed marks
    //    data['data'].length-1 to account for custom mark
    if (data['data'].length-1 != marks.length)
        return true;

    // Check to see if any note or score value is different
    for (var x = 0; x < marks.length; x++) {
        if (mark_notes[x].innerHTML != data['data'][x]['note'] ||
              mark_scores[x].value != data['data'][x]['score'])
            return true;
    }
    
    // Check to see if custom mark changed
    if (data['data'][marks.length]['custom_note'] != custom_mark_text.val())
        return true;
    if (data['data'][marks.length]['custom_score'] != custom_mark_points.val())
        return true;

    // We always have a custom mark, so if length is 1 we have no common marks.
    // This is Very Bad because there should always be at least the No Credit mark.
    // Only thing we can do from here though is let requests go.
    if (data['data'].length === 1) {
        return true;
    }

    return false;
}

/**
 * Reload marks for a component and render them in the list
 * @param num 1-indexed component index
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
function updateMarksOnPage(c_index) {
    var gradeable = getGradeable();
    var component = getComponent(c_index);
    var parent = $('#marks-parent-'+c_index);
    var points = calculateMarksPoints(c_index);
    if(editModeEnabled==true){
        var sortableMarks=$('#marks-parent-'+c_index);
        var sortEvent = function (event, ui){
            sortableMarks.on("sortchange", sortEvent);
            var rows=sortableMarks.children(); 
            var listValues = [];
            for(var i=0; i<rows.length; i++){
                var row=rows[i];
                var id=row.id;
                if(row.dataset.mark_index!=undefined){
                    getMark(c_index, row.dataset.mark_index).order=i;
                }
            }
            // getComponent(c_index).marks.sort(compareOrder);
        };
        sortableMarks.sortable( { 
            items: '> tr:not(:first)',
            stop: sortEvent,
            disabled: false
        });
        sortableMarks.disableSelection();
    }
    else{
        var sortableMarks=$('#marks-parent-'+c_index);
        var sortEvent = function (event, ui){
        };
        sortableMarks.sortable( { 
            items: '> tr:not(:first)',
            stop: sortEvent,
            disabled: true 
        });
    }
    parent.children().remove();
    parent.append("<tr><td colspan='4'>Loading...</td></tr>");
    ajaxGetMarkData(gradeable.id, gradeable.user_id, component.id, function(data) {
        data = JSON.parse(data);

        // If nothing has changed, then don't update
        if (!haveMarksChanged(c_index, data)){
            return;
        }
        // Clear away all marks
        parent.children().remove();

        // Custom mark
        {
            var x = data['data'].length-1;
            var score = data['data'][x]['custom_score'];
            var note  = data['data'][x]['custom_note'];
            
            var score_el = $('input[name=mark_points_custom_'+c_index+']');
            var note_el = $('textarea[name=mark_text_custom_'+c_index+']');
            score_el.val(parseFloat(score));
            note_el.val(note);
            var icon = $('i[name=mark_icon_'+c_index+'_custom]');
            if ((note != "" && note != undefined) && icon[0].classList.contains('fa-square-o') ||
                 (note == "" || note == undefined) && icon[0].classList.contains('fa-square')) {
                     icon.toggleClass("fa-square-o fa-square");
            }

            getComponent(c_index).score = score;
            getComponent(c_index).comment = note;
        }
        
        // Add all marks back
        // data['data'].length - 2 to ignore the custom mark
        for (var m_index = data['data'].length-2; m_index >= 0; m_index--) {
            var is_publish = data['data'][m_index]['is_publish'] == 't';
            var id         = data['data'][m_index]['id'];
            var hasMark    = data['data'][m_index]['has_mark'];
            var score      = data['data'][m_index]['score'];
            var note       = data['data'][m_index]['note'];
            getMark(c_index, m_index).id = id;
            getMark(c_index, m_index).publish = is_publish;
            getMark(c_index, m_index).has = hasMark;
            getMark(c_index, m_index).score = score;
            getMark(c_index, m_index).name = note;
            parent.prepend(getMarkView(c_index, m_index, id));
            if((editModeEnabled==null || editModeEnabled==false)){
                var current_mark = $('#mark_id-'+c_index+'-'+id);
                current_mark.find('input[name=mark_points_'+c_index+'_'+id+']').attr('disabled', true);
                current_mark.find('textarea[name=mark_text_'+c_index+'_'+id+']').attr('disabled', true);
                if(points == "None Selected"){
                    current_mark.find('textarea[name=mark_text_'+c_index+'_'+id+']').attr('style', "width:90%; resize:none; cursor: default; border:none; outline: none; background-color: #E9EFEF");
                    current_mark.find('input[name=mark_points_'+c_index+'_'+id+']').attr('style', "width:50%; resize:none; cursor: default; border:none; outline: none; background-color: #E9EFEF");
                }
                else{
                    current_mark.find('textarea[name=mark_text_'+c_index+'_'+id+']').attr('style', "width:90%; resize:none; cursor: default; border:none; outline: none; background-color: #f9f9f9");
                    current_mark.find('input[name=mark_points_'+c_index+'_'+id+']').attr('style', "width:50%; resize:none; cursor: default; border:none; outline: none; background-color: #f9f9f9");
                }
            }
            /*
            else{
                if(points == "None Selected"){
                    current_mark.find('textarea[name=mark_text_'+c_index+'_'+id+']').attr('style', "width:90%; resize:none; border:none; outline: none; background-color: #E9EFEF");
                    current_mark.find('input[name=mark_points_'+c_index+'_'+id+']').attr('style', "width:50%; resize:none; border:none; outline: none; background-color: #E9EFEF");
                }
                else{
                    current_mark.find('textarea[name=mark_text_'+c_index+'_'+id+']').attr('style', "width:90%; resize:none; border:none; outline: none; background-color: #f9f9f9");
                    current_mark.find('input[name=mark_points_'+c_index+'_'+id+']').attr('style', "width:50%; resize:none; border:none; outline: none; background-color: #f9f9f9");
                }
            }
            */
        }
    });
}


function updateGeneralComment() {
    var gradeable = getGradeable();
    ajaxGetGeneralCommentData(gradeable.id, gradeable.user_id, function(data) {
        data = JSON.parse(data);
        
        $('#comment-id-general').val(data['data']);
    });
}

function addMark(me, num) {
    // Hide all other (potentially) open popups
    $('.popup-form').css('display', 'none');
    
    // Display and update the popup
    $("#mark-creation-popup").css("display", "block");
    
    $("#mark-creation-popup-points")[0].value = "0";
    $("#mark-creation-popup-note")[0].value = "";
    
    $("#mark-creation-popup-error").css("display", "none");
    
    $("#mark-creation-popup-confirm")[0].onclick = function() {
        var note = $("#mark-creation-popup-note")[0].value;
        var points = parseFloat($("#mark-creation-popup-points")[0].value);
        
        if (!note.trim()) {
            $("#mark-creation-popup-error").css("display", "inherit");
        } else {
            $('#mark-creation-popup').css('display', 'none');
            
            var parent = $('#marks-parent-'+num);
            var x      = $('tr[name=mark_'+num+']').length;

            getComponent(num).marks.push({
                name: note,
                points: points,
                publish: false,
                has: false
            });

            parent.append(getMarkView(num, x, -1));

            
            // Add new mark and then update
            // ajaxAddNewMark(gradeable_id, user_id, question_id, note, points, function() {
            //     updateMarksOnPage(num, background, min, max, precision, gradeable_id, user_id, get_active_version, question_id, your_user_id);
            // });
        }
    };
}

// TODO: this
function deleteMark(me, c_index, last_num) {
    var current_row = $(me.parentElement.parentElement);
    current_row.remove();
    var last_row = $('[name=mark_'+c_index+']').last().attr('id');
    var totalD = -1;
    if (last_row == null) {
        totalD = -1;
    } 
    else {
        totalD = parseInt($('[name=mark_'+c_index+']').last().attr('id').split('-')[2]);
    }

    //updates the remaining marks's info
    var current_num = parseInt(last_num);
    for (var m_index = current_num + 1; m_index <= totalD; m_index++) {
        var new_num = m_index-1;
        var current_mark = $('#mark_id-'+c_index+'-'+m_index);
        current_mark.find('input[name=mark_points_'+c_index+'_'+m_index+']').attr('name', 'mark_points_'+c_index+'_'+new_num);
        current_mark.find('textarea[name=mark_text_'+c_index+'_'+m_index+']').attr('name', 'mark_text_'+c_index+'_'+new_num);
        current_mark.find('i[name=mark_icon_'+c_index+'_'+m_index+']').attr('name', 'mark_icon_'+c_index+'_'+new_num);
        current_mark.find('span[id=mark_info_id-'+c_index+'-'+m_index+']').attr('id', 'mark_info_id-'+c_index+'-'+new_num);
        current_mark.attr('id', 'mark_id-'+c_index+'-'+new_num);
    }
}

// gets all the information from the database to return some stats and a list of students with that mark
function showMarklist(me) {
    var gradeable = getGradeable();

    var question_num = parseInt($(me).attr('id').split('-')[1]);
    var order_num = parseInt($(me).attr('id').split('-')[2]);
    var gradeable_component_id = $('#marks-parent-' + question_num)[0].dataset.question_id;
    
    ajaxGetMarkedUsers(gradeable.id, gradeable_component_id, order_num, function(data) {
        data = JSON.parse(data);

        // Calculate total and graded component amounts
        var graded = 0, total = 0;
        for (var x in data['sections']) {
            graded += parseInt(data['sections'][x]['graded_components']);
            total += parseInt(data['sections'][x]['total_components']);
        }

        // Set information in the popup
        $("#student-marklist-popup-question-name")[0].innerHTML = data['name_info']['question_name'];
        $("#student-marklist-popup-mark-note")[0].innerHTML = data['name_info']['mark_note'];
        
        $("#student-marklist-popup-student-amount")[0].innerHTML = data['data'].length;
        $("#student-marklist-popup-graded-components")[0].innerHTML = graded;
        $("#student-marklist-popup-total-components")[0].innerHTML = total;
        
        // Create list of students
        var students_html = "";
        for (var x = 0; x < data['data'].length; x++) {
            // New line every 5 names
            if (x % 5 == 0)
                students_html += "<br>";

            var id = data['data'][x]['gd_user_id'] || data['data'][x]['gd_team_id'];

            var href = window.location.href.replace(/&who_id=([a-z0-9_]*)/, "&who_id="+id);
            students_html +=
                "<a " + (id != null ? "href='"+href+"'" : "") + ">" +
                id + (x != data['data'].length - 1 ? ", " : "") +
                "</a>";
        }
        
        // Hide all other (potentially) open popups
        $('.popup-form').css('display', 'none');
        
        // Display and update the popup
        $("#student-marklist-popup").css("display", "block");
        $("#student-marklist-popup-student-names")[0].innerHTML = students_html;
    })
}

//check if the first mark (Full/no credit) should be selected
function checkMarks(c_index) {
    c_index = parseInt(c_index);
    var mark_table = $('#marks-parent-'+c_index);
    var first_mark = mark_table.find('i[name=mark_icon_'+c_index+'_0]');
    var all_false = true; //ignores the first mark
    mark_table.find('.mark').each(function() {
        if($(this).attr('name') == 'mark_icon_'+c_index+'_0')
        {
            return;
        }
        if($(this)[0].classList.contains('fa-square')) {
            all_false = false;
            return false;
        }
    });

    if(all_false === false) {
        if (first_mark[0].classList.contains('fa-square')) {
            first_mark.toggleClass("fa-square-o fa-square");
            getMark(c_index, 0).has = false;
        }
    } 
}

/**
 * Calculate the number of points a component has with the given selected marks
 * @param c_index 1-indexed component index
 * @returns Either "None Selected" or the point value
 */
function calculateMarksPoints(c_index) {
    c_index = parseInt(c_index);
    var component = getComponent(c_index);
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
        for(var i=0; i<component.marks.length; i++){
            var current_mark = $('#mark_id-'+c_index+'-'+i);
            if(editModeEnabled==false || editModeEnabled==null){
                current_mark.find('textarea[name=mark_text_'+c_index+'_'+i+']').attr('style', "width:90%; resize:none; cursor: default; border:none; outline: none; background-color: #E9EFEF");
                current_mark.find('input[name=mark_points_'+c_index+'_'+i+']').attr('style', "width:50%; resize:none; cursor: default; border:none; outline: none; background-color: #E9EFEF");
            }
        }
    }
    else{
        $('#summary-' + c_index)[0].style.backgroundColor = "#F9F9F9";
        $('#title-' + c_index)[0].style.backgroundColor = "#F9F9F9";
        for(var i=0; i<component.marks.length; i++){
            var current_mark = $('#mark_id-'+c_index+'-'+i);
            if(editModeEnabled==false || editModeEnabled==null){
                current_mark.find('textarea[name=mark_text_'+c_index+'_'+i+']').attr('style', "width:90%; resize:none; cursor: default; border:none; outline: none; background-color: #F9F9F9");
                current_mark.find('input[name=mark_points_'+c_index+'_'+i+']').attr('style', "width:50%; resize:none; cursor: default; border:none; outline: none; background-color: #F9F9F9");
            }
        }
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
    if(custom_message == ""){
        $('#mark_points_custom-' + c_index)[0].disabled=true;
        $('#mark_points_custom-' + c_index)[0].style.cursor="not-allowed";
        $('#mark_icon_custom-' + c_index)[0].style.cursor="not-allowed";
        $('#mark_points_custom-' + c_index)[0].value="";
    }
    else {
        $('#mark_points_custom-' + c_index)[0].disabled = false;
        $('#mark_points_custom-' + c_index)[0].style.cursor = "default";
        $('#mark_icon_custom-' + c_index)[0].style.cursor = "pointer";
        if ($('#mark_points_custom-' + c_index)[0].value == "") {
            $('#mark_points_custom-' + c_index)[0].value = "0";
        }
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
    earned = Math.max(earned, 0);

    updateBadge($("#grading_total"), earned, total);
    updateBadge($("#autograding_total"), autoEarned, autoTotal);
    updateBadge($("#score_total"), (earned + autoEarned), (total + autoTotal));
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
 * @param me DOM mark icon element
 */
function selectMark(me) {
    var icon = $(me).find("i");
    var skip = true; //if the table is all false initially, skip check marks.
    var question_num = me.dataset.component_index;
    var mark_num = me.dataset.mark_index;
    var mark_table = $('#marks-parent-'+question_num);
    mark_table.find('.mark').each(function() {
        if($(this)[0].classList.contains('fa-square')) {
            skip = false;
            return false;
        }
    });

    var mark = getMark(question_num, mark_num);
    mark.has = !mark.has;

    //actually checks the mark then checks if the first mark is still valid
    icon.toggleClass("fa-square-o", !mark.has);
    icon.toggleClass("fa-square", mark.has);
    if (skip === false) {
        checkMarks(question_num);
    }

    //updates the progress points in the title
    updateProgressPoints(question_num);        
}

/**
 * Closes all the questions except the one being opened
 * openClose toggles alot of listed elements in order to work
 * @param c_index 1-indexed component index
 */
function openClose(c_index) {
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
 * Set if a component should be visible
 * @param c_index 1-indexed component index
 * @param show If the component should be visible
 */
function setMarkVisible(c_index, show) {
    var page = ($('#page-' + c_index)[0]).innerHTML;

    var title           = $('#title-' + c_index);
 //   var cancel_button   = $('#title-cancel-' + c_index);
    var current_summary = $('#summary-' + c_index);

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
 * Set if the general comment box should be visible
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

// Saves the general comment
function saveGeneralComment(sync, successCallback, errorCallback) {
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
    
    ajaxSaveGeneralComment(gradeable.id, gradeable.user_id, gradeable.active_version, gradeable_comment, sync, successCallback, errorCallback);
}

// Saves the last opened mark so that exiting the page doesn't
//  have the ta lose their grading data
function saveLastOpenedMark(sync, successCallback, errorCallback) {
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

function saveMark(c_index, sync, successCallback, errorCallback) {
    var gradeable = getGradeable();

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
    // Gathers all the mark's data (ex. points, note, etc.)
    //getComponent(c_index).marks.sort(compareOrder);
    for(var m_index=0; m_index < arr_length; m_index++){
        var current_row = $('#mark_id-'       +c_index+'-'+getMark(c_index, m_index).id);
        var info_mark   = $('#mark_info_id-'  +c_index+'-'+getMark(c_index, m_index).id);
        var success     = true;
        mark_data[m_index] = {
            id      : getMark(c_index, m_index).id,
            points  : getMark(c_index, m_index).points,
            note    : getMark(c_index, m_index).name,
            selected: getMark(c_index, m_index).has,
            order   : getMark(c_index, m_index).order
        };
        info_mark[0].style.display = '';
        existing_marks_num++;
    }
    var current_row = $('#mark_custom_id-'+c_index);

    var current_title = $('#title-' + c_index);
    var custom_points  = current_row.find('input[name=mark_points_custom_'+c_index+']').val();
    var custom_message = current_row.find('textarea[name=mark_text_custom_'+c_index+']').val();

    // Updates the total number of points and text
    var current_question_text = $('#rubric-textarea-' + c_index);
    var component = getComponent(c_index);
    
    var lower_clamp    = parseFloat(component.lower_clamp);
    var current_points = parseFloat(component.default);
    var upper_clamp    = parseFloat(component.upper_clamp);

    var new_text   = "";
    var first_text = true;
    var all_false  = true;

    for (var m_index = 0; m_index < arr_length; m_index++) {
        if (mark_data[m_index].selected === true) {
            all_false = false;
            
            current_points += parseFloat(mark_data[m_index].points);
            mark_data[m_index].note = escapeHTML(mark_data[m_index].note);
            
            var prepend = (!first_text) ? ("\<br>") : ("");
            var points  = (parseFloat(mark_data[m_index].points) != 0) ? ("(" + mark_data[m_index].points + ") ") : ("");
            
            new_text += prepend + "* " + points + mark_data[m_index].note;
            if (first_text) {
                first_text = false;
            }
        }                
    }
    if (!isNaN(parseFloat(custom_points))) {
        current_points += parseFloat(custom_points);
        
        if (parseFloat(custom_points) != 0) {
            all_false = false;
        }
    }

    if(custom_message != "") {
        custom_message = escapeHTML(custom_message);
        
        var prepend = (!first_text) ? ("\<br>") : ("");
        var points  = (parseFloat(custom_points) != 0) ? ("(" + custom_points + ") ") : ("");
        
        new_text += prepend + "* " + points + custom_message;
        if (first_text) {
            first_text = false;
        }
        
        all_false = false;
    }

    var new_background="#F9F9F9";
    if (all_false) {
        new_text = "Click me to grade!";
        new_background="#E9EFEF";
    }

    // Clamp points
    current_points = Math.min(Math.max(current_points, lower_clamp), upper_clamp);
    
    current_question_text.html(new_text);

    calculatePercentageTotal();

    var gradedByElement = $('#graded-by-' + c_index);
    var savingElement = $('#graded-saving-' + c_index);
    var ungraded = gradedByElement.text() === "Ungraded!";

    gradedByElement.hide();
    savingElement.show();

    var overwrite = ($('#overwrite-id').is(':checked')) ? ("true") : ("false");
    ajaxSaveMarks(gradeable.id, gradeable.user_id, component.id, arr_length, gradeable.active_version, custom_points, custom_message, overwrite, mark_data, existing_marks_num, sync, function(data) {
        data = JSON.parse(data);
        if (all_false === true) {
            //We've reset
            gradedByElement.text("Ungraded!");
            component.grader = null;
        } else {
            if (component.grader === null || (data.modified && (ungraded || overwrite === "true"))) {
                if (component.grader === null) {
                    component.grader = {
                        id: ""
                    };
                }
                component.grader.id = grading_data.your_user_id;
            }
            //Just graded it
            gradedByElement.text("Graded by " + component.grader.id + "!");
        }

        gradedByElement.show();
        savingElement.hide();

        if(data['version_updated'] === "true") {
            if ($('#wrong_version_' + c_index).length)
                $('#wrong_version_' + c_index)[0].innerHTML = "";
        }
        
        if (typeof(successCallback) === "function")
            successCallback(data);
            
    }, errorCallback ? errorCallback : function() {
        console.error("Something went wront with saving marks...");
        alert("There was an error with saving the grade. Please refresh the page and try agian.");
    });
    ajaxGetMarkData(gradeable.id, gradeable.user_id, m_index, function(data) {
        data = JSON.parse(data);
    });
}

//finds what mark is currently open
function findCurrentOpenedMark() {
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

function verifyMark(gradeable_id, component_id, user_id, verifyAll){
    var action = (verifyAll) ? 'verify_all' : 'verify_grader';
    $.ajax({
        type: "POST",
        url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': action}),
        async: true,
        data: {
            'gradeable_id' : gradeable_id,
            'component_id' : component_id,
            'anon_id' : user_id,
        },
        success: function(data) {
            window.location.reload();
            console.log("verified user");
            if(action === 'verify_all')
                document.getElementById("verifyAllButton").style.display = "none";
        },
        error: function() {
            alert("failed to verify grader");
        }
    })
}

/**
 * Open the given component (if it's not open already), saving changes on any previous components
 * @param c_index 1-indexed component index
 */
function openMark(c_index) {
    saveLastOpenedMark(true);
    saveMark(c_index, true);
    updateMarksOnPage(c_index);

    //If it's already open, then openClose() will close it
    if (findCurrentOpenedMark() !== c_index) {
        openClose(c_index);
    }
}

/**
 * Close the given component (if it's open), optionally saving changes
 * @param c_index 1-indexed component index
 * @param save If changes should be saved
 */
function closeMark(c_index, save) {
    //Can't close a closed mark
    if (findCurrentOpenedMark() !== c_index) {
        return;
    }

    if (save) {
        saveLastOpenedMark(true);
        saveMark(c_index, true);
    }
    updateMarksOnPage(c_index);
    setMarkVisible(c_index, false);
}

/**
 * Toggle if a component should be visible
 * @param c_index 1-indexed component index
 * @param save If changes should be saved
 */
function toggleMark(c_index, save) {
    if (findCurrentOpenedMark() === c_index) {
        closeMark(c_index, save);
    } else {
        openMark(c_index);
    }
}

/**
 * Open the general message input (if it's not open already), saving changes on any previous mark
 */
function openGeneralMessage() {
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
function closeGeneralMessage(save) {
    //Cannot save it if it is not being edited
    if (findCurrentOpenedMark() !== GENERAL_MESSAGE_ID) {
        return;
    }

    if (save) {
        saveLastOpenedMark(true);
        saveGeneralComment(true);
    } else {
        updateGeneralComment();
    }
    setGeneralVisible(false);
}

/**
 * Toggle if the general comment should be visible
 * @param save If changes should be saved
 */
function toggleGeneralMessage(save) {
    if (findCurrentOpenedMark() === -2) {
        closeGeneralMessage(save);
    } else {
        openGeneralMessage();
    }
}

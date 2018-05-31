/// When no component is selected, the current ID will be this value
NO_COMPONENT_ID = -1;

/// Component ID of the "General Comment" box at the bottom
GENERAL_MESSAGE_ID = -2;

function getGradeable() {
    return grading_data.gradeable;
}

function getComponent(c_index) {
    return grading_data.gradeable.components[c_index - 1];
}

function getMark(c_index, m_index) {
    return grading_data.gradeable.components[c_index - 1].marks[m_index];
}

function updateMarkPoints(me) {
    getMark(me.dataset.component_index, me.dataset.mark_index).points = parseFloat($(me).val());
    updateProgressPoints(me.dataset.component_index);
}

function updateMarkText(me) {
    getMark(me.dataset.component_index, me.dataset.mark_index).name = $(me).val();
    updateProgressPoints(me.dataset.component_index);
}

function updateCustomMarkPoints(me) {
    var component = getComponent(me.dataset.component_index);
    var val = $(me).val();
    component.score = parseFloat(val);
    updateProgressPoints(me.dataset.component_index);
}

function updateCustomMarkText(me) {
    var component = getComponent(me.dataset.component_index);
    var val = $(me).val();
    component.comment = val;
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

function getMarkView(num, x) {
    return Twig.twig({ref: "Mark"}).render({
        gradeable: getGradeable(),
        component: getComponent(num),
        mark: getMark(num, x),
        c_index: num,
        m_index: x
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

function haveMarksChanged(num, data) {
    var marks = $('[name=mark_'+num+']');
    var mark_notes = $('[name^=mark_text_'+num+']');
    var mark_scores = $('[name^=mark_points_'+num+']');
    var custom_mark_points = $('input[name=mark_points_custom_'+num+']');
    var custom_mark_text = $('textarea[name=mark_text_custom_'+num+']');

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

function updateMarksOnPage(num, background, min, max, precision, gradeable_id, user_id, get_active_version, question_id, your_user_id) {
    var parent = $('#marks-parent-'+num);
    parent.children().remove();
    parent.append("<tr><td colspan='4'>Loading...</td></tr>");
    ajaxGetMarkData(gradeable_id, user_id, question_id, function(data) {
        data = JSON.parse(data);

        // If nothing has changed, then don't update
        if (!haveMarksChanged(num, data))
            return;

        // Clear away all marks
        parent.children().remove();

        // Custom mark
        {
            var x = data['data'].length-1;
            var score = data['data'][x]['custom_score'];
            var note  = data['data'][x]['custom_note'];
            
            var score_el = $('input[name=mark_points_custom_'+num+']');
            var note_el = $('textarea[name=mark_text_custom_'+num+']');
            score_el.val(parseFloat(score));
            note_el.val(note);
            var icon = $('i[name=mark_icon_'+num+'_custom]');
            if ((note != "" && note != undefined) && icon[0].classList.contains('fa-square-o') ||
                 (note == "" || note == undefined) && icon[0].classList.contains('fa-square')) {
                     icon.toggleClass("fa-square-o fa-square");
            }

            getComponent(num).score = score;
            getComponent(num).comment = note;
        }
        
        // Add all marks back
        // data['data'].length - 2 to ignore the custom mark
        for (var x = data['data'].length-2; x >= 0; x--) {
            var is_publish = data['data'][x]['is_publish'] == 't';
            var hasMark    = data['data'][x]['has_mark'];
            var score      = data['data'][x]['score'];
            var note       = data['data'][x]['note'];

            getMark(num, x).publish = is_publish;
            getMark(num, x).has = hasMark;
            getMark(num, x).score = score;
            getMark(num, x).name = note;

            parent.prepend(getMarkView(num, x));
        }
    });
}

function updateGeneralComment(gradeable_id, user_id) {
    ajaxGetGeneralCommentData(gradeable_id, user_id, function(data) {
        data = JSON.parse(data);
        
        $('#comment-id-general').val(data['data']);
    });
}

function addMark(me, num, background, min, max, precision, gradeable_id, user_id, get_active_version, question_id, your_user_id) {
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

            parent.append(getMarkView(num, x));

            
            // Add new mark and then update
            // ajaxAddNewMark(gradeable_id, user_id, question_id, note, points, function() {
            //     updateMarksOnPage(num, background, min, max, precision, gradeable_id, user_id, get_active_version, question_id, your_user_id);
            // });
        }
    };
}

// TODO: this
function deleteMark(me, num, last_num) {
    var current_row = $(me.parentElement.parentElement);
    current_row.remove();
    var last_row = $('[name=mark_'+num+']').last().attr('id');
    var totalD = -1;
    if (last_row == null) {
        totalD = -1;
    } 
    else {
        totalD = parseInt($('[name=mark_'+num+']').last().attr('id').split('-')[2]);
    }

    //updates the remaining marks's info
    var current_num = parseInt(last_num);
    for (var i = current_num + 1; i <= totalD; i++) {
        var new_num = i-1;
        var current_mark = $('#mark_id-'+num+'-'+i);
        current_mark.find('input[name=mark_points_'+num+'_'+i+']').attr('name', 'mark_points_'+num+'_'+new_num);
        current_mark.find('textarea[name=mark_text_'+num+'_'+i+']').attr('name', 'mark_text_'+num+'_'+new_num);
        current_mark.find('i[name=mark_icon_'+num+'_'+i+']').attr('name', 'mark_icon_'+num+'_'+new_num);
        current_mark.find('span[id=mark_info_id-'+num+'-'+i+']').attr('id', 'mark_info_id-'+num+'-'+new_num);
        current_mark.attr('id', 'mark_id-'+num+'-'+new_num);
    }
}

// gets all the information from the database to return some stats and a list of students with that mark
function showMarklist(me, gradeable_id) {
    var question_num = parseInt($(me).attr('id').split('-')[1]);
    var order_num = parseInt($(me).attr('id').split('-')[2]);
    var gradeable_component_id = $('#marks-parent-' + question_num)[0].dataset.question_id;
    
    ajaxGetMarkedUsers(gradeable_id, gradeable_component_id, order_num, function(data) {
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

            var id = data['data'][x]['gd_user_id'];
            var href = window.location.href.replace(/&who_id=([a-z0-9]*)/, "&who_id="+id);
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
function checkMarks(question_num) {
    question_num = parseInt(question_num);
    var mark_table = $('#marks-parent-'+question_num);
    var first_mark = mark_table.find('i[name=mark_icon_'+question_num+'_0]');
    var all_false = true; //ignores the first mark
    mark_table.find('.mark').each(function() {
        if($(this).attr('name') == 'mark_icon_'+question_num+'_0')
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
        }
    } 
}

//calculate the number of points a component has with the given selected marks
function calculateMarksPoints(question_num) {
    question_num = parseInt(question_num);
    var component = getComponent(question_num);
    var lower_clamp = component.lower_clamp;
    var current_points = component.default;
    var upper_clamp = component.upper_clamp;
    var arr_length = component.marks.length;
    var any_selected=false;

    for (var i = 0; i < arr_length; i++) {
        var is_selected = false;
        if (component.marks[i].has) {
            is_selected = true;
        }
        if (is_selected === true) {
            any_selected = true;
            current_points += component.marks[i].points;
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

function updateProgressPoints(question_num) {
    question_num = parseInt(question_num);
    var current_progress = $('#progress_points-' + question_num);
    var current_points = calculateMarksPoints(question_num);
    var current_question_text = $('#rubric-textarea-' + question_num);
    var component = getComponent(question_num);
    var max_points = parseFloat(component.max_value);
    var summary_text = "Click me to grade!";


    if(current_points=="None Selected"){
        $('#grade-' + question_num)[0].innerHTML = "";     
        $('#summary-' + question_num)[0].style.backgroundColor = "#E9EFEF";
        $('#gradebar-' + question_num)[0].style.backgroundColor = "#999";
        $('#title-' + question_num)[0].style.backgroundColor = "#E9EFEF";
    }
    else{
        $('#grade-' + question_num)[0].innerHTML = current_points;
        $('#summary-' + question_num)[0].style.backgroundColor = "#F9F9F9";
        $('#title-' + question_num)[0].style.backgroundColor = "#F9F9F9";
        //extra credit
        if(current_points > max_points){
            $('#gradebar-' + question_num)[0].style.backgroundColor = "#006600";
        }
        else if(current_points == max_points){
            $('#gradebar-' + question_num)[0].style.backgroundColor = "#006600";
        }
        else if(current_points > 0){
            $('#gradebar-' + question_num)[0].style.backgroundColor = "#eac73d";
        }
        else{
            $('#gradebar-' + question_num)[0].style.backgroundColor = "#c00000";
        }

        summary_text = "";
        for (var i = 0; i < component.marks.length; i ++) {
            var mark = component.marks[i];
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

    var custom_message = $('textarea[name=mark_text_custom_'+question_num+']').val();
    if(custom_message == ""){
        $('#mark_points_custom-' + question_num)[0].disabled=true;
        $('#mark_points_custom-' + question_num)[0].style.cursor="not-allowed";
        $('#mark_icon_custom-' + question_num)[0].style.cursor="not-allowed";
        $('#mark_points_custom-' + question_num)[0].value="";
    }
    else {
        $('#mark_points_custom-' + question_num)[0].disabled = false;
        $('#mark_points_custom-' + question_num)[0].style.cursor = "default";
        $('#mark_icon_custom-' + question_num)[0].style.cursor = "pointer";
        if ($('#mark_points_custom-' + question_num)[0].value == "") {
            $('#mark_points_custom-' + question_num)[0].value = "0";
        }
    }

    calculatePercentageTotal();
}

function updateAllProgressPoints() {
    for (var i = 1; i <= getGradeable().components.length; i ++) {
        updateProgressPoints(i);
    }
}

function calculatePercentageTotal() {
    var gradeable = getGradeable();
    var total = 0;
    var earned = 0;
    var autoTotal = gradeable.total_autograder_non_extra_credit_points;
    var autoEarned = gradeable.graded_autograder_points;


    for (var i = 1; i <= gradeable.components.length; i ++) {
        var component = getComponent(i);
        total += component.max_value;
        earned += calculateMarksPoints(i);
    }

    total = Math.max(total, 0);
    earned = Math.max(earned, 0);

    $("#score_total").html(earned + " / " + parseFloat(autoTotal + total) + "&emsp;&emsp;&emsp;" + " AUTO-GRADING: " + autoEarned + "/" + autoTotal);
}

function selectMark(me, first_override) {
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

//closes all the questions except the one being opened
//openClose toggles alot of listed elements in order to work
function openClose(row_id) {
    var row_num = parseInt(row_id);
    var total_num = getGradeable().components.length;

    //-2 means general comment, else open the row_id with the number
    var general_comment = $('#extra-general');
    setGeneralVisible(row_num === GENERAL_MESSAGE_ID && general_comment[0].style.display === 'none');

    for (var x = 1; x <= total_num; x++) {
        var current_summary = $('#summary-' + x);
        setMarkVisible(x, x === row_num && current_summary[0].style.display === '');
    }

    updateCookies();
}

//Set if the mark at index X should be visible
function setMarkVisible(x, show) {
    var page = ($('#page-' + x)[0]).innerHTML;

    var title           = $('#title-' + x);
 //   var cancel_button   = $('#title-cancel-' + x);
    var current_summary = $('#summary-' + x);

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
    // $("[id$='-"+x+"'][data-changebg='true']")      .css("background-color", (show ? "#e6e6e6" : "initial"));
    $("[id$='-"+x+"'][data-changedisplay1='true']").css("display",          (show ? "" : "none"));
    $("[id$='-"+x+"'][data-changedisplay2='true']").css("display",          (show ? "none" : ""));

    title.attr('colspan', (show ? 3 : 4));
  //  cancel_button.attr('colspan', (show ? 1 : 0));
}

//Set if the general comment box should be visible
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
function saveGeneralComment(gradeable_id, user_id, active_version, sync, successCallback, errorCallback ) {
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
    
    ajaxSaveGeneralComment(gradeable_id, user_id, active_version, gradeable_comment, sync, successCallback, errorCallback);
}

// Saves the last opened mark so that exiting the page doesn't
//  have the ta lose their grading data
function saveLastOpenedMark(gradeable_id, user_id, active_version, your_user_id, sync, successCallback, errorCallback) {
    // Find open mark
    var index = 1;
    var mark = $('#marks-parent-' + index);
    while(mark.length > 0) {
        // If mark is open, then save it
        if (mark[0].style.display !== 'none') {
            var gradeable_component_id = getComponent(index).id;
            saveMark(index, gradeable_id, user_id, active_version, gradeable_component_id, your_user_id, sync, successCallback, errorCallback);
            return;
        }
        mark = $('#marks-parent-' + (++index));
    }
    // If no open mark was found, then save general comment
    saveGeneralComment(gradeable_id, user_id, active_version, sync, successCallback, errorCallback);
}

function saveMark(num, gradeable_id, user_id, active_version, gc_id, your_user_id, sync, successCallback, errorCallback) {
    if ($('#marks-parent-' + num)[0].style.display === "none") {
        //Nothing to save so we are fine
        if (typeof(successCallback) === "function") {
            successCallback();
        }
        return;
    }
    
    var arr_length = $('tr[name=mark_'+num+']').length;
    
    var mark_data = new Array(arr_length);
    var existing_marks_num = 0;
    
    // Gathers all the mark's data (ex. points, note, etc.)
    for (var i = 0; i < arr_length; i++) {
        var current_row = $('#mark_id-'       +num+'-'+i);
        var info_mark   = $('#mark_info_id-'  +num+'-'+i);
        var success     = true;

        mark_data[i] = {
            points  : current_row.find('input[name=mark_points_'+num+'_'+i+']').val(),
            note    : current_row.find('textarea[name=mark_text_'+num+'_'+i+']').val(),
            selected: current_row.find('i[name=mark_icon_'+num+'_'+i+']')[0].classList.contains('fa-square'),
            order   : i
        };
        
        info_mark[0].style.display = '';
        existing_marks_num++;
    }

    var current_row = $('#mark_custom_id-'+num);

    var current_title = $('#title-' + num);
    var custom_points  = current_row.find('input[name=mark_points_custom_'+num+']').val();
    var custom_message = current_row.find('textarea[name=mark_text_custom_'+num+']').val();

    // Updates the total number of points and text
    var current_question_num  = $('#grade-' + num);
    var current_question_text = $('#rubric-textarea-' + num);
    var component = getComponent(num);
    
    var lower_clamp    = parseFloat(component.lower_clamp);
    var current_points = parseFloat(component.default);
    var upper_clamp    = parseFloat(component.upper_clamp);

    var new_text   = "";
    var first_text = true;
    var all_false  = true;

    for (var i = 0; i < arr_length; i++) {
        if (mark_data[i].selected === true) {
            all_false = false;
            
            current_points += parseFloat(mark_data[i].points);
            mark_data[i].note = escapeHTML(mark_data[i].note);
            
            var prepend = (!first_text) ? ("\<br>") : ("");
            var points  = (parseFloat(mark_data[i].points) != 0) ? ("(" + mark_data[i].points + ") ") : ("");
            
            new_text += prepend + "* " + points + mark_data[i].note;
            if (first_text) {
                first_text = false;
            }
        }                
    }
    if (!isNaN(parseFloat(custom_points))) {
        current_points += parseFloat(custom_points);
    }
    
    if (parseFloat(custom_points) != 0) {
        all_false = false;
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

    var gradedByElement = $('#graded-by-' + num);
    var savingElement = $('#graded-saving-' + num);
    var ungraded = gradedByElement.text() === "Ungraded!";

    gradedByElement.hide();
    savingElement.show();

    var overwrite = ($('#overwrite-id').is(':checked')) ? ("true") : ("false");
    
    ajaxSaveMarks(gradeable_id, user_id, gc_id, arr_length, active_version, custom_points, custom_message, overwrite, mark_data, existing_marks_num, sync, function(data) {
        data = JSON.parse(data);

        if (all_false === true) {
            //We've reset
            gradedByElement.text("Ungraded!");
        } else if(ungraded || (overwrite === "true")) {
            //Just graded it
            gradedByElement.text("Graded by " + your_user_id + "!");
            var question_points = parseFloat(current_question_num[0].innerHTML);
            var max_points = parseFloat(component.max_value);
        }

        gradedByElement.show();
        savingElement.hide();

        if(data['version_updated'] === "true") {
            if ($('#wrong_version_' + num).length)
                $('#wrong_version_' + num)[0].innerHTML = "";
        }
        
        if (typeof(successCallback) === "function")
            successCallback(data);
            
    }, errorCallback ? errorCallback : function() {
        console.error("Something went wront with saving marks...");
        alert("There was an error with saving the grade. Please refresh the page and try agian.");
    });
}

//finds what mark is currently open
function findCurrentOpenedMark() {
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

//Open the given mark (if it's not open already), saving changes on any previous mark
function openMark(id) {
    var gradeable = getGradeable();
    var component = getComponent(id);

    saveLastOpenedMark(gradeable.id, gradeable.user_id, gradeable.active_version, grading_data.your_user_id, true);
    saveMark(id, gradeable.id, gradeable.user_id, gradeable.active_version, component.id, grading_data.your_user_id, true);
    updateMarksOnPage(id, '', component.lower_clamp, component.upper_clamp, gradeable.precision, gradeable.id, gradeable.user_id, gradeable.active_version, component.id, grading_data.your_user_id);

    //If it's already open, then openClose() will close it
    if (findCurrentOpenedMark() !== id) {
        openClose(id);
    }
}

//Close the given mark (if it's open), optionally saving changes
function closeMark(id, save) {
    //Can't close a closed mark
    if (findCurrentOpenedMark() !== id) {
        return;
    }

    var gradeable = getGradeable();
    var component = getComponent(id);

    if (save) {
        saveLastOpenedMark(gradeable.id, gradeable.user_id, gradeable.active_version, grading_data.your_user_id, true);
        saveMark(id, gradeable.id, gradeable.user_id, gradeable.active_version, component.id, grading_data.your_user_id, true);
    }
    updateMarksOnPage(id, '', component.lower_clamp, component.upper_clamp, gradeable.precision, gradeable.id, gradeable.user_id, gradeable.active_version, component.id, grading_data.your_user_id);
    setMarkVisible(id, false);
}

function toggleMark(id, save) {
    if (findCurrentOpenedMark() === id) {
        closeMark(id, save);
    } else {
        openMark(id);
    }
}

//Open the general message input (if it's not open already), saving changes on any previous mark
function openGeneralMessage() {
    var gradeable = getGradeable();

    saveLastOpenedMark(gradeable.id, gradeable.user_id, gradeable.active_version, grading_data.your_user_id, true);
    saveGeneralComment(gradeable.id, gradeable.user_id, gradeable.active_version, true);

    //If it's already open, then openClose() will close it
    if (findCurrentOpenedMark() !== GENERAL_MESSAGE_ID) {
        openClose(GENERAL_MESSAGE_ID);
    }
}

//Close the general message input (if it's open), optionally saving changes
function closeGeneralMessage(save) {
    //Cannot save it if it is not being edited
    if (findCurrentOpenedMark() !== GENERAL_MESSAGE_ID) {
        return;
    }

    var gradeable = getGradeable();

    if (save) {
        saveLastOpenedMark(gradeable.id ,gradeable.user_id, gradeable.active_version, grading_data.your_user_id, true);
        saveGeneralComment(gradeable.id ,gradeable.user_id, gradeable.active_version, true);
    } else {
        updateGeneralComment(gradeable.id, gradeable.user_id);
    }
    setGeneralVisible(false);
}

function toggleGeneralMessage(save) {
    if (findCurrentOpenedMark() === -2) {
        closeGeneralMessage(save);
    } else {
        openGeneralMessage();
    }
}

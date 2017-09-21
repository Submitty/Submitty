function fixMarkPointValue(me) {
    var max = parseFloat($(me).attr('max'));
    var min = parseFloat($(me).attr('min'));
    var current_value = parseFloat($(me).val());
    if (current_value > max) {
        $(me).val(max);
    } else if (current_value < min) {
        $(me).val(min);
    }
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

function addMark(me, num, background, min, max, precision, gradeable_id, user_id, get_active_version, question_id, your_user_id) {
    var last_num = -10;
    var current_row = $(me.parentElement.parentElement);
    var current = $('[name=mark_'+num+']').last().attr('id');
    if (current == null) {
        last_num = -1;
    } 
    else {
        last_num = parseInt($('[name=mark_'+num+']').last().attr('id').split('-')[2]);
    }

    var new_num = last_num + 1;
    current_row.before(' \
<tr id="mark_id-'+num+'-'+new_num+'" name="mark_'+num+'"> \
<td colspan="1" style="'+background+'; text-align: center;"> \
    <span onclick="selectMark(this);"> <i class="fa fa-square-o mark" name="mark_icon_'+num+'_'+new_num+'" style="visibility: visible; cursor: pointer; position: relative; top: 2px;"></i> </span> \
    <input name="mark_points_'+num+'_'+new_num+'" type="number" onchange="fixMarkPointValue(this);" step="'+precision+'" value="0" min="'+min+'" max="'+max+'" style="width: 50%; resize:none; min-width: 50px;"> \
</td> \
<td colspan="3" style="'+background+'"> \
    <textarea name="mark_text_'+num+'_'+new_num+'" onkeyup="autoResizeComment(event);" rows="1" style="width:90%; resize:none;"></textarea> \
    <span id="mark_info_id-'+num+'-'+new_num+'" style="display: none" onclick="saveMark('+num+',\''+gradeable_id+'\' ,\''+user_id+'\','+get_active_version+', '+question_id+', \''+your_user_id+'\'); getMarkInfo(this,\''+gradeable_id+'\');"> <i class="fa fa-users icon-got-this-mark"></i> </span> \
    <span id="mark_remove_id-'+num+'-'+new_num+'" onclick="deleteMark(this,'+num+','+new_num+');"> <i class="fa fa-times" style="visibility: visible; cursor: pointer; position: relative; top: 2px; left: 10px;"></i> </span> \
</td> \
</tr> \
    '); 
}

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
        current_mark.find('span[id=mark_remove_id-'+num+'-'+i+']').attr('onclick', 'deleteMark(this,'+num+','+new_num+');');
        current_mark.find('i[name=mark_icon_'+num+'_'+i+']').attr('name', 'mark_icon_'+num+'_'+new_num);
        current_mark.find('span[id=mark_info_id-'+num+'-'+i+']').attr('id', 'mark_info_id-'+num+'-'+new_num);
        current_mark.find('span[id=mark_remove_id-'+num+'-'+i+']').attr('id', 'mark_remove_id-'+num+'-'+new_num);
        current_mark.attr('id', 'mark_id-'+num+'-'+new_num);
    }
}

//gets all the information from the database to return some stats and a list of students with that mark
function getMarkInfo(me, gradeable_id) {
    var question_num = parseInt($(me).attr('id').split('-')[1]);
    var order_num = parseInt($(me).attr('id').split('-')[2]);
    var gradeable_component_id = $('#extra-' + question_num)[0].dataset.question_id;
    $.ajax({
        type: "POST",
        url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'get_marked_users'}),
        data: {
            'gradeable_id' : gradeable_id,
            'gradeable_component_id' : gradeable_component_id,
            'order_num' : order_num
        },
        success: function(data) {
            console.log("success for getting the information on marks");
            data = JSON.parse(data);

            var graded = 0;
            var total = 0;
            
            for (var x in data['sections']) {
                graded += parseInt(data['sections'][x]['graded_components']);
                total += parseInt(data['sections'][x]['total_components']);
            }

            var elem_html = "";
            elem_html += "<h3>Students who received<br>&nbsp;<br>" + data['name_info']['question_name'] + ":<br><em>\"" 
                + data['name_info']['mark_note'] + "\"</em></h3>";
            elem_html += "&nbsp;<br>";
	    elem_html += "# of students with mark: " + data['data'].length + "<br>";
            elem_html += "# of graded components: " + graded + "<br>";
            elem_html += "# of total components: " + total + "<br>";
            elem_html += "&nbsp;<br>";
            var y = 1;
            for (var x = 0; x < data['data'].length; x++) {

                elem_html += "" + data['data'][x]['gd_user_id'];
                if (x != data['data'].length - 1) {
                    elem_html += ", ";
                }
                if(y == 5) {
                    elem_html += "<br>";
                    y = 0;
                }
                y++;
            }
            $('.popup-form').css('display', 'none');
            var form = $("#student-marklist-popup");
            form.css("display", "block");
            form.css("width", "500px");
            form.css("margin-left", "-250px");
            $("#student-marklist-popup-content")[0].innerHTML = elem_html;
        },
        error: function() {
            console.log("Couldn't get the information on marks");
        }
    })
}

//check if the first mark (Full/no credit) should be selected
function checkMarks(question_num) {
    question_num = parseInt(question_num);
    var mark_table = $('#extra-'+question_num);
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
    var current_question_num = $('#grade-' + question_num);
    var lower_clamp = parseFloat(current_question_num[0].dataset.lower_clamp);
    var current_points = parseFloat(current_question_num[0].dataset.default);
    var upper_clamp = parseFloat(current_question_num[0].dataset.upper_clamp);
    var arr_length = $('tr[name=mark_'+question_num+']').length;


    for (var i = 0; i < arr_length; i++) {
        var current_row = $('#mark_id-'+question_num+'-'+i);
        var is_selected = false;
        if (current_row.find('i[name=mark_icon_'+question_num+'_'+i+']')[0].classList.contains('fa-square')) {
            is_selected = true;
        }
        if (is_selected === true) {
            current_points += parseFloat(current_row.find('input[name=mark_points_'+question_num+'_'+i+']').val());
        }
    }

    current_row = $('#mark_custom_id-'+question_num);
    var custom_points = parseFloat(current_row.find('input[name=mark_points_custom_'+question_num+']').val());
    if (isNaN(custom_points)) {
        current_points += 0;
    } else {
        current_points += custom_points;
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
    var current_question_num = $('#grade-' + question_num);
    var current_points = calculateMarksPoints(question_num);
    var max_points = parseFloat(current_question_num[0].dataset.max_points);
    current_progress[0].innerHTML = current_points + " / " + max_points; 
}

function selectMark(me, first_override = false) {
    var icon = $(me).find("i");
    var skip = true; //if the table is all false initially, skip check marks.
    var question_num = parseInt(icon.attr('name').split('_')[2]);
    var mark_table = $('#extra-'+question_num);
    mark_table.find('.mark').each(function() {
        if($(this)[0].classList.contains('fa-square')) {
            skip = false;
            return false;
        }
    });

    //actually checks the mark then checks if the first mark is still valid
    icon.toggleClass("fa-square-o fa-square");
    if (skip === false) {
        checkMarks(question_num);
    }

    //updates the progress points in the title
    updateProgressPoints(question_num);        
}

//closes all the questions except the one being opened
//openClose toggles alot of listed elements in order to work
function openClose(row_id, num_questions = -1) {
    var row_num = parseInt(row_id);
    var total_num = 0;
    if (num_questions === -1) {
        total_num = parseInt($('#rubric-table')[0].dataset.num_questions);
    } else {
        total_num = parseInt(num_questions);
    }
    //-2 means general comment, else open the row_id with the number
    general_comment = $('#extra-general');
    general_comment_summary = $('#summary-general');
    general_comment_cancel_mark = $('#cancel-mark-general');
    general_comment_save_mark = $('#save-mark-general');
    general_comment_title = $('#title-general');
    general_comment_title_cancel = $('#title-general-cancel');
    if(row_num === -2 && general_comment[0].style.display === 'none') {
        general_comment[0].style.display = '';
        general_comment_title[0].style.backgroundColor = "#e6e6e6";
        general_comment[0].style.backgroundColor = "#e6e6e6";
        general_comment_title_cancel[0].style.backgroundColor = "#e6e6e6";
        general_comment_summary[0].style.display = 'none';
        general_comment_cancel_mark[0].style.display = '';
        general_comment_save_mark[0].style.display = '';
        general_comment_title.attr('colspan', 3);
        general_comment_title_cancel[0].style.display = '';
        general_comment_title_cancel.attr('colspan', 1);
    } else {
        general_comment[0].style.display = 'none';
        general_comment_title[0].style.backgroundColor = "initial";
        general_comment[0].style.backgroundColor = "initial";
        general_comment_title_cancel[0].style.backgroundColor = "initial";
        general_comment_summary[0].style.display = '';
        general_comment_cancel_mark[0].style.display = 'none';
        general_comment_save_mark[0].style.display = 'none';
        general_comment_title.attr('colspan', 4);
        general_comment_title_cancel[0].style.display = 'none';
        general_comment_title_cancel.attr('colspan', 0);
    }
    for (var x = 1; x <= total_num; x++) {
        var current = $('#extra-' + x);
        var current_summary = $('#summary-' + x);
        var ta_note = $('#ta_note-' + x);
        var student_note = $('#student_note-' + x);
        var progress_points = $('#progress_points-' + x);
        var cancel_mark = $('#cancel-mark-' + x);
        var save_mark = $('#save-mark-' + x);
        var title = $('#title-' + x);
        var title_cancel = $('#title-cancel-' + x);
        var page = ($('#page-' + x)[0]).innerHTML;

        // update the color if it is penalty or extra credit
        var current_question_num = $('#grade-' + x);
        var question_points = parseFloat(current_question_num[0].innerHTML);
        if (question_points > parseFloat(current_question_num[0].dataset.max_points)) {
            current_summary.children("td:first-of-type")[0].style.backgroundColor = "#D8F2D8";
        } else if (question_points < 0) {
            current_summary.children("td:first-of-type")[0].style.backgroundColor = "#FAD5D3";
        } else {
            current_summary.children("td:first-of-type")[0].style.backgroundColor = "initial";
        }

        if (x == row_num) {
            if (current[0].style.display === 'none') {
                current[0].style.display = '';
                current[0].style.backgroundColor = "#e6e6e6";
                title[0].style.backgroundColor = "#e6e6e6";
                title_cancel[0].style.backgroundColor = "#e6e6e6";
                current_summary[0].style.display = 'none';
                ta_note[0].style.display = '';
                student_note[0].style.display = '';
                updateProgressPoints(x);
                progress_points[0].style.display = '';
                cancel_mark[0].style.display = '';
                save_mark[0].style.display = '';
                title.attr('colspan', 3);
                title_cancel[0].style.display = '';
                title_cancel.attr('colspan', 1);

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
                        directory = "submissions"; 
                        src = $("#"+iframeId).prop('src');
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
            } else {
                current[0].style.display = 'none';
                current_summary[0].style.display = '';
                current[0].style.backgroundColor = "initial";
                title[0].style.backgroundColor = "initial";
                title_cancel[0].style.backgroundColor = "initial";
                ta_note[0].style.display = 'none';
                student_note[0].style.display = 'none';
                progress_points[0].style.display = 'none';
                cancel_mark[0].style.display = 'none';
                save_mark[0].style.display = 'none';
                title.attr('colspan', 4);
                title_cancel[0].style.display = 'none';
                title_cancel.attr('colspan', 0);
            }
        } else {
            current[0].style.display = 'none';
            current_summary[0].style.display = '';
            current[0].style.backgroundColor = "initial";
            title[0].style.backgroundColor = "initial";
            title_cancel[0].style.backgroundColor = "initial";
            ta_note[0].style.display = 'none';
            student_note[0].style.display = 'none';
            progress_points[0].style.display = 'none';
            cancel_mark[0].style.display = 'none';
            save_mark[0].style.display = 'none';
            title.attr('colspan', 4);
            title_cancel[0].style.display = 'none';
            title_cancel.attr('colspan', 0);
        }
    }

    updateCookies();
}

//cancelMark gets the data from the database and reinsert its without saving
function cancelMark(num, gradeable_id, user_id, gc_id) {
    //-3 means gradeable comment
    if (num === -3) {
        var current_question_text = $('#rubric-textarea-custom');
        $.ajax({
            type: "POST",
            url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'get_gradeable_comment'}),
            data: {
                'gradeable_id' : gradeable_id,
                'anon_id' : user_id
            },
            success: function(data) {
                console.log("success for canceling gradeable comment");
                data = JSON.parse(data);
                $('#comment-general-id').val(data['data']);
            },
            error: function() {
                console.log("Couldn't get the gradeable comment");
                alert("Failed to cancel the comment");
            }
        })
    } else {
        //removes the new marks first
        var arr_length = $('tr[name=mark_'+num+']').length;
        for (var i = 0; i < arr_length; i++) {
            var current_row = $('#mark_id-'+num+'-'+i);
            var delete_mark = $('#mark_remove_id-'+num+'-'+i);
            if (typeof delete_mark[0] !== 'undefined') {
                current_row.remove();
            }
        }
        //gets the data in the database and applys it back
        var arr_length = $('tr[name=mark_'+num+']').length;
        $.ajax({
            type: "POST",
            url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'get_mark_data'}),
            data: {
                'gradeable_id' : gradeable_id,
                'anon_id' : user_id,
                'gradeable_component_id' : gc_id
            },
            success: function(data) {
                //if success reinput all the data back into the form
                console.log("success for canceling a mark");
                data = JSON.parse(data);
                for (var x = 0; x < arr_length; x++) {
                    current_row = $('#mark_id-'+num+'-'+x);
                    var is_selected = false;
                    if (data['data'][x]['has_mark'] === true) {
                        is_selected = true;
                    } else {
                        is_selected = false;
                    }
                    current_row.find('input[name=mark_points_'+num+'_'+x+']').val(data['data'][x]['score']);
                    current_row.find('textarea[name=mark_text_'+num+'_'+x+']').val(data['data'][x]['note']);
                    if (is_selected === true) {
                        if (current_row.find('i[name=mark_icon_'+num+'_'+x+']')[0].classList.contains('fa-square-o')) {
                            current_row.find('i[name=mark_icon_'+num+'_'+x+']').toggleClass("fa-square-o fa-square");
                        }
                    } else {
                        if (current_row.find('i[name=mark_icon_'+num+'_'+x+']')[0].classList.contains('fa-square')) {
                            current_row.find('i[name=mark_icon_'+num+'_'+x+']').toggleClass("fa-square-o fa-square");
                        }
                    }     
                }
                current_row = $('#mark_custom_id-'+num);
                current_row.find('input[name=mark_points_custom_'+num+']').val(data['data'][x]['custom_score']);
                current_row.find('textarea[name=mark_text_custom_'+num+']').val(data['data'][x]['custom_note']);
                if (current_row.find('input[name=mark_points_custom_'+num+']').val() == 0 && current_row.find('textarea[name=mark_text_custom_'+num+']').val() == "") {
                    if (current_row.find('i[name=mark_icon_'+num+'_custom]')[0].classList.contains('fa-square')) {
                        current_row.find('i[name=mark_icon_'+num+'_custom]').toggleClass("fa-square-o fa-square");
                    }
                } else {
                    if (current_row.find('i[name=mark_icon_'+num+'_custom]')[0].classList.contains('fa-square-o')) {
                        current_row.find('i[name=mark_icon_'+num+'_custom]').toggleClass("fa-square-o fa-square");
                    }
                }
            },
            error: function() {
                console.log("You make me sad. The cancel mark errored out.");
                alert("Failed to cancel the grade");
            }
        })
    }
}

//num === -3 means save gradeable comment
//num === -2 means save last opened component
//num === -1 means save all components, TO DO?
function saveMark(num, gradeable_id, user_id, active_version, gc_id = -1, your_user_id = "", sync = true) {
    if (num === -3) {
        var comment_row = $('#comment-general-id');
        var gradeable_comment = comment_row.val();
        var current_question_text = $('#rubric-textarea-custom');
        var overwrite = $('#overwrite-id').is(":checked");
        current_question_text[0].innerHTML = '<pre>' + gradeable_comment + '</pre>';

        $.ajax({
            type: "POST",
            url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'save_gradeable_comment'}),
            async: sync,
            data: {
                'gradeable_id' : gradeable_id,
                'anon_id' : user_id,
                'active_version' : active_version,
                'gradeable_comment' : gradeable_comment
            },
            success: function(data) {
                console.log("success for saving the gradeable comment");
            },
            error: function() {
                console.log("There was an error with saving the gradeable comment.");
                alert("There was an error with saving the comment. Please refresh the page and try agian.");
            }
        })
    }
    else if (num === -2) {
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
        if (found === true) { //if nothing was found, assumes it needs to save the gradeable comment
            var gradeable_component_id = parseInt($('#extra-' + index)[0].dataset.question_id);
            saveMark(index, gradeable_id, user_id, active_version, gradeable_component_id, your_user_id, sync);
        } else {
            saveMark(-3, gradeable_id, user_id, active_version, -1, your_user_id, sync);
        }
    }
    else if (num === -1) {

    }
    else {
        var arr_length = $('tr[name=mark_'+num+']').length;
        var mark_data = new Array(arr_length);

        for (var i = 0; i < arr_length; i++) {
            var current_row = $('#mark_id-'+num+'-'+i);
            var info_mark = $('#mark_info_id-'+num+'-'+i);
            var delete_mark = $('#mark_remove_id-'+num+'-'+i);
            var is_selected = false;
            var success = true;
            if (current_row.find('i[name=mark_icon_'+num+'_'+i+']')[0].classList.contains('fa-square')) {
                is_selected = true;
            }

            var mark = {
                points: current_row.find('input[name=mark_points_'+num+'_'+i+']').val(),
                note: current_row.find('textarea[name=mark_text_'+num+'_'+i+']').val(),
                order: i,
                selected: is_selected
            };
            mark_data[i] = mark;
            info_mark[0].style.display = '';
            delete_mark.remove();
        }
        current_row = $('#mark_custom_id-'+num);
        var custom_points = current_row.find('input[name=mark_points_custom_'+num+']').val();
        var custom_message = current_row.find('textarea[name=mark_text_custom_'+num+']').val();

        //updates the total number of points and text
        var current_question_num = $('#grade-' + num);
        var current_question_text = $('#rubric-textarea-' + num);
        var lower_clamp = parseFloat(current_question_num[0].dataset.lower_clamp);
        var current_points = parseFloat(current_question_num[0].dataset.default);
        var upper_clamp = parseFloat(current_question_num[0].dataset.upper_clamp);

        var new_text = "";
        var first_text = true;
        var all_false = true;
        for (var i = 0; i < arr_length; i++) {
            if(mark_data[i].selected === true) {
                all_false = false;
                current_points += parseFloat(mark_data[i].points);
                if(first_text === true) {
                    if (parseFloat(mark_data[i].points) == 0) {
                        new_text += "* " + mark_data[i].note;
                    } else {
                        new_text += "* (" + mark_data[i].points + ") " + mark_data[i].note;
                    }
                    first_text = false;
                }
                else {
                    if (parseFloat(mark_data[i].points) == 0) {
                        new_text += "\<br>* " + mark_data[i].note;
                    }
                    else {
                        new_text += "\<br>* (" + mark_data[i].points + ") "+ mark_data[i].note;
                    }
                    
                }
            }                
        }
        if (isNaN(parseFloat(custom_points))) {
            current_points += 0;
        }
        else {
            current_points += parseFloat(custom_points);
        }
        
        if (parseFloat(custom_points) != 0) {
            all_false = false;
        }
        if(custom_message != "") {
            if(first_text === true) {
                if (parseFloat(custom_points) == 0) {
                    new_text += "* " + custom_message;
                }
                else {
                    new_text += "* (" + custom_points + ") " + custom_message;
                } 
                first_text = false;
            }
            else {
                if (parseFloat(custom_points) == 0) {
                    new_text += "\<br>* " + custom_message;
                }
                else {
                    new_text += "\<br>* (" + custom_points + ") " + custom_message;
                }
            }
            all_false = false;
        }
        
        if(current_points < lower_clamp) {
            current_points = lower_clamp;
        }
        if(current_points > upper_clamp) {
            current_points = upper_clamp;
        }
        
        current_question_num[0].innerHTML = (all_false === false) ? current_points : "";
        current_question_text[0].innerHTML = new_text;

        calculatePercentageTotal();

        var overwrite = "false";
        if($('#overwrite-id').is(':checked')) {
            overwrite = "true";
        }
        else {
            overwrite = "false";
        }
        $.ajax({
            type: "POST",
            url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'save_one_component'}),
            async: sync,
            data: {
                'gradeable_id' : gradeable_id,
                'anon_id' : user_id,
                'gradeable_component_id' : gc_id,
                'num_mark' : arr_length,
                'active_version' : active_version,
                'custom_points' : custom_points,
                'custom_message' : custom_message,
                'overwrite' : overwrite,
                'marks' : mark_data,
            },
            success: function(data) {
                console.log("success for saving a mark");
                console.log(data);
                data = JSON.parse(data);
                if (data['modified'] === true) {
                    if (all_false === true) {
                        $('#graded-by-' + num)[0].innerHTML = "Ungraded!";
                    } else {
                        if($('#graded-by-' + num)[0].innerHTML === "Ungraded!" || (overwrite === "true")) {
                            $('#graded-by-' + num)[0].innerHTML = "Graded by " + your_user_id + "!";
                        }
                    }
                }
            },
            error: function() {
                console.log("Something went wront with saving marks...");
                alert("There was an error with saving the grade. Please refresh the page and try agian.");
            }
        })
    }
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
            return -2;
        } else {
            return -1;
        }
    }
}

//Used to reset users cookies
var cookie_version = 1;

//Set positions and visibility of configurable ui elements
$(document).ready(function(){

    //Check each cookie and test for 'undefined'. If any cookie is undefined
    $.each(document.cookie.split(/; */), function(){
        var cookie = this.split("=")
        if(!cookie[1] || cookie[1] == 'undefined'){
            deleteCookies();
        }
    });

    if(document.cookie.replace(/(?:(?:^|.*;\s*)cookie_version\s*\=\s*([^;]*).*$)|^.*$/, "$1") != cookie_version) {
        //If cookie version is not the same as the current version then toggle the visibility of each
        //rubric panel then update the cookies
        deleteCookies();
        handleKeyPress("KeyG");
        handleKeyPress("KeyA");
        handleKeyPress("KeyS");
        handleKeyPress("KeyO");
        handleKeyPress("KeyR");
        updateCookies();
    }
    else{
        readCookies();
    }

    $('body').css({'position':'fixed', 'width':'100%'});
    $('#header').css({'position':'fixed', 'z-index':'1099'});

    calculatePercentageTotal();
    var progressbar = $(".progressbar"),
        value = progressbar.val();
    $(".progress-value").html("<b>" + value + '%</b>');

    $( ".draggable" ).draggable({snap:false, grid:[2, 2], stack:".draggable"}).resizable();

    $(".draggable").on("dragstop", function(){
        updateCookies();
    });

    $(".draggable").on("resizestop", function(){
        updateCookies();
    });

    eraseCookie("reset");
});

function createCookie(name,value,seconds)  {
    if(seconds) {
        var date = new Date();
        date.setTime(date.getTime()+(seconds*1000));
        var expires = "; expires="+date.toGMTString();
    }
    else var expires = "";
    document.cookie = name+"="+value+expires+"; path=/";
}

function eraseCookie(name) {
    createCookie(name,"",-3600);
}

function deleteCookies(){
    $.each(document.cookie.split(/; */), function(){
        var cookie = this.split("=")
        if(!cookie[1] || cookie[1] == 'undefined'){
            document.cookie = cookie[0] + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            document.cookie = "cookie_version=-1; path=/;";
        }
    });
}

function readCookies(){
    var output_top = document.cookie.replace(/(?:(?:^|.*;\s*)output_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var output_left = document.cookie.replace(/(?:(?:^|.*;\s*)output_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var output_width = document.cookie.replace(/(?:(?:^|.*;\s*)output_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var output_height = document.cookie.replace(/(?:(?:^|.*;\s*)output_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var output_visible = document.cookie.replace(/(?:(?:^|.*;\s*)output_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var files_top = document.cookie.replace(/(?:(?:^|.*;\s*)files_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var files_left = document.cookie.replace(/(?:(?:^|.*;\s*)files_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var files_width = document.cookie.replace(/(?:(?:^|.*;\s*)files_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var files_height = document.cookie.replace(/(?:(?:^|.*;\s*)files_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var files_visible = document.cookie.replace(/(?:(?:^|.*;\s*)files_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var rubric_top = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var rubric_left = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var rubric_width = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var rubric_height = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var rubric_visible = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var status_top = document.cookie.replace(/(?:(?:^|.*;\s*)status_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var status_left = document.cookie.replace(/(?:(?:^|.*;\s*)status_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var status_width = document.cookie.replace(/(?:(?:^|.*;\s*)status_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var status_height = document.cookie.replace(/(?:(?:^|.*;\s*)status_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var status_visible = document.cookie.replace(/(?:(?:^|.*;\s*)status_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    (output_top) ? $("#autograding_results").css("top", output_top):{};
    (output_left) ? $("#autograding_results").css("left", output_left):{};
    (output_width) ? $("#autograding_results").css("width", output_width):{};
    (output_height) ? $("#autograding_results").css("height", output_height):{};
    (output_visible) ? $("#autograding_results").css("display", output_visible):{};

    (rubric_top) ? $("#grading_rubric").css("top", rubric_top):{};
    (rubric_left) ? $("#grading_rubric").css("left", rubric_left):{};
    (rubric_width) ? $("#grading_rubric").css("width", rubric_width):{};
    (rubric_height) ? $("#grading_rubric").css("height", rubric_height):{};
    (rubric_visible) ? $("#grading_rubric").css("display", rubric_visible):{};

    (files_top) ? $("#submission_browser").css("top", files_top):{};
    (files_left) ? $("#submission_browser").css("left", files_left):{};
    (files_width) ? $("#submission_browser").css("width", files_width):{};
    (files_height) ? $("#submission_browser").css("height", files_height):{};
    (files_visible) ? $("#submission_browser").css("display", files_visible):{};

    (status_top) ? $("#student_info").css("top", status_top):{};
    (status_left) ? $("#student_info").css("left", status_left):{};
    (status_width) ? $("#student_info").css("width", status_width):{};
    (status_height) ? $("#student_info").css("height", status_height):{};
    (status_visible) ? $("#student_info").css("display", status_visible):{};

    (output_visible) ? ((output_visible) == "none" ? $(".fa-list-alt").removeClass("icon-selected") : $(".fa-list-alt").addClass("icon-selected")) : {};
    (files_visible) ? ((files_visible) == "none" ? $(".fa-folder-open").removeClass("icon-selected") : $(".fa-folder-open").addClass("icon-selected")) : {};
    (rubric_visible) ? ((rubric_visible) == "none" ? $(".fa-pencil-square-o").removeClass("icon-selected") : $(".fa-pencil-square-o").addClass("icon-selected")) : {};
    (status_visible) ? ((status_visible) == "none" ? $(".fa-user").removeClass("icon-selected") : $(".fa-user").addClass("icon-selected")) : {};
}

function updateCookies(){
    document.cookie = "output_top=" + $("#autograding_results").css("top") + "; path=/;";
    document.cookie = "output_left=" + $("#autograding_results").css("left") + "; path=/;";
    document.cookie = "output_width=" + $("#autograding_results").css("width") + "; path=/;";
    document.cookie = "output_height=" + $("#autograding_results").css("height") + "; path=/;";
    document.cookie = "output_visible=" + $("#autograding_results").css("display") + "; path=/;";

    document.cookie = "rubric_top=" + $("#grading_rubric").css("top") + "; path=/;";
    document.cookie = "rubric_left=" + $("#grading_rubric").css("left") + "; path=/;";
    document.cookie = "rubric_width=" + $("#grading_rubric").css("width") + "; path=/;";
    document.cookie = "rubric_height=" + $("#grading_rubric").css("height") + "; path=/;";
    document.cookie = "rubric_visible=" + $("#grading_rubric").css("display") + "; path=/;";

    document.cookie = "files_top=" + $("#submission_browser").css("top") + "; path=/;";
    document.cookie = "files_left=" + $("#submission_browser").css("left") + "; path=/;";
    document.cookie = "files_width=" + $("#submission_browser").css("width") + "; path=/;";
    document.cookie = "files_height=" + $("#submission_browser").css("height") + "; path=/;";
    document.cookie = "files_visible=" + $("#submission_browser").css("display") + "; path=/;";

    document.cookie = "status_top=" + $("#student_info").css("top") + "; path=/;";
    document.cookie = "status_left=" + $("#student_info").css("left") + "; path=/;";
    document.cookie = "status_width=" + $("#student_info").css("width") + "; path=/;";
    document.cookie = "status_height=" + $("#student_info").css("height") + "; path=/;";
    document.cookie = "status_visible=" + $("#student_info").css("display") + "; path=/;";

    document.cookie = "cookie_version=" + cookie_version + "; path=/;";
}

window.onkeydown = function(e) {
    if (e.target.tagName == "TEXTAREA" || e.target.tagName == "INPUT" || e.target.tagName == "SELECT") return; // disable keyboard event when typing to textarea/input
    handleKeyPress(e.code);
};

function handleKeyPress(key) {
    switch (key) {
        case "KeyA":
            $('.fa-list-alt').toggleClass('icon-selected');
            $("#autograding_results").toggle();
            break;
        case "KeyG":
            $('.fa-pencil-square-o').toggleClass('icon-selected');
            $("#grading_rubric").toggle();
            break;
        case "KeyO":
            $('.fa-folder-open').toggleClass('icon-selected');
            $("#submission_browser").toggle();
            break;
        case "KeyS":
            $('.fa-user').toggleClass('icon-selected');
            $("#student_info").toggle();
            break;
        case "KeyR":
            $('.fa-list-alt').addClass('icon-selected');
            $("#autograding_results").attr("style", "left:15px; top:175px; width:48%; height:37%; display:block;");
            $('.fa-pencil-square-o').addClass('icon-selected');
            $("#grading_rubric").attr("style", "right:15px; top:175px; width:48%; height:37%; display:block;");
            $('.fa-folder-open').addClass('icon-selected');
            $("#submission_browser").attr("style", "left:15px; bottom:40px; width:48%; height:30%; display:block;");
            $('.fa-user').addClass('icon-selected');
            $("#student_info").attr("style", "right:15px; bottom:40px; width:48%; height:30%; display:block;");
            deleteCookies();
            updateCookies();
            break;
        default:
            break;
    }
    updateCookies();
};

// expand all files in Submissions and Results section
function openAll() {
    // click on all with the class openAllDiv that hasn't been expanded yet
    $(".openAllDiv").each(function() {
        if ($(this).parent().find('span').hasClass('fa-folder')) {
            $(this).click();
        }
    });

    // click on all with the class openAllFile that hasn't been expanded yet
    $(".openAllFile").each(function() {
        if($(this).find('span').hasClass('fa-plus-circle')) {
            $(this.click());
        }
    });
}

// close all files in Submission and results section
function closeAll() {
    // click on all with the class openAllFile that is expanded
    $(".openAllFile").each(function() {
        if($(this).find('span').hasClass('fa-minus-circle')) {
            $(this.click());
        }
    });

    // click on all with the class openAllDiv that is expanded
    $(".openAllDiv").each(function() {
        if ($(this).parent().find('span').hasClass('fa-folder-open')) {
            $(this).click();
        }
    });
}

function openDiv(num) {
    var elem = $('#div_viewer_' + num);
    if (elem.hasClass('open')) {
        elem.hide();
        elem.removeClass('open');
        $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder-open').addClass('fa-folder');
    }
    else {
        elem.show();
        elem.addClass('open');
        $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder').addClass('fa-folder-open');
    }
    return false;
}

function resizeFrame(id) {
    var height = parseInt($("iframe#" + id).contents().find("body").css('height').slice(0,-2));
    if (height > 500) {
        document.getElementById(id).height= "500px";
    }
    else {
        document.getElementById(id).height = (height+18) + "px";
    }
}

// delta in this function is the incremental step of points, currently hardcoded to 0.5pts
function validateInput(id, question_total, delta){
    var ele = $('#' + id);
    if(isNaN(parseFloat(ele.val())) || ele.val() == ""){
        ele.val("");
        return;
    }
    if(ele.val() < 0 && parseFloat(question_total) > 0) {
        ele.val( 0 );
    }
    if(ele.val() > 0 && parseFloat(question_total) < 0) {
        ele.val( 0 );
    }
    if(ele.val() < parseFloat(question_total) && parseFloat(question_total) < 0) {
        ele.val(question_total);
    }
    if(ele.val() > parseFloat(question_total) && parseFloat(question_total) > 0) {
        ele.val(question_total);
    }
    if(ele.val() % delta != 0) {
        ele.val( Math.round(ele.val() / delta) * delta );
    }
}

// autoresize the comment box
function autoResizeComment(e){
    e.target.style.height ="";
    e.target.style.height = e.target.scrollHeight + "px";
}

function downloadZip(grade_id, user_id) {
    window.location = buildUrl({'component': 'misc', 'page': 'download_zip', 'dir': 'submissions', 'gradeable_id': grade_id, 'user_id': user_id});
    return false;
}

function downloadFile(html_file, url_file) {
    url_file = decodeURIComponent(url_file);  
    directory = "";
    if (url_file.includes("submissions")) directory = "submissions";
    else if (url_file.includes("results")) directory = "results";      
    window.location = buildUrl({'component': 'misc', 'page': 'download_file', 'dir': directory, 'file': html_file, 'path': url_file});
    return false;
}

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

function addMark(me, num, background, min, max, precision) {
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
<td colspan="1" style="'+background+'; text-align: center;"> <input name="mark_points_'+num+'_'+new_num+'" type="number" onchange="fixMarkPointValue(this);" step="'+precision+'" value="0" min="'+min+'" max="'+max+'" style="width: 50%; resize:none;"> \
                    <span onclick="selectMark(this);"> <i class="fa fa-square-o mark" name="mark_icon_'+num+'_'+new_num+'" style="visibility: visible; cursor: pointer; position: relative; top: 2px;"></i> </span> \
</td> \
<td colspan="3" style="'+background+'"> \
    <textarea name="mark_text_'+num+'_'+new_num+'" onkeyup="autoResizeComment(event);" rows="1" style="width:95%; resize:none; float:left;"></textarea> \
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
        current_mark.find('span[id=mark_remove_id-'+num+'-'+i+']').attr('id', 'mark_remove_id-'+num+'-'+new_num);
        current_mark.attr('id', 'mark_id-'+num+'-'+new_num);
    }
}

//check if the first mark (Full/no credit) should be selected
function checkMarks(question_num) {
    question_num = parseInt(question_num);
    var mark_table = $('#extra-'+question_num);
    var first_mark = mark_table.find('i[name=mark_icon_'+question_num+'_0]');
    var all_false = true;
    mark_table.find('.mark').each(function() {
        if($(this)[0].classList.contains('fa-square')) {
            all_false = false;
            return false;
        }
    });

    if(all_false === true) {
        first_mark.toggleClass("fa-square-o fa-square");
    } else {
        if (first_mark[0].classList.contains('fa-square')) {
            first_mark.toggleClass("fa-square-o fa-square");
        }
    }
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

    icon.toggleClass("fa-square-o fa-square");
    if (skip === false) {
        checkMarks(question_num);
    }        
}

//closes all the questions except the one being opened
function openClose(row_id, num_questions) {
    var row_num = parseInt(row_id);
    var total_num = parseInt(num_questions);
    //-2 means general comment, else open the row_id with the number
    general_comment = document.getElementById('done-general');
    if(row_num === -2) {
        general_comment.style.display = '';
    } else {
        general_comment.style.display = 'none';
    }

    for (var x = 1; x <= num_questions; x++) {
        var current = document.getElementById('extra-' + x);
        var current_summary = document.getElementById('summary-' + x);
        var icon = document.getElementById('icon-' + x);
        var ta_note = document.getElementById('ta_note-' + x);
        var student_note = document.getElementById('student_note-' + x);
        if (x === row_num) {
            if (current.style.display === 'none') {
                current.style.display = '';
                current_summary.style.display = 'none';
                ta_note.style.display = '';
                student_note.style.display = '';
                if (icon.classList.contains('fa-window-maximize'))
                {
                    icon.classList.remove('fa-window-maximize');
                }
                if(!(icon.classList.contains('fa-window-close-o'))) {
                    icon.classList.add('fa-window-close-o');
                }
            } else {
                current.style.display = 'none';
                current_summary.style.display = '';
                ta_note.style.display = 'none';
                student_note.style.display = 'none';
                if (icon.classList.contains('fa-window-close-o'))
                {
                    icon.classList.remove('fa-window-close-o');
                }
                if(!(icon.classList.contains('fa-window-maximize'))) {
                    icon.classList.add('fa-window-maximize');
                }
            }
        } else {
            current.style.display = 'none';
            current_summary.style.display = '';
            ta_note.style.display = 'none';
            student_note.style.display = 'none';
            if (icon.classList.contains('fa-window-close-o'))
            {
                icon.classList.remove('fa-window-close-o');
            }
            if(!(icon.classList.contains('fa-window-maximize'))) {
                icon.classList.add('fa-window-maximize');
            }
        }
    }
}

function cancelMark(num, gradeable_id, user_id, gc_id) {
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
            'user_id' : user_id,
            'gradeable_component_id' : gc_id
        },
        success: function(data) {
            //if success reinput all the data back into the form
            console.log("success");
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
        }
    })
}

//num === -3 means save gradeable comment
//num === -2 means save last opened component
//num === -1 means save all components, TO DO?
function saveMark(num, gradeable_id, user_id, active_version, gc_id = -1) {
    if (num === -3) {
        var comment_row = $('#comment-general-id');
        var gradeable_comment = comment_row.val();
        $.ajax({
            type: "POST",
            url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'save_gradeable_comment'}),
            data: {
                'gradeable_id' : gradeable_id,
                'user_id' : user_id,
                'active_version' : active_version,
                'gradeable_comment' : gradeable_comment
            },
            success: function(data) {
                console.log("success");
            },
            error: function() {
                console.log("There was an error with saving the gradeable comment.");
            }
        })
    } else if (num === -2) {
        var index = 1;
        var found = false;
        var doesExist = ($('#icon-' + index).length) ? true : false;
        while(doesExist) {
            if($('#icon-' + index).length) {
                if ($('#icon-' + index)[0].classList.contains('fa-window-close-o')) {
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
            var gradeable_component_id = parseInt($('#icon-' + index)[0].dataset.question_id);
            saveMark(index, gradeable_id, user_id, active_version, gradeable_component_id);
        } else {
            saveMark(-3, gradeable_id, user_id, active_version);
        }
    } else if (num === -1) {

    } else {
        var arr_length = $('tr[name=mark_'+num+']').length;
        var mark_data = new Array(arr_length);
        var type = 0; //0 is deducation, 1 is addition
        var keep_checking = true;
        for (var i = 0; i < arr_length; i++) {
            var current_row = $('#mark_id-'+num+'-'+i);
            var delete_mark = $('#mark_remove_id-'+num+'-'+i);
            var is_selected = false;
            var success = true;
            if (current_row.find('i[name=mark_icon_'+num+'_'+i+']')[0].classList.contains('fa-square')) {
                is_selected = true;
            }

            if (keep_checking) {
                if(parseFloat(current_row.find('input[name=mark_points_'+num+'_'+i+']').val()) !== 0) {
                    if(parseFloat(current_row.find('input[name=mark_points_'+num+'_'+i+']').val()) > 0) {
                        type = 1;
                    }
                    else
                    {
                        type = 0;
                    }
                    keep_checking = false;
                }
            }

            var mark = {
                points: current_row.find('input[name=mark_points_'+num+'_'+i+']').val(),
                note: current_row.find('textarea[name=mark_text_'+num+'_'+i+']').val(),
                order: i,
                selected: is_selected
            };
            mark_data[i] = mark;
            delete_mark.remove();
        }
        current_row = $('#mark_custom_id-'+num);
        var custom_points = current_row.find('input[name=mark_points_custom_'+num+']').val();
        var custom_message = current_row.find('textarea[name=mark_text_custom_'+num+']').val();

        //updates the total number of points and text
        var current_question_num = $('#grade-' + num);
        var current_question_text = $('#rubric-textarea-' + num);
        var max_points = parseFloat(current_question_num[0].dataset.max_points);
        var current_points = 0;
        if (max_points < 0) { //is penalty
            current_points = (type === 0) ? 0 : max_points;
        } else {
            current_points = (type === 0) ? max_points : 0;
        }
        var new_text = "";
        var first_text = true;
        current_points = parseFloat(current_points);
        for (var i = 0; i < arr_length; i++) {
            if(mark_data[i].selected === true) {
                current_points += parseFloat(mark_data[i].points);
                if(first_text === true) {
                    new_text += "* " + mark_data[i].note;
                    first_text = false;
                } else {
                    new_text += "\<br>* " + mark_data[i].note;
                }
            }                
        }

        current_points += parseFloat(custom_points);
        if(custom_message != "") {
            if(first_text === true) {
                new_text += "* " + custom_message;
                first_text = false;
            } else {
                new_text += "\<br>* " + custom_message;
            }
        }
        
        if (max_points < 0) { //is penalty
            if (type === 0) {
                if (current_points < max_points) current_points = max_points;
            } else {
                if (current_points > 0) current_points = 0;
            }
        }
        else {
            if (type === 0) {
                if (current_points < 0) current_points = 0;
            } else {
                if (current_points > max_points) current_points = max_points;
            }
        }
        
        current_question_num[0].innerHTML = current_points;
        current_question_text[0].innerHTML = new_text;

        calculatePercentageTotal();

        var overwrite = "false";
        if($('#overwrite-id').is(':checked')) {
            overwrite = "true";
        } else {
            overwrite = "false";
        }

        $.ajax({
            type: "POST",
            url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'save_one_component'}),
            data: {
                'gradeable_id' : gradeable_id,
                'user_id' : user_id,
                'gradeable_component_id' : gc_id,
                'num_mark' : arr_length,
                'active_version' : active_version,
                'custom_points' : custom_points,
                'custom_message' : custom_message,
                'overwrite' : overwrite,
                marks : mark_data
            },
            success: function(data) {
                console.log("success");
                console.log(data);
                data = JSON.parse(data);
                if (data['modified'] === 'true') {
                    if(($('#graded-by-' + num)[0].innerHTML === "Ungraded!") || (overwrite === "true")) {
                        $('#graded-by-' + num)[0].innerHTML = "Graded by you!";
                    }
                }
            },
            error: function() {
                console.log("Something went wront with saving marks...");
            }
        })
    }
}


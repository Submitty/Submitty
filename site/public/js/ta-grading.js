//Used to reset users cookies
var cookie_version = 1;

//Set positions and visibility of configurable ui elements
$(function() {
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
        updateCookies();
        hideIfEmpty(".rubric_panel");
    }

    $('body').css({'position':'fixed', 'width':'100%'});

    calculatePercentageTotal();
    var progressbar = $(".progressbar"),
        value = progressbar.val();
    $(".progress-value").html("<b>" + value + '%</b>');

    $( ".draggable" ).draggable({snap:false, grid:[2, 2], stack:".draggable"}).resizable();

    $("#bar_wrapper").resizable("destroy"); //We don't want the toolbar to be resizable

    $(".draggable").on("dragstop", function(){
        $('#bar_wrapper').css({'z-index':'40'}); //Reset z-index after jquery drag
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

function onAjaxInit() {}

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

    var bar_wrapper_top = document.cookie.replace(/(?:(?:^|.*;\s*)bar_wrapper_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var bar_wrapper_left = document.cookie.replace(/(?:(?:^|.*;\s*)bar_wrapper_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var bar_wrapper_width = document.cookie.replace(/(?:(?:^|.*;\s*)bar_wrapper_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var bar_wrapper_height = document.cookie.replace(/(?:(?:^|.*;\s*)bar_wrapper_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var bar_wrapper_visible = document.cookie.replace(/(?:(?:^|.*;\s*)bar_wrapper_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var overwrite = document.cookie.replace(/(?:(?:^|.*;\s*)overwrite\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var autoscroll = document.cookie.replace(/(?:(?:^|.*;\s*)autoscroll\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var opened_mark = document.cookie.replace(/(?:(?:^|.*;\s*)opened_mark\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var scroll_pixel = document.cookie.replace(/(?:(?:^|.*;\s*)scroll_pixel\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var testcases = document.cookie.replace(/(?:(?:^|.*;\s*)testcases\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var files = document.cookie.replace(/(?:(?:^|.*;\s*)files\s*\=\s*([^;]*).*$)|^.*$/, "$1");

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

    (bar_wrapper_top) ? $("#bar_wrapper").css("top", bar_wrapper_top):{};
    (bar_wrapper_left) ? $("#bar_wrapper").css("left", bar_wrapper_left):{};
    (bar_wrapper_width) ? $("#bar_wrapper").css("width", bar_wrapper_width):{};
    (bar_wrapper_height) ? $("#bar_wrapper").css("height", bar_wrapper_height):{};
    (bar_wrapper_visible) ? $("#bar_wrapper").css("display", bar_wrapper_visible):{};

    (output_visible) ? ((output_visible) == "none" ? $(".fa-list-alt").removeClass("icon-selected") : $(".fa-list-alt").addClass("icon-selected")) : {};
    (files_visible) ? ((files_visible) == "none" ? $(".fa-folder-open").removeClass("icon-selected") : $(".fa-folder-open").addClass("icon-selected")) : {};
    (rubric_visible) ? ((rubric_visible) == "none" ? $(".fa-pencil-square-o").removeClass("icon-selected") : $(".fa-pencil-square-o").addClass("icon-selected")) : {};
    (status_visible) ? ((status_visible) == "none" ? $(".fa-user").removeClass("icon-selected") : $(".fa-user").addClass("icon-selected")) : {};

    (overwrite) ? ((overwrite) == "on" ? $('#overwrite-id').prop('checked', true) : $('#overwrite-id').prop('checked', false)) : {};

    (autoscroll) ? ((autoscroll) == "on" ? $('#autoscroll_id').prop('checked', true) : $('#autoscroll_id').prop('checked', false)) : {};
    if (autoscroll == "on") {
        onAjaxInit = function() {
            $('#title-'+opened_mark).click();
            
            if (scroll_pixel > 0) {
                document.getElementById('grading_rubric').scrollTop = scroll_pixel;
            }
        }
        
        var testcases_array = JSON.parse(testcases);
        testcases_array.forEach(function(element) {
            var id = 'testcase_' + element;
            if ($("#" + id).attr("style") == "display: none;") {
                toggleDiv(id);
            }
        });
        
        var files_array = JSON.parse(files);
        files_array.forEach(function(element) {
            var file_path = element.split('#$SPLIT#$');
            var current = $('#file-container');
            for (var x = 0; x < file_path.length; x++) {
                current.children().each(function() {
                    if (x == file_path.length - 1) {
                        $(this).children('div[id^=file_viewer_]').each(function() {
                            if ($(this)[0].dataset.file_name == file_path[x] && !$($(this)[0]).hasClass('open')) {
                                openFrame($(this)[0].dataset.file_name, $(this)[0].dataset.file_url, $(this).attr('id').split('_')[2]);
                            }
                        });
                        $(this).children('div[id^=div_viewer_]').each(function() {
                            if ($(this)[0].dataset.file_name == file_path[x] && !$($(this)[0]).hasClass('open')) {
                                openDiv($(this).attr('id').split('_')[2]);
                            }
                        });
                    } else {
                        $(this).children('div[id^=div_viewer_]').each(function() {
                            if ($(this)[0].dataset.file_name == file_path[x]) {
                                current = $(this);
                                return false;
                            }
                        });                        
                    }
                });
            }
        });
    }
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

    document.cookie = "bar_wrapper_top=" + $("#bar_wrapper").css("top") + "; path=/;";
    document.cookie = "bar_wrapper_left=" + $("#bar_wrapper").css("left") + "; path=/;";
    document.cookie = "bar_wrapper_width=" + $("#bar_wrapper").css("width") + "; path=/;";
    document.cookie = "bar_wrapper_height=" + $("#bar_wrapper").css("height") + "; path=/;";
    document.cookie = "bar_wrapper_visible=" + $("#bar_wrapper").css("display") + "; path=/;";

    var overwrite = "on";
    if ($('#overwrite-id').is(":checked")) {
        overwrite = "on";
    } else {
        overwrite = "off";
    }
    document.cookie = "overwrite=" + overwrite + "; path=/;";

    var autoscroll = "on";
    if ($('#autoscroll_id').is(":checked")) {
        autoscroll = "on";
    } else {
        autoscroll = "off";
    }
    document.cookie = "autoscroll=" + autoscroll + "; path=/;";
    document.cookie = "opened_mark=" + findCurrentOpenedMark() + "; path=/;";
    if (findCurrentOpenedMark() > 0 || findCurrentOpenedMark() == -2) {
        if (findCurrentOpenedMark() == -2) {
            var current_mark = document.getElementById('title-general');
        } else {
            var current_mark = document.getElementById('title-' + findCurrentOpenedMark());
        }
        var top_pos = current_mark.offsetTop;
        var rubric_table = document.getElementById('rubric-table');
        rubric_table = rubric_table.parentElement;
        top_pos += rubric_table.offsetTop;
        document.cookie = "scroll_pixel=" + top_pos + "; path=/;";
    } else {
        document.cookie = "scroll_pixel=" + 0 + "; path=/;";
    }

    var testcases = findOpenTestcases();
    testcases = JSON.stringify(testcases); 
    document.cookie = "testcases=" + testcases + "; path=/;";

    var files = [];
    $('#file-container').children().each(function() {
        $(this).children('div[id^=div_viewer_]').each(function() {
            files = files.concat(findAllOpenedFiles($(this), "", $(this)[0].dataset.file_name, [], true));
        });
    });
    files = JSON.stringify(files); 
    document.cookie = "files=" + files + "; path=/;"

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
            hideIfEmpty("#autograding_results");
            break;
        case "KeyG":
            $('.fa-pencil-square-o').toggleClass('icon-selected');
            $("#grading_rubric").toggle();
            hideIfEmpty("#grading_rubric");
            break;
        case "KeyO":
            $('.fa-folder-open.icon-header').toggleClass('icon-selected');
            $("#submission_browser").toggle();
            hideIfEmpty("#submission_browser");
            break;
        case "KeyS":
            $('.fa-user').toggleClass('icon-selected');
            $("#student_info").toggle();
            hideIfEmpty("#student_info");
            break;
        case "KeyR":
            $('.fa-list-alt').addClass('icon-selected');
            $("#autograding_results").attr("style", "z-index:30; left:15px; top:175px; width:48%; height:37%; display:block;");
            $('.fa-pencil-square-o').addClass('icon-selected');
            $("#grading_rubric").attr("style", "right:15px; z-index:30; top:175px; width:48%; height:37%; display:block;");
            $('.fa-folder-open').addClass('icon-selected');
            $("#submission_browser").attr("style", "left:15px; z-index:30; bottom:40px; width:48%; height:30%; display:block;");
            $('.fa-user').addClass('icon-selected');
            $('#bar_wrapper').attr("style", "top: -90px;left: 45%; z-index:40;");
            $("#student_info").attr("style", "right:15px; bottom:40px; z-index:30; width:48%; height:30%; display:block;");
            updateHandle("#autograding_results");
            updateHandle("#grading_rubric");
            updateHandle("#submission_browser");
            updateHandle("#student_info");
            hideIfEmpty(".rubric_panel");
            deleteCookies();
            updateCookies();
            break;
        default:
            break;
    }
    updateCookies();
};

// expand all files in Submissions and Results section
function openAll(click_class, class_modifier) {
    $("."+click_class + class_modifier).each(function(){
        $(this).click();
    });
}
function updateValue(obj, option1, option2) {
    // Switches the value of an element between option 1 and two
    console.log('hi');
    obj.text(function(i, oldText){
        if(oldText.indexOf(option1) >= 0){
            newText = oldText.replace(option1, option2);
        } else{
            newText = oldText.replace(option2, option1);
        }
        return newText;
    });

}

// expand all outputs in Auto-Grading Testcases section
function openAllAutoGrading() {
    // show all divs whose id starts with testcase_
    $("[id^='testcase_']").show();
}

// close all outputs in Auto-Grading Testcases section
function closeAllAutoGrading() {
    // hide all divs whose id starts with testcase_
    $("[id^='testcase_']").hide();
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
    var directory = "";
    if (url_file.includes("submissions")) {
      directory = "submissions";
    }
    else if (url_file.includes("results")) {
      directory = "results";
    }
    window.location = buildUrl({'component': 'misc', 'page': 'download_file', 'dir': directory, 'file': html_file, 'path': url_file});
    return false;
}

function updateHandle(element) {
    var bottom_e = $(element).scrollTop();
    var padding = $(element).outerHeight() - $(element).innerHeight();
    var height = $(element).prop('scrollHeight') - padding;
    var bottom_s = $(element).scrollTop() + $(element).prop('clientHeight');
    var bottom_s = Math.min(height, bottom_s);
    var bottom_se = bottom_s - 20;
    var bottom_se = Math.min(height, bottom_se);
    $(element).find('.ui-resizable-e').css('top', bottom_e + 'px');
    $(element).find('.ui-resizable-s').css('top', bottom_s + 'px');
    $(element).find('.ui-resizable-se').css('bottom', '0px');
}

function hideIfEmpty(element) {
    $(element).each(function() {
        if ($(this).hasClass("empty")) {
            $(this).hide();
        }
    });
}

function findOpenTestcases() {
    var testcase_num = [];
    var current_testcase;
    $(".box").each(function() {
        current_testcase = $(this).find('div[id^=testcase_]');
        if (typeof current_testcase[0] !== 'undefined'){
            if (current_testcase[0].style.display != 'none' ) {
                testcase_num.push(parseInt(current_testcase.attr('id').split('_')[1]));
            }         
        }
    });
    return testcase_num;
}

//finds all the open files and folder and stores them in stored_paths
function findAllOpenedFiles(elem, current_path, path, stored_paths, first) {
    if (first === true) {
        current_path += path;
        if ($(elem)[0].classList.contains('open')) {       
            stored_paths.push(path);
        }  
        else {
            return [];
        }
    } else {
        current_path += "#$SPLIT#$" + path;
    }

    $(elem).children().each(function() {
        $(this).children('div[id^=file_viewer_]').each(function() {
            if ($(this)[0].classList.contains('shown')) {
                stored_paths.push((current_path + "#$SPLIT#$" + $(this)[0].dataset.file_name));
            }
        });
        
    });

    $(elem).children().each(function() {
        $(this).children('div[id^=div_viewer_]').each(function() {
            if ($(this)[0].classList.contains('open')) {
                stored_paths.push((current_path + "#$SPLIT#$" + $(this)[0].dataset.file_name));
                stored_paths = findAllOpenedFiles($(this), current_path, $(this)[0].dataset.file_name, stored_paths, false);
            }
        });
    });

    return stored_paths;
}

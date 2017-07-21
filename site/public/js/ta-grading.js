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

    (output_visible) ? ((output_visible) == "none" ? $(".icon-auto-grading-results").removeClass("icon-selected") : $(".icon-auto-grading-results").addClass("icon-selected")) : {};
    (files_visible) ? ((files_visible) == "none" ? $(".icon-files").removeClass("icon-selected") : $(".icon-files").addClass("icon-selected")) : {};
    (rubric_visible) ? ((rubric_visible) == "none" ? $(".icon-grading-panel").removeClass("icon-selected") : $(".icon-grading-panel").addClass("icon-selected")) : {};
    (status_visible) ? ((status_visible) == "none" ? $(".icon-status").removeClass("icon-selected") : $(".icon-status").addClass("icon-selected")) : {};
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
            $('.icon-auto-grading-results').toggleClass('icon-selected');
            $("#autograding_results").toggle();
            break;
        case "KeyG":
            $('.icon-grading-panel').toggleClass('icon-selected');
            $("#grading_rubric").toggle();
            break;
        case "KeyO":
            $('.icon-files').toggleClass('icon-selected');
            $("#submission_browser").toggle();
            break;
        case "KeyS":
            $('.icon-status').toggleClass('icon-selected');
            $("#student_info").toggle();
            break;
        case "KeyR":
            $('.icon-auto-grading-results').addClass('icon-selected');
            $("#autograding_results").attr("style", "left:15px; top:175px; width:48%; height:37%; display:block;");
            $('.icon-grading-panel').addClass('icon-selected');
            $("#grading_rubric").attr("style", "right:15px; top:175px; width:48%; height:37%; display:block;");
            $('.icon-files').addClass('icon-selected');
            $("#submission_browser").attr("style", "left:15px; bottom:40px; width:48%; height:30%; display:block;");
            $('.icon-status').addClass('icon-selected');
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

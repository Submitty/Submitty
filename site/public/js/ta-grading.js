//Used to reset users cookies
var cookie_version = 1;

//Check if cookie version is/is not the same as the current version
var versionMatch = false;
//Set positions and visibility of configurable ui elements
$(function() {
    //Check each cookie and test for 'undefined'. If any cookie is undefined
    $.each(document.cookie.split(/; */), function(){
        var cookie = this.split("=")
        if(!cookie[1] || cookie[1] == 'undefined'){
            deleteCookies();
        }
        else if(cookie[0] === "cookie_version"){
            if(cookie[1] == cookie_version){
                versionMatch = true;
            }
        }
    });
    if(!versionMatch) {
        //If cookie version is not the same as the current version then toggle the visibility of each
        //rubric panel then update the cookies
        deleteCookies();
        setAutogradingVisible(true);
        setRubricVisible(true);
        setSubmissionsVisible(true);
        setInfoVisible(true);
        setRegradeVisible(true);
        setDiscussionVisible(true);
        setPeerVisible(false);
        resetModules();
    }
   else{
        readCookies();
    }

    //bring regrade panel to the front if grade inquiry is pending
    if ($(".fa-exclamation")[0]) {
      if (!isRegradeVisible())
        toggleRegrade();
      $('#regrade_info').css({'z-index':'40'});
    }
    updateCookies();

    $('body').css({'position':'fixed', 'width':'100%'});

    var progressbar = $(".progressbar"),
        value = progressbar.val();
    $(".progress-value").html("<b>" + value + '%</b>');

    $(".draggable").draggable({
        snap: false,
        grid: [2, 2],
        stack: ".draggable",
        cancel: "input,textarea,button,select,option,div#file_content,div#size_selector_menu"
    }).resizable();


    $("#bar_wrapper").resizable("destroy"); //We don't want the toolbar to be resizable
    // $('#pdf_annotation_bar').length != 0 && $('#pdf_annotation_bar').resizable("destroy"); //Same with PDF annotation.

    $(".draggable").on("dragstop", function(){
        $('#bar_wrapper').css({'z-index':'40'}); //Reset z-index after jquery drag
        // $('#pdf_annotation_bar').length != 0 && $('#pdf_annotation_bar').css({'z-index':'40'});
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
    var output_z_index = document.cookie.replace(/(?:(?:^|.*;\s*)output_z_index\s*\=\s*([^;]*).*$)|^.*$/, "$1");


    var files_top = document.cookie.replace(/(?:(?:^|.*;\s*)files_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var files_left = document.cookie.replace(/(?:(?:^|.*;\s*)files_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var files_width = document.cookie.replace(/(?:(?:^|.*;\s*)files_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var files_height = document.cookie.replace(/(?:(?:^|.*;\s*)files_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var files_visible = document.cookie.replace(/(?:(?:^|.*;\s*)files_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var files_z_index = document.cookie.replace(/(?:(?:^|.*;\s*)files_z_index\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var rubric_top = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var rubric_left = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var rubric_width = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var rubric_height = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var rubric_visible = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var rubric_z_index = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_z_index\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var status_top = document.cookie.replace(/(?:(?:^|.*;\s*)status_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var status_left = document.cookie.replace(/(?:(?:^|.*;\s*)status_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var status_width = document.cookie.replace(/(?:(?:^|.*;\s*)status_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var status_height = document.cookie.replace(/(?:(?:^|.*;\s*)status_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var status_visible = document.cookie.replace(/(?:(?:^|.*;\s*)status_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var status_z_index = document.cookie.replace(/(?:(?:^|.*;\s*)status_z_index\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var regrade_top = document.cookie.replace(/(?:(?:^|.*;\s*)regrade_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var regrade_left = document.cookie.replace(/(?:(?:^|.*;\s*)regrade_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var regrade_width = document.cookie.replace(/(?:(?:^|.*;\s*)regrade_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var regrade_height = document.cookie.replace(/(?:(?:^|.*;\s*)regrade_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var regrade_visible = document.cookie.replace(/(?:(?:^|.*;\s*)regrade_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var regrade_z_index = document.cookie.replace(/(?:(?:^|.*;\s*)regrade_z_index\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var discussion_top = document.cookie.replace(/(?:(?:^|.*;\s*)discussion_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var discussion_left = document.cookie.replace(/(?:(?:^|.*;\s*)discussion_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var discussion_width = document.cookie.replace(/(?:(?:^|.*;\s*)discussion_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var discussion_height = document.cookie.replace(/(?:(?:^|.*;\s*)discussion_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var discussion_visible = document.cookie.replace(/(?:(?:^|.*;\s*)discussion_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var discussion_z_index = document.cookie.replace(/(?:(?:^|.*;\s*)discussion_z_index\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var peer_top = document.cookie.replace(/(?:(?:^|.*;\s*)peer_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var peer_left = document.cookie.replace(/(?:(?:^|.*;\s*)peer_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var peer_width = document.cookie.replace(/(?:(?:^|.*;\s*)peer_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var peer_height = document.cookie.replace(/(?:(?:^|.*;\s*)peer_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var peer_visible = document.cookie.replace(/(?:(?:^|.*;\s*)peer_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var peer_z_index = document.cookie.replace(/(?:(?:^|.*;\s*)peer_z_index\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var bar_wrapper_top = document.cookie.replace(/(?:(?:^|.*;\s*)bar_wrapper_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var bar_wrapper_left = document.cookie.replace(/(?:(?:^|.*;\s*)bar_wrapper_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var bar_wrapper_visible = document.cookie.replace(/(?:(?:^|.*;\s*)bar_wrapper_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var silent_edit_enabled = document.cookie.replace(/(?:(?:^|.*;\s*)silent_edit_enabled\s*\=\s*([^;]*).*$)|^.*$/, "$1") === 'true';

    // var pdf_annotation_bar_top = document.cookie.replace(/(?:(?:^|.*;\s*)pdf_annotation_bar_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    // var pdf_annotation_bar_left = document.cookie.replace(/(?:(?:^|.*;\s*)pdf_annotation_bar_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");

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
    (output_z_index) ? $("#autograding_results").css("z-index", output_z_index):{};

    (rubric_top) ? $("#grading_rubric").css("top", rubric_top):{};
    (rubric_left) ? $("#grading_rubric").css("left", rubric_left):{};
    (rubric_width) ? $("#grading_rubric").css("width", rubric_width):{};
    (rubric_height) ? $("#grading_rubric").css("height", rubric_height):{};
    (rubric_visible) ? $("#grading_rubric").css("display", rubric_visible):{};
    (rubric_z_index) ? $("#grading_rubric").css("z-index", rubric_z_index):{};

    (files_top) ? $("#submission_browser").css("top", files_top):{};
    (files_left) ? $("#submission_browser").css("left", files_left):{};
    (files_width) ? $("#submission_browser").css("width", files_width):{};
    (files_height) ? $("#submission_browser").css("height", files_height):{};
    (files_visible) ? $("#submission_browser").css("display", files_visible):{};
    (files_z_index) ? $("#submission_browser").css("z-index", files_z_index):{};

    (status_top) ? $("#student_info").css("top", status_top):{};
    (status_left) ? $("#student_info").css("left", status_left):{};
    (status_width) ? $("#student_info").css("width", status_width):{};
    (status_height) ? $("#student_info").css("height", status_height):{};
    (status_visible) ? $("#student_info").css("display", status_visible):{};
    (status_z_index) ? $("#student_info").css("z-index", status_z_index):{};

    (regrade_top) ? $("#regrade_info").css("top", regrade_top):{};
    (regrade_left) ? $("#regrade_info").css("left", regrade_left):{};
    (regrade_width) ? $("#regrade_info").css("width", regrade_width):{};
    (regrade_height) ? $("#regrade_info").css("height", regrade_height):{};
    (regrade_visible) ? $("#regrade_info").css("display", regrade_visible):{};
    (regrade_z_index) ? $("#regrade_info").css("z-index", regrade_z_index):{};

    (discussion_top) ? $("#discussion_browser").css("top", discussion_top):{};
    (discussion_left) ? $("#discussion_browser").css("left", discussion_left):{};
    (discussion_width) ? $("#discussion_browser").css("width", discussion_width):{};
    (discussion_height) ? $("#discussion_browser").css("height", discussion_height):{};
    (discussion_visible) ? $("#discussion_browser").css("display", discussion_visible):{};
    (discussion_z_index) ? $("#discussion_browser").css("z-index", discussion_z_index):{};

    (peer_top) ? $("#peer_info").css("top", peer_top):{};
    (peer_left) ? $("#peer_info").css("left", peer_left):{};
    (peer_width) ? $("#peer_info").css("width", peer_width):{};
    (peer_height) ? $("#peer_info").css("height", peer_height):{};
    (peer_visible) ? $("#peer_info").css("display", peer_visible):{};
    (peer_z_index) ? $("#peer_info").css("z-index", peer_z_index):{};

    (bar_wrapper_top) ? $("#bar_wrapper").css("top", bar_wrapper_top):{};
    (bar_wrapper_left) ? $("#bar_wrapper").css("left", bar_wrapper_left):{};
    (bar_wrapper_visible) ? $("#bar_wrapper").css("display", bar_wrapper_visible):{};

    $('#silent-edit-id').prop('checked', silent_edit_enabled);

    // (pdf_annotation_bar_top) ? $("#pdf_annotation_bar").css("top", pdf_annotation_bar_top):{};
    // (pdf_annotation_bar_left) ? $("#pdf_annotation_bar").css("left", pdf_annotation_bar_left):{};

    (output_visible) ? ((output_visible) == "none" ? $(".grading_toolbar .fa-list").removeClass("icon-selected") : $(".grading_toolbar .fa-list").addClass("icon-selected")) : {};
    (files_visible) ? ((files_visible) == "none" ? $(".grading_toolbar .fa-folder-open").removeClass("icon-selected") : $(".grading_toolbar .fa-folder-open").addClass("icon-selected")) : {};
    (rubric_visible) ? ((rubric_visible) == "none" ? $(".grading_toolbar .fa-edit").removeClass("icon-selected") : $(".grading_toolbar .fa-edit").addClass("icon-selected")) : {};
    (status_visible) ? ((status_visible) == "none" ? $(".grading_toolbar .fa-user").removeClass("icon-selected") : $(".grading_toolbar .fa-user").addClass("icon-selected")) : {};
    (regrade_visible) ? ((regrade_visible) == "none" ? $(".grading_toolbar .grade_inquiry_icon").removeClass("icon-selected") : $(".grading_toolbar .grade_inquiry_icon").addClass("icon-selected")) : {};
    (discussion_visible) ? ((discussion_visible) == "none" ? $(".grading_toolbar .fa-comment-alt").removeClass("icon-selected") : $(".grading_toolbar .fa-comment-alt").addClass("icon-selected")) : {};
    (peer_visible) ? ((peer_visible) == "none" ? $(".grading_toolbar .fa-users").removeClass("icon-selected") : $(".grading_toolbar .fa-users").addClass("icon-selected")) : {};

    (autoscroll) ? ((autoscroll) == "on" ? $('#autoscroll_id').prop('checked', true) : $('#autoscroll_id').prop('checked', false)) : {};

    onAjaxInit = function() {
        $('#title-'+opened_mark).click();
        if (scroll_pixel > 0) {
            document.getElementById('grading_rubric').scrollTop = scroll_pixel;
        }
    }

    if (autoscroll == "on") {
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
                    }
                    else {
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
    for(var x=0; x<testcases.length; x++){
        if(testcases[x]!='[' && testcases[x]!=']')
            openAutoGrading(testcases[x]);
    }
}

function updateCookies(){
    document.cookie = "output_top=" + $("#autograding_results").css("top") + "; path=/;";
    document.cookie = "output_left=" + $("#autograding_results").css("left") + "; path=/;";
    document.cookie = "output_width=" + $("#autograding_results").css("width") + "; path=/;";
    document.cookie = "output_height=" + $("#autograding_results").css("height") + "; path=/;";
    document.cookie = "output_visible=" + $("#autograding_results").css("display") + "; path=/;";
    document.cookie = "output_z_index=" + $("#autograding_results").css("z-index") + "; path=/;";

    document.cookie = "rubric_top=" + $("#grading_rubric").css("top") + "; path=/;";
    document.cookie = "rubric_left=" + $("#grading_rubric").css("left") + "; path=/;";
    document.cookie = "rubric_width=" + $("#grading_rubric").css("width") + "; path=/;";
    document.cookie = "rubric_height=" + $("#grading_rubric").css("height") + "; path=/;";
    document.cookie = "rubric_visible=" + $("#grading_rubric").css("display") + "; path=/;";
    document.cookie = "rubric_z_index=" + $("#grading_rubric").css("z-index") + "; path=/;";

    document.cookie = "files_top=" + $("#submission_browser").css("top") + "; path=/;";
    document.cookie = "files_left=" + $("#submission_browser").css("left") + "; path=/;";
    document.cookie = "files_width=" + $("#submission_browser").css("width") + "; path=/;";
    document.cookie = "files_height=" + $("#submission_browser").css("height") + "; path=/;";
    document.cookie = "files_visible=" + $("#submission_browser").css("display") + "; path=/;";
    document.cookie = "files_z_index=" + $("#submission_browser").css("z-index") + "; path=/;";

    document.cookie = "status_top=" + $("#student_info").css("top") + "; path=/;";
    document.cookie = "status_left=" + $("#student_info").css("left") + "; path=/;";
    document.cookie = "status_width=" + $("#student_info").css("width") + "; path=/;";
    document.cookie = "status_height=" + $("#student_info").css("height") + "; path=/;";
    document.cookie = "status_visible=" + $("#student_info").css("display") + "; path=/;";
    document.cookie = "status_z_index=" + $("#student_info").css("z-index") + "; path=/;";

    document.cookie = "regrade_top=" + $("#regrade_info").css("top") + "; path=/;";
    document.cookie = "regrade_left=" + $("#regrade_info").css("left") + "; path=/;";
    document.cookie = "regrade_width=" + $("#regrade_info").css("width") + "; path=/;";
    document.cookie = "regrade_height=" + $("#regrade_info").css("height") + "; path=/;";
    document.cookie = "regrade_visible=" + $("#regrade_info").css("display") + "; path=/;";
    document.cookie = "regrade_z_index=" + $("#regrade_info").css("z-index") + "; path=/;";

    document.cookie = "discussion_top=" + $("#discussion_browser").css("top") + "; path=/;";
    document.cookie = "discussion_left=" + $("#discussion_browser").css("left") + "; path=/;";
    document.cookie = "discussion_width=" + $("#discussion_browser").css("width") + "; path=/;";
    document.cookie = "discussion_height=" + $("#discussion_browser").css("height") + "; path=/;";
    document.cookie = "discussion_visible=" + $("#discussion_browser").css("display") + "; path=/;";
    document.cookie = "discussion_z_index=" + $("#discussion_browser").css("z-index") + "; path=/;";

    document.cookie = "peer_top=" + $("#peer_info").css("top") + "; path=/;";
    document.cookie = "peer_left=" + $("#peer_info").css("left") + "; path=/;";
    document.cookie = "peer_width=" + $("#peer_info").css("width") + "; path=/;";
    document.cookie = "peer_height=" + $("#peer_info").css("height") + "; path=/;";
    document.cookie = "peer_visible=" + $("#peer_info").css("display") + "; path=/;";
    document.cookie = "peer_z_index=" + $("#peer_info").css("z-index") + "; path=/;";

    document.cookie = "bar_wrapper_top=" + $("#bar_wrapper").css("top") + "; path=/;";
    document.cookie = "bar_wrapper_left=" + $("#bar_wrapper").css("left") + "; path=/;";
    document.cookie = "bar_wrapper_visible=" + $("#bar_wrapper").css("display") + "; path=/;";

    document.cookie = "silent_edit_enabled=" + isSilentEditModeEnabled() + "; path=/;";

    // document.cookie = "pdf_annotation_bar_top=" + $("#pdf_annotation_bar").css("top") + "; path=/;";
    // document.cookie = "pdf_annotation_bar_left=" + $("#pdf_annotation_bar").css("left") + "; path=/;";

    var autoscroll = "on";
    if ($('#autoscroll_id').is(":checked")) {
        autoscroll = "on";
    }
    else {
        autoscroll = "off";
    }
    document.cookie = "autoscroll=" + autoscroll + "; path=/;";

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

//-----------------------------------------------------------------------------
// Student navigation
function gotoPrevStudent(to_ungraded = false) {

    var selector;
    var window_location;

    if(to_ungraded === true) {
        selector = "#prev-ungraded-student";
        window_location = $(selector)[0].dataset.href;

        // Append extra get param
        window_location += '&component_id=' + getFirstOpenComponentId();

    }
    else {
        selector = "#prev-student";
        window_location = $(selector)[0].dataset.href
    }

    if (getGradeableId() !== '') {
        closeAllComponents(true).then(function () {
            window.location = window_location;
        }).catch(function () {
            if (confirm("Could not save open component, change student anyway?")) {
                window.location = window_location;
            }
        });
    }
    else {
        window.location = window_location;
    }
}

function gotoNextStudent(to_ungraded = false) {

    var selector;
    var window_location;

    if(to_ungraded === true) {
        selector = "#next-ungraded-student";
        window_location = $(selector)[0].dataset.href;

        // Append extra get param
        window_location += '&component_id=' + getFirstOpenComponentId();
    }
    else {
        selector = "#next-student";
        window_location = $(selector)[0].dataset.href
    }

    if (getGradeableId() !== '') {
        closeAllComponents(true).then(function () {
            window.location = window_location;
        }).catch(function () {
            if (confirm("Could not save open component, change student anyway?")) {
                window.location = window_location;
            }
        });
    }
    else {
        window.location = window_location;
    }
}
//Navigate to the prev / next student buttons
registerKeyHandler({name: "Previous Student", code: "ArrowLeft"}, function() {
    gotoPrevStudent();
});
registerKeyHandler({name: "Next Student", code: "ArrowRight"}, function() {
    gotoNextStudent();
});

//Navigate to the prev / next student buttons
registerKeyHandler({name: "Previous Ungraded Student", code: "Shift ArrowLeft"}, function() {
    gotoPrevStudent(true);
});
registerKeyHandler({name: "Next Ungraded Student", code: "Shift ArrowRight"}, function() {
    gotoNextStudent(true);
});

//-----------------------------------------------------------------------------
// Panel show/hide

function isAutogradingVisible() {
    return $("#autograding_results").is(":visible");
}

function isRubricVisible() {
    return $("#grading_rubric").is(":visible");
}

function isSubmissionsVisible() {
    return $("#submission_browser").is(":visible");
}

function isInfoVisible() {
    return $("#student_info").is(":visible");
}
function isRegradeVisible(){
    return $("#regrade_info").is(":visible");
}

function isDiscussionVisible() {
    return $("#discussion_browser").is(":visible");
}

function isPeerVisible() {
    return $("#peer_info").is(":visible");
}

function setAutogradingVisible(visible) {
    $('.grading_toolbar .fa-list').toggleClass('icon-selected', visible);
    $("#autograding_results").toggle(visible);
    hideIfEmpty("#autograding_results");
}

function setRubricVisible(visible) {
    $('.grading_toolbar .fa-edit').toggleClass('icon-selected', visible);
    $("#grading_rubric").toggle(visible);
}

function setSubmissionsVisible(visible) {
    $('.grading_toolbar .fa-folder-open.icon-header').toggleClass('icon-selected', visible);
    $("#submission_browser").toggle(visible);
    hideIfEmpty("#submission_browser");
}

function setInfoVisible(visible) {
    $('.grading_toolbar .fa-user').toggleClass('icon-selected', visible);
    $("#student_info").toggle(visible);
    hideIfEmpty("#student_info");
}

function setRegradeVisible(visible) {
    $('.grading_toolbar .grade_inquiry_icon').toggleClass('icon-selected', visible);
    $("#regrade_info").toggle(visible);
    hideIfEmpty("#regrade_info");
}

function setDiscussionVisible(visible) {
    $('.grading_toolbar .fa-comment-alt').toggleClass('icon-selected', visible);
    $("#discussion_browser").toggle(visible);
    hideIfEmpty("#discussion_browser");
}

function setPeerVisible(visible) {
    $('.grading_toolbar .fa-users').toggleClass('icon-selected', visible);
    $("#peer_info").toggle(visible);
}
function toggleAutograding() {
    setAutogradingVisible(!isAutogradingVisible());
}

function toggleRubric() {
    setRubricVisible(!isRubricVisible());
}

function toggleSubmissions() {
    setSubmissionsVisible(!isSubmissionsVisible());
}

function toggleInfo() {
    setInfoVisible(!isInfoVisible());
}
function toggleRegrade() {
    setRegradeVisible(!isRegradeVisible());
}

function toggleDiscussion() {
    setDiscussionVisible(!isDiscussionVisible());
}

function togglePeer() {
    setPeerVisible(!isPeerVisible());
}

function resetModules() {
    var width = $("main").width();
    var height = $("main").height();

    $('.grading_toolbar .fa-list').addClass('icon-selected');
    $("#autograding_results").attr("style", "position: absolute; z-index:30; left:30px; top:60%; width:48%; height:40%; display:block;");
    $('.grading_toolbar .fa-edit').addClass('icon-selected');
    $("#grading_rubric").attr("style", "position: absolute; left: 50%; z-index:30; top:10%; width:48%; height:68%; display:block;");
    $('.grading_toolbar .fa-folder-open').addClass('icon-selected');
    $("#submission_browser").attr("style", "position: absolute; left:30px; z-index:30; top:10%; width:48%; height:48%; display:block;");
    $('.grading_toolbar .fa-user').addClass('icon-selected');
    $('#bar_wrapper').attr("style", "position: absolute; top: 0; left: " + ((width - $('#bar_wrapper').width()) / 2) + "px; z-index:40;");
    $("#student_info").attr("style", "position: absolute; left: 50%; top: 80%; z-index:30; width:48%; height:20%; display:block;");
    $('.grading_toolbar .fa-hand-paper').addClass('icon-selected');
    $("#regrade_info").attr("style", "position: absolute; bottom:30px; z-index:30; right:15px; width:48%; height:37%; display:block;");
    $('.grading_toolbar .fa-comment-alt').addClass('icon-selected');
    $("#discussion_browser").attr("style", "position: absolute; bottom:30px; z-index:30; right:15px; width:48%; height:37%; display:block;");
    $('.grading_toolbar .fa-users').addClass('icon-selected');
    $("#peer_info").attr("style", "position: absolute; bottom:30px; z-index:30; right:15px; width:48%; height:37%; display:block;");
    // $("#pdf_annotation_bar").attr("style", "left: 58%, z-index:40; top:307px");
    deleteCookies();
    updateCookies();
}


registerKeyHandler({name: "Reset Panel Positions", code: "KeyR"}, function() {
    resetModules();
    updateCookies();
});
registerKeyHandler({name: "Toggle Autograding Panel", code: "KeyA"}, function() {
    toggleAutograding();
    updateCookies();
});
registerKeyHandler({name: "Toggle Rubric Panel", code: "KeyG"}, function() {
    toggleRubric();
    updateCookies();
});
registerKeyHandler({name: "Toggle Submissions Panel", code: "KeyO"}, function() {
    toggleSubmissions();
    updateCookies();
});
registerKeyHandler({name: "Toggle Student Information Panel", code: "KeyS"}, function() {
    toggleInfo();
    updateCookies();
});
registerKeyHandler({name: "Toggle Grade Inquiry Panel", code: "KeyX"}, function() {
    toggleRegrade();
    updateCookies();
});
registerKeyHandler({name: "Toggle Discussion Panel", code: "KeyD"}, function() {
    toggleDiscussion();
    updateCookies();
});
registerKeyHandler({name: "Toggle Discussion Panel", code: "KeyP"}, function() {
    togglePeer();
    updateCookies();
});
//-----------------------------------------------------------------------------
// Show/hide components

registerKeyHandler({name: "Open Next Component", code: 'ArrowDown'}, function(e) {
    let openComponentId = getFirstOpenComponentId();
    let numComponents = getComponentCount();

    // Note: we use the 'toggle' functions instead of the 'open' functions
    //  Since the 'open' functions don't close any components
    if (isOverallCommentOpen()) {
        // Overall comment is open, so just close it
        closeOverallComment(true);
    }
    else if (openComponentId === NO_COMPONENT_ID) {
        // No component is open, so open the first one
        let componentId = getComponentIdByOrder(0);
        toggleComponent(componentId, true).then(function () {
            scrollToComponent(componentId);
        });
    }
    else if (openComponentId === getComponentIdByOrder(numComponents - 1)) {
        // Last component is open, so open the general comment
        toggleOverallComment(true).then(function () {
            scrollToOverallComment();
        });
    }
    else {
        // Any other case, open the next one
        let nextComponentId = getNextComponentId(openComponentId);
        toggleComponent(nextComponentId, true).then(function () {
            scrollToComponent(nextComponentId);
        });
    }
    e.preventDefault();
});

registerKeyHandler({name: "Open Previous Component", code: 'ArrowUp'}, function(e) {
    let openComponentId = getFirstOpenComponentId();
    let numComponents = getComponentCount();

    // Note: we use the 'toggle' functions instead of the 'open' functions
    //  Since the 'open' functions don't close any components
    if (isOverallCommentOpen()) {
        // Overall comment open, so open the last component
        let componentId = getComponentIdByOrder(numComponents - 1);
        toggleComponent(componentId, true).then(function () {
            scrollToComponent(componentId);
        });
    }
    else if (openComponentId === NO_COMPONENT_ID) {
        // No Component is open, so open the overall comment
        toggleOverallComment(true).then(function () {
            scrollToOverallComment();
        });
    }
    else if (openComponentId === getComponentIdByOrder(0)) {
        // First component is open, so close it
        closeAllComponents(true);
    }
    else {
        // Any other case, open the previous one
        let prevComponentId = getPrevComponentId(openComponentId);
        toggleComponent(prevComponentId, true).then(function () {
            scrollToComponent(prevComponentId);
        });
    }
    e.preventDefault();
});

//-----------------------------------------------------------------------------
// Misc rubric options
registerKeyHandler({name: "Toggle Rubric Edit Mode", code: "KeyE"}, function() {
    let editBox = $("#edit-mode-enabled");
    editBox.prop("checked", !editBox.prop("checked"));
    onToggleEditMode();
    updateCookies();
});


//-----------------------------------------------------------------------------
// Selecting marks

registerKeyHandler({name: "Select Full/No Credit Mark", code: 'Digit0', locked: true}, function() {
    checkOpenComponentMark(0);
});
registerKeyHandler({name: "Select Mark 1", code: 'Digit1', locked: true}, function() {
    checkOpenComponentMark(1);
});
registerKeyHandler({name: "Select Mark 2", code: 'Digit2', locked: true}, function() {
    checkOpenComponentMark(2);
});
registerKeyHandler({name: "Select Mark 3", code: 'Digit3', locked: true}, function() {
    checkOpenComponentMark(3);
});
registerKeyHandler({name: "Select Mark 4", code: 'Digit4', locked: true}, function() {
    checkOpenComponentMark(4);
});
registerKeyHandler({name: "Select Mark 5", code: 'Digit5', locked: true}, function() {
    checkOpenComponentMark(5);
});
registerKeyHandler({name: "Select Mark 6", code: 'Digit6', locked: true}, function() {
    checkOpenComponentMark(6);
});
registerKeyHandler({name: "Select Mark 7", code: 'Digit7', locked: true}, function() {
    checkOpenComponentMark(7);
});
registerKeyHandler({name: "Select Mark 8", code: 'Digit8', locked: true}, function() {
    checkOpenComponentMark(8);
});
registerKeyHandler({name: "Select Mark 9", code: 'Digit9', locked: true}, function() {
    checkOpenComponentMark(9);
});

function checkOpenComponentMark(index) {
    let component_id = getFirstOpenComponentId();
    if (component_id !== NO_COMPONENT_ID) {
        let mark_id = getMarkIdFromOrder(component_id, index);
        //TODO: Custom mark id is zero as well, should use something unique
        if (mark_id === CUSTOM_MARK_ID || mark_id === 0) {
            return;
        }
        toggleCommonMark(component_id, mark_id)
            .catch(function (err) {
                console.error(err);
                alert('Error toggling mark! ' + err.message);
            });
    }
}


// expand all files in Submissions and Results section
function openAll(click_class, class_modifier) {
    $("."+click_class + class_modifier).each(function(){
        // Check that the file is not a PDF before clicking on it
        let innerText = Object.values($(this))[0].innerText;
        if (innerText.slice(-4) !== ".pdf") {
            $(this).click();
        }
    });
}
function updateValue(obj, option1, option2) {
    // Switches the value of an element between option 1 and two
    obj.text(function(i, oldText){
        if(oldText.indexOf(option1) >= 0){
            newText = oldText.replace(option1, option2);
        }
        else {
            newText = oldText.replace(option2, option1);
        }
        return newText;
    });

}
function openAutoGrading(num){
    $('#tc_' + num).click();
    if($('#testcase_' + num)[0]!=null){
        $('#testcase_' + num)[0].style.display="block";
    }
}
// expand all outputs in Auto-Grading Testcases section
function openAllAutoGrading() {
    // show all divs whose id starts with testcase_
     var clickable_divs  = $("[id^='tc_']");

     for(var i = 0; i < clickable_divs.length; i++){
        var clickable_div = clickable_divs[i];
        var num = clickable_div.id.split("_")[1];
        var content_div = $('#testcase_' + num);
        if(content_div.css("display") == "none"){
            clickable_div.click();
        }
     }
}

// close all outputs in Auto-Grading Testcases section
function closeAllAutoGrading() {
    // hide all divs whose id starts with testcase_
    $("[id^='testcase_']").hide();
    $("[id^='details_tc_']").find("span").hide();
    $("[id^='details_tc_']").find(".loading-tools-show").show();
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
    }
    else {
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

function adjustSize(name) {
    var textarea = document.getElementById(name);
    textarea.style.height = "";
    textarea.style.height = Math.min(textarea.scrollHeight, 300) + "px";
}

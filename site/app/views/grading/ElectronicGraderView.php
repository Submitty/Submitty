<?php

namespace app\views\grading;

use app\models\Gradeable;
use app\models\User;
use app\views\AbstractView;

class ElectronicGraderView extends AbstractView {
    /**
     * @param Gradeable $gradeable
     * @param array     $sections
     * @return string
     */
    public function statusPage($gradeable, $sections) {
        $course = $this->core->getConfig()->getCourse();
        $semester = $this->core->getConfig()->getSemester();
        $graded = 0;
        $total = 0;
        foreach ($sections as $key => $section) {
            if ($key === "NULL") {
                continue;
            }
            $graded += $section['graded_students'];
            $total += $section['total_students'];
        }
        if ($total === 0){
            $percentage = -1;
        }
        else{
            $percentage = round(($graded / $total) * 100);
        }
        $return = <<<HTML
<div class="content">
    <h2>Status of {$gradeable->getName()}</h2>
HTML;
    if($percentage === -1){
        $view = 'all';
        $return .= <<<HTML
    <div class="sub">
        No Grading To Be Done! :)
HTML;

    }
    else{
        $view = null;
        $return .= <<<HTML
    <div class="sub">
        Current percentage of grading done: {$percentage}% ({$graded}/{$total})
        <br />
        <br />
        By Grading Sections:
        <div style="margin-left: 20px">
HTML;
        foreach ($sections as $key => $section) {
            $percentage = round(($section['graded_students'] / $section['total_students']) * 100);
            $return .= <<<HTML
            Section {$key}: {$percentage}% ({$section['graded_students']} / {$section['total_students']})<br />
HTML;
        }
        $return .= <<<HTML
        </div>
        <br />
        Graders:
        <div style="margin-left: 20px">
HTML;
            foreach ($sections as $key => $section) {
                if ($key === "NULL") {
                    continue;
                }
                if (count($section['graders']) > 0) {
                    $graders = implode(", ", array_map(function($grader) { return $grader->getId(); }, $section['graders']));
                }
                else {
                    $graders = "Nobody";
                }
                $return .= <<<HTML
            Section {$key}: {$graders}<br />
HTML;
            }
        }
        // {$this->core->getConfig()->getTABaseUrl()}account/account-summary.php?course={$course}&semester={$semester}&g_id={$gradeable->getId()}
        $return .= <<<HTML
        </div>
        <div style="margin-top: 20px">
HTML;
        if($percentage !== -1 || $this->core->getUser()->accessFullGrading()){
            $return .= <<<HTML
            <a class="btn btn-primary" 
                href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action' => 'summary', 'gradeable_id' => $gradeable->getId(), 'view' => $view))}"">
                Grading Details
            </a>
HTML;
            if(count($this->core->getUser()->getGradingRegistrationSections()) !== 0){
                $return .= <<<HTML
            <a class="btn btn-primary"
                href="{$this->core->getConfig()->getTABaseUrl()}account/index.php?course={$course}&semester={$semester}&g_id={$gradeable->getId()}">
                Grade Next Student
            </a>
        </div>
    </div>
</div>
HTML;
            }
        }
        return $return;
    }

    /**
     * @param Gradeable   $gradeable
     * @param Gradeable[] $rows
     * @param array       $graders
     * @return string
     */
    public function summaryPage($gradeable, $rows, $graders) {
        $return = <<<HTML
<div class="content">
    
HTML;
        // Default is viewing your sections
        // Limited grader does not have "View All" option
        // If nothing to grade, Instructor will see all sections
        if (!isset($_GET['view']) || $_GET['view'] !== 'all') {
            $text = 'View All';
            $view = 'all';
        }
        else{
            $text = 'View Your Sections';
            $view = null;
        }
        if($gradeable->isGradeByRegistration()){
            $grading_count = count($this->core->getUser()->getGradingRegistrationSections());
        }
        else{
            $grading_count = count($this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable->getId(),$this->core->getUser()->getId()));
        }

        if($this->core->getUser()->accessFullGrading() && (!$this->core->getUser()->accessAdmin() || $grading_count !== 0)){
            $return .= <<<HTML
    <div style="float: right; margin-bottom: 10px">
        <a class="btn btn-default"
            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'action' => 'summary', 'gradeable_id' => $gradeable->getId(), 'view' => $view))}">
            $text
        </a>
    </div>
HTML;
        }
        $return .= <<<HTML
    <h2>Grade Details for {$gradeable->getName()}</h2>
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="3%"></td>
                <td width="5%">Section</td>
                <td width="20%">User ID</td>
                <td width="15%">First Name</td>
                <td width="15%">Last Name</td>
                <td width="14%">Autograding</td>
                <td width="10%">TA Grading</td>
                <td width="10%">Total</td>
                <td width="8%">Viewed Grade</td>
            </tr>
        </thead>
HTML;
            $return .= <<<HTML
HTML;
            $count = 1;
            $last_section = false;
            $tbody_open = false;
            foreach ($rows as $row) {
                if ($row->beenTAgraded()){
                    if ($row->getUserViewedDate() === null || $row->getUserViewedDate() === "") {
                        $viewed_grade = "&#10008;";
                        $grade_viewed = "";
                        $grade_viewed_color = "color: red; font-size: 1.5em;";
                    }
                    else {
                        $viewed_grade = "&#x2714;";
                        $grade_viewed = "Last Viewed: " . date("F j, Y, g:i a", strtotime($row->getUserViewedDate()));
                        $grade_viewed_color = "color: #5cb85c; font-size: 1.5em;";
                    }
                }
                else{
                    $viewed_grade = "";
                    $grade_viewed = "";
                    $grade_viewed_color = "";
                }
                $total_possible = $row->getTotalAutograderNonExtraCreditPoints() + $row->getTotalTANonExtraCreditPoints();
                $graded = $row->getGradedAutograderPoints() + $row->getGradedTAPoints();
                if ($graded < 0) $graded = 0;
                if ($gradeable->isGradeByRegistration()) {
                    $section = $row->getUser()->getRegistrationSection();
                }
                else {
                    $section = $row->getUser()->getRotatingSection();
                }
                $display_section = ($section === null) ? "NULL" : $section;
                if ($section !== $last_section) {
                    $last_section = $section;
                    $count = 1;
                    if ($tbody_open) {
                        $return .= <<<HTML
        </tbody>
HTML;
                    }
                    if (isset($graders[$display_section]) && count($graders[$display_section]) > 0) {
                        $section_graders = implode(", ", array_map(function(User $user) { return $user->getId(); }, $graders[$display_section]));
                    }
                    else {
                        $section_graders = "Nobody";
                    }

                    $return .= <<<HTML
        <tr class="info persist-header">
            <td colspan="9" style="text-align: center">Students Enrolled in Section {$display_section}</td>
        </tr>
        <tr class="info">
            <td colspan="9" style="text-align: center">Graders: {$section_graders}</td>
        </tr>
        <tbody id="section-{$section}">
HTML;
                }
                $return .= <<<HTML
            <tr id="user-row-{$row->getUser()->getId()}">
                <td>{$count}</td>
                <td>{$display_section}</td>
                <td>{$row->getUser()->getId()}</td>
                <td>{$row->getUser()->getDisplayedFirstName()}</td>
                <td>{$row->getUser()->getLastName()}</td>
                <td>{$row->getGradedAutograderPoints()} / {$row->getTotalAutograderNonExtraCreditPoints()}</td>
                <td>
HTML;
                if ($row->beenTAgraded()) {
                    $btn_class = "btn-default";
                    $contents = "{$row->getGradedTAPoints()} / {$row->getTotalTANonExtraCreditPoints()}";
                }
                else {
                    $btn_class = "btn-primary";
                    $contents = "Grade";
                }
                $return .= <<<HTML
                    <a class="btn btn-default" href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'who_id'=>$row->getUser()->getId()))}">
                        {$contents}
                    </a>
                    <a class="btn {$btn_class}" href="{$this->core->getConfig()->getTABaseUrl()}account/index.php?g_id={$gradeable->getId()}&amp;individual={$row->getUser()->getId()}&amp;course={$this->core->getConfig()->getCourse()}&amp;semester={$this->core->getConfig()->getSemester()}">
                        {$contents}
                    </a>
                </td>
                <td>{$graded} / {$total_possible}</td>
                <td title="{$grade_viewed}" style="{$grade_viewed_color}">{$viewed_grade}</td>
            </tr>
HTML;
                $count++;
            }
            $return .= <<<HTML
        </tbody>
HTML;
        $return .= <<<HTML
    </table>
</div>
HTML;
        return $return;
    }

    public function hwGradingPage($gradeable) {
        $return = <<<HTML
<div class="grading_toolbar">
    <a><i title="Go to the previous student (Press Left Arrow)" class="icon-left"></i></a>
    <a href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'summary', 'gradeable_id'=>$gradeable->getId()))}"><i title="Go to the main page (Press H)" class="icon-home" ></i></a>
    <a><i title="Go to the next student (Press Right Arrow)" class="icon-right"></i></a>
    <i title="Reset Rubric Panel Positions (Press R)" class="icon-refresh" onclick="handleKeyPress('KeyR');"></i>
    <i title="Show/Hide Auto-Grading Testcases (Press A)" class="icon-auto-grading-results" onclick="handleKeyPress('KeyA');"></i>
    <i title="Show/Hide Grading Rubric (Press G)" class="icon-grading-panel" onclick="handleKeyPress('KeyG');"></i>
    <i title="Show/Hide Submission and Results Browser (Press O)" class="icon-files" onclick="handleKeyPress('KeyO');"></i>
    <i title="Show/Hide Student Information (Press S)" class="icon-status" onclick="handleKeyPress('KeyS');"></i>
</div>

<div class="progress_bar">
    <progress class="progressbar" max="100" value="60" style="width:80%; height: 100%;"></progress>
    <div class="progress-value" style="display:inline;"></div>
</div>

<div id="autograding_results" class="draggable rubric_panel" style="left:15px; top:185px; width:48%; height:36%;">
    <span class="grading_label">Auto-Grading Testcases</span>
    <div class="inner-container">
        <br />
HTML;
    if ($gradeable->getActiveVersion() === 0){
        $return .= <<<HTML
        No Submission <br />
HTML;
    }
    else{
        $return .= <<<HTML
        Submitted: {$gradeable->getSubmissionTime()}<br />
        Submission Number: {$gradeable->getActiveVersion()} / {$gradeable->getHighestVersion()}
HTML;
    }
    $return .= <<<HTML
    </div>
</div>

<div id="grading_rubric" class="draggable rubric_panel" style="right:15px; top:140px; width:48%; height:42%;">
    <span class="grading_label">Grading Rubric</span>
</div>

<div id="submission_browser" class="draggable rubric_panel" style="left:15px; bottom:40px; width:48%; height:30%">
    <span class="grading_label">Submission and Results Browser</span>
</div>

<div id="student_info" class="draggable rubric_panel" style="right:15px; bottom:40px; width:48%; height:30%;">
    <span class="grading_label">Student Information</span>
</div>

<script type="text/javascript">
    var progressbar = $(".progressbar"),
        value = progressbar.val();
    $(".progress-value").html("<b>" +value + '%</b>');

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
    });

    function createCookie(name,value,seconds)  {
        if(seconds) {
            var date = new Date();
            date.setTime(date.getTime()+(seconds*1000));
            var expires = "; expires="+date.toGMTString();
        }
        else var expires = "";
        document.cookie = name+"="+value+expires+"; domain=."+document.domain+"; path=/";
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

    $( ".draggable" ).draggable({snap:false, grid:[2, 2], stack:".draggable"}).resizable();

    $(".draggable").on("dragstop", function(){
        updateCookies();
    });

    $(".draggable").on("resizestop", function(){
        updateCookies();
    });

    /*$("#grading_rubric-autoscroll-checkbox").change(function() {
        if($("#grading_rubric-autoscroll-checkbox").is(':checked')) {
            createCookie('auto',1,1000);
            $('#grading_rubric').css("overflow-y", "hidden");
        }
        else {
            eraseCookie('auto');
            $('#grading_rubric').css("overflow-y", "scroll");
        }
    });

    $('.content').scroll(function() {
        if($("#grading_rubric-autoscroll-checkbox").is(':checked')) {
            var scrollPercentage = this.scrollTop / (this.scrollHeight-this.clientHeight);
            document.getElementById('rubric').scrollTop = scrollPercentage * (document.getElementById('rubric').scrollHeight-document.getElementById('rubric').clientHeight);
        }
    });*/

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
                $("#autograding_results").attr("style", "left:15px; top:185px; width:48%; height:36%; display:block;");
                $('.icon-grading-panel').addClass('icon-selected');
                $("#grading_rubric").attr("style", "right:15px; top:140px; width:48%; height:42%; display:block;");
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
    }

    eraseCookie("reset");
</script>
HTML;
        return $return;
    }
}
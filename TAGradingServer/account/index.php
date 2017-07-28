<?php
include "../header.php";

use \models\User;
use lib\Database;

$account_subpages_unlock = true;

$split = isset($_COOKIE["split"]) ? floatval($_COOKIE["split"]) : 50;
$show_stats = isset($_COOKIE["show_stats"]) ? intval($_COOKIE["show_stats"]) : 0;
$show_rubric = isset($_COOKIE["show_rubric"]) ? intval($_COOKIE["show_rubric"]) : 0;
$show_left = isset($_COOKIE["show_left"]) ? intval($_COOKIE["show_left"]) : 1;
$show_right = isset($_COOKIE["show_right"]) ? intval($_COOKIE["show_right"]) : 1;

$rubric_late_days = 0;

print <<<HTML
<div id="container">
HTML;

if(isset($_GET["g_id"])) {
    $g_id = $_GET["g_id"];
    $params = array($g_id);
    $db->query("SELECT g.g_id, g_title, g_grade_by_registration, eg_submission_due_date, eg_late_days FROM gradeable AS g INNER JOIN electronic_gradeable AS eg ON g.g_id=eg.g_id WHERE g.g_id=?", $params);
    $rubric = $db->row();
}

if(isset($_GET["g_id"]) && isset($rubric["g_id"])) {
    $g_id = $rubric["g_id"];
    $g_title = $rubric['g_title'];
    $rubric_late_days = $rubric['eg_late_days'];
    $grade_by_reg_section = $rubric['g_grade_by_registration'];
    $section_param = ($grade_by_reg_section ? 'sections_registration_id': 'sections_rotating_id');
    $user_section_param = ($grade_by_reg_section ? 'registration_section': 'rotating_section');
    $grading_section_param = ($grade_by_reg_section ? 'sections_registration_id': 'sections_rotating_id');
    $section_title = ($grade_by_reg_section ? 'Registration': 'Rotating');
    $eg_submission_due_date = $rubric['eg_submission_due_date'];
    $s_user_id = null;
    $student_first_name = null;
    $student_last_name = null;
    $student_allowed_lates = 0;
    $position_completed = 0;
    $position_total = 0;
    $position_other = 0;
    $student_individual_graded = false;
    $previous_user_id = "";
    $next_user_id = "";

    if(isset($_GET["individual"]) || isset($_GET['prev']) || isset($_GET['next'])) {
        if(isset($_GET['individual'])) {
            $s_user_id = $_GET["individual"];
        } else if(isset($_GET['prev'])) {
            $s_user_id = $_GET['prev'];
        } else {
            $s_user_id = $_GET['next'];
        }

        $params = array($s_user_id);
        $db->query("SELECT * FROM users WHERE user_id=?", $params);
        $temp_row = $db->row();
        $student_first_name = $temp_row["user_firstname"];
        $student_last_name = $temp_row["user_lastname"];
        $params = array($temp_row['user_id'], $rubric['eg_submission_due_date']);
        
        //HANDLE LATE DAYS AND LATE DAY EXCEPTIONS
        //TODO handle late day exceptions
        //$db->query("SELECT * FROM late_days WHERE s_user_id=? AND since_timestamp <= ? ORDER BY since_timestamp DESC LIMIT 1", $params);
        $lates = $db->row();
        $student_allowed_lates = 2;//isset($lates['allowed_lates']) ? intval($lates['allowed_lates']) : 0;

        // DETERMINE IF A STUDENT HAS BEEN GRADED 
        $params = array($s_user_id, $g_id);
        $db->query("SELECT COUNT(*) AS cnt FROM gradeable_data as gd INNER JOIN gradeable_component_data AS gcd ON gd.gd_id=gcd.gd_id WHERE gd_user_id=? and g_id=? GROUP BY g_id", $params);
        $temp_row = $db->row();
        $student_individual_graded = isset($temp_row['cnt']) && $temp_row['cnt'] > 0;
    }

    if ($grade_by_reg_section) {
      $params = array(User::$user_id);
      $query = "SELECT * FROM grading_registration WHERE user_id=? ORDER BY sections_registration_id ASC";
      $db->query($query, $params);
    } else {
      $params = array(User::$user_id,$g_id);
      $query = "SELECT * FROM grading_rotating WHERE user_id=? AND g_id=? ORDER BY sections_rotating_id ASC";
      $db->query($query, $params);
    }
    foreach ($db->rows() as $section) {
        $params = array($g_id, intval($section[$section_param]));
        $db->query("
SELECT
    s.user_id
    , s.user_firstname
    , s.user_lastname
    , case when g.number_graded is null then 0 else g.number_graded end
FROM
    users AS s
    LEFT JOIN (
    SELECT
        gd_user_id
        , count(*) as number_graded
    FROM
        gradeable_data AS gd INNER JOIN gradeable_component_data AS gcd
        ON gd.gd_id = gcd.gd_id
    WHERE
        g_id=?
    GROUP BY
        gd_user_id
    ) AS g
    ON s.user_id=g.gd_user_id
WHERE
    s.{$user_section_param}=?
AND 
    user_group=4
ORDER BY
    s.user_id ASC
                ", $params);

        $prev_row = array('user_id' => "");
        $set_next = false;

        foreach ($db->rows() as $row) {
            $temp_row = $row;

            if($set_next) {
                $next_user_id = $temp_row['user_id'];
                $set_next = false;
            }

            if($s_user_id != null) {
                if($s_user_id == $temp_row['user_id']) {
                    $previous_user_id = $prev_row['user_id'];
                    $set_next = true;
                }
            }

            if(intval($temp_row["number_graded"]) == 0) {
                if($s_user_id == null) {
                    $params = array($row["user_id"]);
                    $db->query("SELECT * FROM users WHERE user_id=?", $params);
                    $row = $db->row();

                    $student_first_name = $row["user_firstname"];
                    $student_last_name = $row["user_lastname"];
                    $s_user_id = $row["user_id"];
                    $previous_user_id = $prev_row['user_id'];

                    $params = array($row['user_id'], $eg_submission_due_date);
                    //TODO SET LATE DAYS
                    //$db->query("SELECT * FROM late_days WHERE s_user_id=? AND since_timestamp <= ?", $params);
                    $lates = $db->row();
                    $student_allowed_lates = 2;//intval($lates['allowed_lates']);

                    $set_next = true;
                }
            } else {
                $params = array($row['user_id'], $g_id);
                $db->query("SELECT gd_grader_id FROM gradeable_data WHERE gd_user_id=? AND g_id=?", $params);
                $temp_row = $db->row();

                if(intval($temp_row["gd_grader_id"]) == User::$user_id) {
                    $position_completed++;
                } else {
                    $position_other++;
                }
            }

            $position_total++;
            $prev_row = $row;
        }
    }

    if($s_user_id != null) {
        include "account-rubric.php";
    } else {
        
        if (User::$is_administrator) {
            Database::query("
SELECT
    s.{$user_section_param},
    count(s.*) as total,
    case when gg.graded is null then 0 else gg.graded end
FROM
    users as s
    LEFT JOIN (
        SELECT
            uu.{$user_section_param},
            count(*) as graded
        FROM
            users as uu 
            INNER JOIN (
                SELECT
                    DISTINCT(gd_user_id)
                FROM gradeable_data AS gd
                INNER JOIN gradeable_component_data AS gcd ON gd.gd_id=gcd.gd_id
                WHERE
                    gd.g_id=?
            ) AS gdd ON gdd.gd_user_id=uu.user_id 
            WHERE uu.user_group=?
        GROUP BY uu.{$user_section_param}
    ) as gg ON s.{$user_section_param} = gg.{$user_section_param}
WHERE
    user_group=?
GROUP BY
    s.{$user_section_param},
    gg.graded
ORDER BY
    s.{$user_section_param}", array($g_id,4,4));

            $sections = Database::rows();
            $graded = 0;
            $total = 0;
            foreach ($sections as $section) {
                $graded += $section['graded'];
                $total += $section['total'];
            }

            $percent = round(($graded / $total) * 100);

            print <<<HTML
        <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; z-index:100; margin: 70px auto 100px; position: relative; width: 700px;">
            <div class="modal-header">
                <h3 id="myModalLabel">{$g_title} Grading Status</h3>
            </div>

            <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
                Current percentage of grading done: {$percent}% ({$graded}/{$total})
                <br/>
                <br/>
                By grading section:
                <div style="margin-left: 20px">
HTML;
            foreach ($sections as $section) {
                $section['percent'] = round(($section['graded'] / $section['total']) * 100);
                print "Section {$section[$user_section_param]}: {$section['percent']}% ({$section['graded']} / {$section['total']})<br />";
            }
            print <<<HTML
                </div>
                <br />
                Graders:
                <div style="margin-left: 20px">
HTML;
            $query = ($grade_by_reg_section ? "SELECT gr.*, u.* FROM grading_registration gr LEFT JOIN (SELECT * FROM users) as u ON gr.user_id = u.user_id WHERE user_group <=3 ORDER BY gr.sections_registration_id, u.user_id"
                                            : "SELECT gr.*, u.* FROM grading_rotating gr LEFT JOIN (SELECT * FROM users) as u ON gr.user_id = u.user_id WHERE user_group <=3 AND g_id=? ORDER BY gr.sections_rotating_id, u.user_id");
            
            if ($grade_by_reg_section){
                Database::query($query, array());
            } else{
                Database::query($query, array($g_id));
            }
           
            $graders = array();
            foreach (Database::rows() as $row) {
                if(!isset($graders[$row[$grading_section_param]])) {
                    $graders[$row[$grading_section_param]] = array();
                }
                $graders[$row[$grading_section_param]][] = "{$row['user_firstname']} {$row['user_lastname']} ({$row['user_id']})";
            }

            foreach ($sections as $section) {
                if(isset($graders[$section[$user_section_param]])) {
                    print $section[$user_section_param] . ": " . implode(",", $graders[$section[$user_section_param]]) . "<br />";
                } else {
                    print $section[$user_section_param] . ": Nobody";
                }
            }

            print <<<HTML
                </div>
            </div>

            <div class="modal-footer">
                <a class="btn" href="{$BASE_URL}/account/index.php?course={$_GET['course']}&semester={$_GET['semester']}">Select Different Homework</a>
                <a class="btn" href="{$SUBMISSION_URL}/index.php?semester={$_GET['semester']}&course={$_GET['course']}&component=grading&page=electronic&action=summary&gradeable_id={$_GET['g_id']}">{$g_title} Status</a>
                <!--<a class="btn btn-primary" href="/logout.php">Logout</a>-->
            </div>
        </div>
    </div>
HTML;
        }
        else {
            print <<<HTML
        <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
            <div class="modal-header">
                <h3 id="myModalLabel">Grading Finished</h3>
            </div>

            <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
                Congratulations, you have finished grading {$g_title}.
                <br/>
                <br/>
                <i style="color:#777;">You can review the grades you have saved by using the navigation buttons at the bottom-right of the page or by going to the homework overview page.</i>
            </div>

            <div class="modal-footer">
                <a class="btn" href="{$BASE_URL}/account/index.php?course={$_GET['course']}&semester={$_GET['semester']}">Select Different Homework</a>
                <a class="btn" href="{$SUBMISSION_URL}/index.php?semester={$_GET['semester']}&course={$_GET['course']}&component=grading&page=electronic&action=summary&gradeable_id={$_GET['g_id']}">{$g_title} Status</a>
                <!--<a class="btn btn-primary" href="/logout.php">Logout</a>-->
            </div>
        </div>
    </div>
HTML;
        }
    }
}
else if(!isset($_GET["g_id"])) {

    // update with the gradeable data 
    $params = array();
    
    $db->query("SELECT g_title, g.g_id, g_grade_start_date FROM gradeable AS g INNER JOIN electronic_gradeable AS eg ON g.g_id=eg.g_id", $params);
    $results = $db->rows();

    if(count($results) > 0) {
        print <<<HTML
    <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
        <form action="{$BASE_URL}/account/index.php" method="get">
            <input type="hidden" name="course" value="{$_GET['course']}" />
            <input type="hidden" name="semester" value="{$_GET['semester']}" />
            <div class="modal-header">
                <h3 id="myModalLabel">Select Gradeable</h3>
            </div>

            <div class="modal-body" style="vertical-align: middle; padding-top:20px; padding-bottom:20px;">
                <select style="width:400px" name="g_id">
HTML;

        $last = end($results);
        reset($results);
        $c = 0;
        $now = new DateTime('now');
        foreach($results as $row) {
            $homeworkDate = new DateTime($row['g_grade_start_date']);
            //TODO ADD LATE DAYS
            if (isset($row['eg_late_days']) && $row['eg_late_days'] > 0) {
                $homeworkDate->add(new DateInterval("PT{$row['eg_late_days']}H"));
            }
            $extra = ($now < $homeworkDate) ? "(Gradable: {$homeworkDate->format("Y-m-d H:i:s")})" : "";
            echo "<option value='{$row['g_id']}' ".($last["g_id"] == $row["g_id"] ? "selected" : "").">{$row['g_title']} {$extra}</option>";
            $c++;
        }
        print <<<HTML

                </select>
            </div>
            <div class="modal-footer">
                <input class="btn btn-primary" type="submit" value="Grade" onclick="createCookie('backup',0,1000);"/>
            </div>
        </form>
    </div>
HTML;
    }
    else {
        print <<<HTML
    <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
        <div class="modal-header">
            <h3 id="myModalLabel">Gradeables Not Available Yet</h3>
        </div>

        <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
            No gradeables have been added to the system yet.
        </div>
    </div>
HTML;
    }
}
else {
    print <<<HTML
    <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
        <div class="modal-header">
            <h3 id="myModalLabel">Gradeable Error</h3>
        </div>

        <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
            The rubric you are looking for cannot be found
        </div>

        <div class="modal-footer">
            <a class="btn" href="{$BASE_URL}/account/index.php?course={$_GET['course']}&semester={$_GET['semester']}">Select Different Gradable</a>
        </div>
    </div>
HTML;
}
print <<<HTML
</div>
HTML;

// TODO: Rewrite this (Issue #43)
if(isset($_GET["g_id"]) && isset($g_id)) {
    if($position_total == 0) {
        $position_total = 0.001;
    }

    ?>
<div id="grade">
    <i title="Show/Hide Student Information (Press S)" class="icon-status" onclick="handleKeyPress('KeyS')"></i>
    <i title="Show/Hide Grading Rubric (Press G)" class="icon-grading-panel" onclick="handleKeyPress('KeyG')"></i>
    <i title="Show/Hide Auto-Grading Testcases (Press A)" class="icon-auto-grading-results" onclick="handleKeyPress('KeyA');"></i>
    <i title="Show/Hide Submission and Results Browser (Press O)" class="icon-files" onclick="handleKeyPress('KeyO')"></i>
    <i title="Reset Rubric Panel Positions" class="icon-refresh" onclick="handleKeyPress('KeyR')"></i>
    <a <?php echo ($previous_user_id == "" ? "" : "href=\"{$BASE_URL}/account/index.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id={$_GET['g_id']}&prev={$previous_user_id}\""); ?> ><i title="Go to the previous student (Press Left Arrow)" class="icon-left <?php echo ($previous_user_id == "" ? 'icon-disabled' : ''); ?>" ></i></a>
    <a <?php echo ("href='{$SUBMISSION_URL}/index.php?semester={$_GET['semester']}&course={$_GET['course']}&component=grading&page=electronic&action=summary&gradeable_id={$_GET['g_id']}'"); ?> ><i title="Go to the main page (Press H)" class="icon-home" ></i></a>
    <a <?php echo ($next_user_id == "" ? "" : "href=\"{$BASE_URL}/account/index.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id={$_GET['g_id']}&next={$next_user_id}\""); ?> ><i title="Go to the next student (Press Right Arrow)" class="icon-right <?php echo ($next_user_id == "" ? 'icon-disabled' : ''); ?>" ></i></a>
</div>

<div id="progress_bar">
        <progress class="progressbar" max="100" value="<?php echo round(($position_completed / $position_total) * 100, 1); ?>"
                  style="width:80%; height: 100%;"></progress>
    <div class="progress-value" style="display:inline;"></div>
</div>

<?php } ?>
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

        if(document.cookie.replace(/(?:(?:^|.*;\s*)cookie_version\s*\=\s*([^;]*).*$)|^.*$/, "$1") != cookie_version)
        {
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

        (output_top) ? $("#left").css("top", output_top):{};
        (output_left) ? $("#left").css("left", output_left):{};
        (output_width) ? $("#left").css("width", output_width):{};
        (output_height) ? $("#left").css("height", output_height):{};
        (output_visible) ? $("#left").css("display", output_visible):{};

        (files_top) ? $("#right").css("top", files_top):{};
        (files_left) ? $("#right").css("left", files_left):{};
        (files_width) ? $("#right").css("width", files_width):{};
        (files_height) ? $("#right").css("height", files_height):{};
        (files_visible) ? $("#right").css("display", files_visible):{};

        (rubric_top) ? $("#rubric").css("top", rubric_top):{};
        (rubric_left) ? $("#rubric").css("left", rubric_left):{};
        (rubric_width) ? $("#rubric").css("width", rubric_width):{};
        (rubric_height) ? $("#rubric").css("height", rubric_height):{};
        (rubric_visible) ? $("#rubric").css("display", rubric_visible):{};

        (status_top) ? $("#stats").css("top", status_top):{};
        (status_left) ? $("#stats").css("left", status_left):{};
        (status_width) ? $("#stats").css("width", status_width):{};
        (status_height) ? $("#stats").css("height", status_height):{};
        (status_visible) ? $("#stats").css("display", status_visible):{};

        (output_visible) ? ((output_visible) == "none" ? $(".icon-auto-grading-results").removeClass("icon-selected") : $(".icon-auto-grading-results").addClass("icon-selected")) : {};
        (files_visible) ? ((files_visible) == "none" ? $(".icon-files").removeClass("icon-selected") : $(".icon-files").addClass("icon-selected")) : {};
        (rubric_visible) ? ((rubric_visible) == "none" ? $(".icon-grading-panel").removeClass("icon-selected") : $(".icon-grading-panel").addClass("icon-selected")) : {};
        (status_visible) ? ((status_visible) == "none" ? $(".icon-status").removeClass("icon-selected") : $(".icon-status").addClass("icon-selected")) : {};
    }

    $("#rubric-autoscroll-checkbox").change(function() {
        if($("#rubric-autoscroll-checkbox").is(':checked')) {
            createCookie('auto',1,1000);
            $('#rubric').css("overflow-y", "hidden");
        }
        else {
            eraseCookie('auto');
            $('#rubric').css("overflow-y", "scroll");
        }
    });

    $('.content').scroll(function() {
        if($("#rubric-autoscroll-checkbox").is(':checked')) {
            var scrollPercentage = this.scrollTop / (this.scrollHeight-this.clientHeight);
            document.getElementById('rubric').scrollTop = scrollPercentage * (document.getElementById('rubric').scrollHeight-document.getElementById('rubric').clientHeight);
        }
    });

    window.onkeydown = function(e) {
        if (e.target.tagName == "TEXTAREA" || e.target.tagName == "INPUT" || e.target.tagName == "SELECT") return; // disable keyboard event when typing to textarea/input
        handleKeyPress(e.code);
    };

    function handleKeyPress(key) {
        switch (key) {
            case "KeyG":
                $('#grade .icon-grading-panel').toggleClass('icon-selected');
                $("#rubric").toggle();
                break;
            case "KeyS":
                $('#grade .icon-status').toggleClass('icon-selected');
                $("#stats").toggle();
                break;
            case "KeyO":
                $('#grade .icon-files').toggleClass('icon-selected');
                $("#right").toggle();
                break;
            case "KeyA":
                $('#grade .icon-auto-grading-results').toggleClass('icon-selected');
                $("#left").toggle();
                break;
            case "KeyR":
                $('#grade .icon-auto-grading-results').addClass('icon-selected');
                $("#left").attr("style", "left:5px;top:50px; height:55%;width:60%; display: block;");
                $('#grade .icon-files').addClass('icon-selected');
                $("#right").attr("style", "top:65%; left: 5px;width: 60%; height: 30%; display: block;");
                $('#grade .icon-status').addClass('icon-selected');
                $("#stats").attr("style", "bottom: 0px; right:20px; width:35%; height: 25%; display: block;");
                $('#grade .icon-grading-panel').addClass('icon-selected');
                $("#rubric").attr("style", "top:50px; right:20px;width:35%; height: 65%; display: block;");
                deleteCookies();
                updateCookies();
                break;
            default:
                break;
        }
        updateCookies();
    }

    $(".draggable").draggable({snap:false, grid:[2, 2], stack:".draggable"}).resizable();

    $(".draggable").on("dragstop", function(){
        updateCookies();
    });

    $(".draggable").on("resizestop", function(){
        updateCookies();
    });

    function updateCookies(){
        document.cookie = "output_top=" + $("#left").css("top") + "; path=/;";
        document.cookie = "output_left=" + $("#left").css("left") + "; path=/;";
        document.cookie = "output_width=" + $("#left").css("width") + "; path=/;";
        document.cookie = "output_height=" + $("#left").css("height") + "; path=/;";
        document.cookie = "output_visible=" + $("#left").css("display") + "; path=/;";

        document.cookie = "files_top=" + $("#right").css("top") + "; path=/;";
        document.cookie = "files_left=" + $("#right").css("left") + "; path=/;";
        document.cookie = "files_width=" + $("#right").css("width") + "; path=/;";
        document.cookie = "files_height=" + $("#right").css("height") + "; path=/;";
        document.cookie = "files_visible=" + $("#right").css("display") + "; path=/;";

        document.cookie = "rubric_top=" + $("#rubric").css("top") + "; path=/;";
        document.cookie = "rubric_left=" + $("#rubric").css("left") + "; path=/;";
        document.cookie = "rubric_width=" + $("#rubric").css("width") + "; path=/;";
        document.cookie = "rubric_height=" + $("#rubric").css("height") + "; path=/;";
        document.cookie = "rubric_visible=" + $("#rubric").css("display") + "; path=/;";

        document.cookie = "status_top=" + $("#stats").css("top") + "; path=/;";
        document.cookie = "status_left=" + $("#stats").css("left") + "; path=/;";
        document.cookie = "status_width=" + $("#stats").css("width") + "; path=/;";
        document.cookie = "status_height=" + $("#stats").css("height") + "; path=/;";
        document.cookie = "status_visible=" + $("#stats").css("display") + "; path=/;";

        document.cookie = "cookie_version=" + cookie_version + "; path=/;";
    }

    eraseCookie("reset");

</script>

<?php include "../footer.php"; ?>
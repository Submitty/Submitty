<?php
include "../header.php";

use app\models\User;
use lib\Database;

$account_subpages_unlock = true;

$split = isset($_COOKIE["split"]) ? floatval($_COOKIE["split"]) : 50;
$show_stats = isset($_COOKIE["show_stats"]) ? intval($_COOKIE["show_stats"]) : 0;
$show_rubric = isset($_COOKIE["show_rubric"]) ? intval($_COOKIE["show_rubric"]) : 0;
$show_left = isset($_COOKIE["show_left"]) ? intval($_COOKIE["show_left"]) : 1;
$show_right = isset($_COOKIE["show_right"]) ? intval($_COOKIE["show_right"]) : 1;

$stats_left = isset($_COOKIE["stats_left"]) ? floatval($_COOKIE["stats_left"]) : 0;
$stats_top = isset($_COOKIE["stats_top"]) ? floatval($_COOKIE["stats_top"]) : 0;
$stats_width = isset($_COOKIE["stats_width"]) ? floatval($_COOKIE["stats_width"]) : 0;
$stats_height = isset($_COOKIE["stats_height"]) ? floatval($_COOKIE["stats_height"]) : 0;

$rubric_left = isset($_COOKIE["rubric_left"]) ? floatval($_COOKIE["rubric_left"]) : 0;
$rubric_top = isset($_COOKIE["rubric_top"]) ? floatval($_COOKIE["rubric_top"]) : 0;
$rubric_width = isset($_COOKIE["rubric_width"]) ? floatval($_COOKIE["rubric_width"]) : 0;
$rubric_height = isset($_COOKIE["rubric_height"]) ? floatval($_COOKIE["rubric_height"]) : 0;

$grade_left = isset($_COOKIE["grade_left"]) ? floatval($_COOKIE["grade_left"]) : 0; // position of toolbar should also be remembered. Not implemented yet
$grade_top = isset($_COOKIE["grade_top"]) ? floatval($_COOKIE["grade_top"]) : 0;
$rubric_late_days = 0;

print <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
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
    $grading_section_param = ($grade_by_reg_section ? 'sections_registration_id': 'sections_rotating');
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

    $params = array(User::$user_id);
    $query = ($grade_by_reg_section ? "SELECT * FROM grading_registration WHERE user_id=? ORDER BY sections_registration_id ASC"
                                    : "SELECT * FROM grading_rotating WHERE user_id=? ORDER BY sections_rotating ASC");
    $db->query($query, $params);
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
        user_id
        , count(*) as number_graded
    FROM
        gradeable_data AS gd INNER JOIN gradeable_component_data AS gcd
        ON gd.gd_id = gcd.gd_id
    WHERE
        g_id=?
    GROUP BY
        user_id
    ) AS g
    ON s.user_id=g.user_id
WHERE
    s.".$user_section_param."=?
ORDER BY
    s.s_user_id ASC
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
                $db->query("SELECT gd_grader_id FROM gradeable_data WHERE user_id=? AND g_id=?", $params);
                $temp_row = $db->row();

                if(intval($temp_row["gd_grader_id"]) == \app\models\User::$user_id) {
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
    s.".$user_section_param.",
    count(s.*) as total,
    case when gg.graded is null then 0 else gg.graded end
FROM
    users as s
    LEFT JOIN (
        SELECT
            uu.".$user_section_param.",
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
        GROUP BY uu.".$user_section_param."
    ) as gg ON s.".$user_section_param." = gg.".$user_section_param."
WHERE
    user_group=?
GROUP BY
    s.".$user_section_param.",
    gg.graded
ORDER BY
    s.".$user_section_param, array($g_id,4,4));

            $sections = Database::rows();
            $graded = 0;
            $total = 0;
            foreach ($sections as $section) {
                $graded += $section['graded'];
                $total += $section['total'];
            }

            $percent = round(($graded / $total) * 100);

            print <<<HTML
        <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
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
                                            : "SELECT gr.*, u.* FROM grading_rotating gr LEFT JOIN (SELECT * FROM users) as u ON gr.user_id = u.user_id WHERE user_group <=3 AND g_id=? ORDER BY gr.sections_rotating, u.user_id");
            
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
                <a class="btn" href="{$BASE_URL}/account/index.php">Select Different Homework</a>
                <a class="btn" href="{$BASE_URL}/account/account-summary.php?g_id={$_GET["g_id"]}">{$g_title} Overview</a>
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
                <a class="btn" href="{$BASE_URL}/account/index.php">Select Different Homework</a>
                <a class="btn" href="{$BASE_URL}/account/account-summary.php?g_id={$_GET["g_id"]}">{$g_title} Overview</a>
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
    
    $db->query("SELECT g_title, g.g_id, eg_submission_due_date FROM gradeable AS g INNER JOIN electronic_gradeable AS eg ON g.g_id=eg.g_id", $params);
    $results = $db->rows();

    if(count($results) > 0) {
        print <<<HTML
    <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
        <form action="{$BASE_URL}/account/index.php" method="get">
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
            $homeworkDate = new DateTime($row['eg_submission_due_date']);
            //TODO ADD LATE DAYS
            if ($row['eg_late_days'] > 0) {
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
            <a class="btn" href="{$BASE_URL}/account/index.php">Select Different Gradable</a>
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
<span id="grade" class="resbox draggable" style="padding:5px;" onmousedown="dragPanelStart(event, 'grade'); return false;" onmousemove="dragPanel(event, 'grade');"  onmouseup="dragPanelEnd(event);">
    <i title="Show/Hide Submission Info (Press S)" <?php echo "class='icon-status".(($show_stats == 0) ? "' ": " icon-selected'") ;?> onclick="handleKeyPress('KeyS')"></i>
    <i title="Show/Hide Grading Panel (Press G)" <?php echo "class='icon-grading-panel".(($show_rubric == 0) ? "' ": " icon-selected'") ;?> onclick="handleKeyPress('KeyG')"></i>
    <i title="Show/Hide Auto Grading Results (Press A)" <?php echo "class='icon-auto-grading-results".(($show_left == 0) ? "' ": " icon-selected'") ;?> onclick="handleKeyPress('KeyA');"></i>
    <i title="Show/Hide Files Viewer (Press F)" <?php echo "class='icon-files".(($show_right == 0) ? "' ": " icon-selected'") ;?> onclick="handleKeyPress('KeyF')"></i>
    <a <?php echo ($previous_user_id == "" ? "" : "href=\"{$BASE_URL}/account/index.php?course={$_GET['course']}&g_id={$_GET['g_id']}&prev={$previous_user_id}\""); ?> ><i title="Go to the previous student (Press Left Arrow)" class="icon-left <?php echo ($previous_user_id == "" ? 'icon-disabled"' : '"'); ?> ></i></a>
    <a href="<?php echo $BASE_URL; ?>/account/account-summary.php?g_id=<?php echo $_GET["g_id"]; ?>"><i title="Go to the main page (Press H)" class="icon-home" ></i></a>
    <a <?php echo ($next_user_id == "" ? "" : "href=\"{$BASE_URL}/account/index.php?course={$_GET['course']}&g_id={$_GET['g_id']}&next={$next_user_id}\""); ?> ><i title="Go to the next student (Press Right Arrow)" class="icon-right <?php echo ($next_user_id == "" ? 'icon-disabled"' : '"'); ?>></i></a>
    <i title="Pin Toolbar" class="icon-toolbar-up" ></i>
    <div style="width:100%; height: 15px; bottome:0;">
        <?php if($position_other == 0) { ?>
            <div class="progress" id="prog" style="border:#AAA solid 2px; ">
                <div class="bar bar-primary" style="width: <?php echo (($position_completed - $position_backup) / $position_total) * 95; ?>%;"><i class="icon-ok icon-white" id="progress-icon"></i></div>
                <div class="bar bar-warning" style="width: <?php echo ($position_backup / $position_total) * 95; ?>%;"><i class="icon-refresh icon-white" id="progress-icon"></i></div>
                <div class="bar" style="width: 5%; background-image: none; background-color: #777;"><?php echo round(($position_completed / $position_total) * 100, 1); ?>%</div>
            </div>
        <?php } else if($position_other < $position_total) { ?>
            <div class="progress" id="prog" style="border:#AAA solid 2px; ">
                <div class="bar bar-info" style="width: <?php echo ($position_other / $position_total) * 94; ?>%;"><i class="icon-ok icon-white" id="progress-icon"></i></div>
                <div class="bar" style="width: 1%; background-image: none; background-color: #777;"></div>
                <div class="bar bar-primary" style="width: <?php echo (($position_completed - $position_backup) / $position_total) * 94; ?>%;"><i class="icon-ok icon-white" id="progress-icon"></i></div>
                <div class="bar bar-warning" style="width: <?php echo ($position_backup / $position_total) * 94; ?>%;"><i class="icon-refresh icon-white" id="progress-icon"></i></div>
                <div class="bar" style="width: 5%; background-image: none; background-color: #777;"><?php echo round((($position_completed + $position_other) / $position_total) * 100, 1); ?>%</div>
            </div>
        <?php } else { ?>
            <div class="progress" id="prog" style="border:#AAA solid 2px; ">
                <div class="bar bar-info" style="width: <?php echo ($position_other / $position_total) * 95; ?>%;"><i class="icon-ok icon-white" id="progress-icon"></i></div>
                <div class="bar" style="width: 5%; background-image: none; background-color: #777;"><?php echo round(($position_other / $position_total) * 100, 1); ?>%</div>
            </div>
        <?php } ?>
    </div>
</span>

<?php } ?>
<script type="text/javascript">
    var mousedown = false;
    var dragging_panel = false;

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

    $('#content').scroll(function() {
        if($("#rubric-autoscroll-checkbox").is(':checked')) {
            var scrollPercentage = this.scrollTop / (this.scrollHeight-this.clientHeight);
            document.getElementById('rubric').scrollTop = scrollPercentage * (document.getElementById('rubric').scrollHeight-document.getElementById('rubric').clientHeight);
        }
    });

    document.getElementById('container').style.width = window.innerWidth + 'px';
    document.getElementById('container').style.height = (window.innerHeight - 40) + 'px';

    var width = window.innerWidth - 7;
    var height = window.innerHeight - 40;

    // get cookies of panel size and position
    var split = 0;
    split = <?php echo ($split == "" || $split < 25 || 75 < $split ? 50 : $split); ?> / 100.0;
    if (document.getElementById('left') != null) {
        document.getElementById('left').style.width = (width * (split)) + 'px';
    }
    if (document.getElementById('right') != null) {
        document.getElementById('right').style.width = ((width * (1.0 - split)) ) + 'px';
    }
    if (document.getElementById('panemover') != null) {
        document.getElementById('panemover').style.left = document.getElementById('pane').offsetLeft + 'px';
        document.getElementById('panemover').style.width = '10px';
    }
    if(document.getElementById('stats') != null) {
        stats_width = (<?php echo $stats_width;?> == 0) ? 0.2 * width : <?php echo $stats_width;?>;
        stats_height = (<?php echo $stats_height;?> == 0) ? 0.4 * height : <?php echo $stats_height;?>;
        document.getElementById('stats').style.width = stats_width + "px";
        document.getElementById('stats').style.height = stats_height + "px";

        stats_left = (<?php echo $stats_left;?> == 0) ? 0.1 * width : <?php echo $stats_left;?>;
        stats_top = (<?php echo $stats_top;?> == 0) ? 0.55 * height : <?php echo $stats_top;?>;
        document.getElementById('stats').style.left = stats_left + "px";
        document.getElementById('stats').style.top = stats_top + "px";
    }

    if(document.getElementById('rubric') != null) {
        rubric_width = (<?php echo $rubric_width;?> == 0) ? 0.45 * width : <?php echo $rubric_width;?>;
        rubric_height = (<?php echo $rubric_height;?> == 0) ? 0.80 * height : <?php echo $rubric_height;?>;
        document.getElementById('rubric').style.width = rubric_width + "px";
        document.getElementById('rubric').style.height = rubric_height + "px";

        rubric_left = (<?php echo $rubric_left;?> == 0) ? 0.4 * width : <?php echo $rubric_left;?>;
        rubric_top = (<?php echo $rubric_top;?> == 0) ? 0.15 * height : <?php echo $rubric_top;?>;
        document.getElementById('rubric').style.left = rubric_left + "px";
        document.getElementById('rubric').style.top = rubric_top + "px";
    }

    window.onload = updateDisplay();

    window.onresize = function(event) {
        document.getElementById('grade').style.left = parseFloat(window.innerWidth * document.getElementById('grade').offsetLeft/(width + 7)) + 'px';
        document.getElementById('rubric').style.left = parseFloat(window.innerWidth * document.getElementById('rubric').offsetLeft/(width + 7)) + 'px';
        document.getElementById('stats').style.left = parseFloat(window.innerWidth * document.getElementById('stats').offsetLeft/(width + 7)) + 'px';

        document.getElementById('grade').style.top = parseFloat(window.innerHeight * document.getElementById('grade').offsetTop/height) + 'px';
        document.getElementById('rubric').style.top = parseFloat(window.innerHeight * document.getElementById('rubric').offsetTop/height) + 'px';
        document.getElementById('stats').style.top = parseFloat(window.innerHeight * document.getElementById('stats').offsetTop/height) + 'px';

        width = window.innerWidth - 7;
        height = window.innerHeight;
        split = (parseInt(document.getElementById('left').style.width) / (parseInt(document.getElementById('left').style.width) + parseInt(document.getElementById('right').style.width))).toFixed(2);

        document.getElementById('left').style.width = (width * split) + 'px';
        document.getElementById('right').style.width = (width * (1 - split)) + 'px';

        // document.getElementById('prog').style.width = (document.documentElement.clientWidth - 157) + 'px';
        document.getElementById('container').style.width = window.innerWidth + 'px';
        document.getElementById('container').style.height = (window.innerHeight - 40) + 'px';
        document.getElementById('panemover').style.left = document.getElementById('pane').offsetLeft + 'px';
        updateDisplay();
    };

    window.onkeydown = function(e) {
        if (e.target.tagName == "TEXTAREA" || e.target.tagName == "INPUT" || e.target.tagName == "SELECT") return; // disable keyboard event when typing to textarea/input
        handleKeyPress(e.code);
    };

    function handleKeyPress(key) {
        switch (key) {
            case "KeyG":
                $('#grade .icon-grading-panel').toggleClass('icon-selected');
                togglePanel("rubric");
                break;
            case "KeyS":
                $('#grade .icon-status').toggleClass('icon-selected');
                togglePanel("stats");
                break;
            case "KeyF":
                $('#grade .icon-files').toggleClass('icon-selected');
                togglePanel("right");
                updateDisplay();
                break;
            case "KeyA":
                $('#grade .icon-auto-grading-results').toggleClass('icon-selected');
                togglePanel("left");
                updateDisplay();
                break;
            case "KeyR": // Reset all cookies to default // TODO: Checkbox that lets user choose to save cookie or not?
                setCookie("show_stats", 0, -180*24*60*60);
                setCookie("show_rubric", 0, -180*24*60*60);
                setCookie("show_left", 1, -180*24*60*60);
                setCookie("show_right", 1, -180*24*60*60);
                setCookie("stats_width", 0, -180*24*60*60);
                setCookie("stats_height", 0, -180*24*60*60);
                setCookie("stats_left", 0, -180*24*60*60);
                setCookie("stats_top", 0, -180*24*60*60);
                setCookie("rubric_width", 0, -180*24*60*60);
                setCookie("rubric_height", 0, -180*24*60*60);
                setCookie("rubric_left", 0, -180*24*60*60);
                setCookie("rubric_top", 0, -180*24*60*60);
                setCookie("grade_left", 0, -180*24*60*60);
                setCookie("grade_top", 0, -180*24*60*60);
                break;
            default:
                break;
        }
    }

    function setCookie(name, value, seconds) {
        var date = new Date();
        date.setTime(date.getTime()+(seconds*1000));
        var expires = "; expires="+date.toGMTString();
        var path = "; path=/;";
        document.cookie = name + "=" + value + expires + path;
    }

    // show or hide the panels and remember user preference
    function togglePanel(id) {
        document.getElementById(id).style.display = document.getElementById(id).style.display == "none" ? "inline-block" : "none";
        setCookie("show_"+id, (document.getElementById(id).style.display == "none" ? 0 : 1), 180*24*60*60);
    }

    // update display for auto-grading results and files panel
    function updateDisplay() {
        var left = document.getElementById('left');
        var right =  document.getElementById('right');
        var pane = document.getElementById('pane');
        var panemover = document.getElementById('panemover');
        if(left == null || right == null) return;
        if(left.style.display != "none" && right.style.display != "none") {
            pane.style.display = "inline-block";
            panemover.style.display = "inline-block";
            left.style.width = (width * split) + 'px';
            right.style.width = (width * (1 - split)) + 'px';
            panemover.style.left = pane.offsetLeft + 'px';
        }
        else {
            pane.style.display = "none";
            panemover.style.display = "none";
            if(left.style.display != "none") {
                left.style.width = window.innerWidth + 'px';
            }
            else {
                right.style.width = window.innerWidth + 'px';
            }
        }
    }

    // ========================================================
    // Drag to resize auto-grading results and files
    function dragStart(e, left, right) {
        document.getElementById('panemover').style.width = '100%';
        document.getElementById('panemover').style.left = '0px';

        mousedown = true;
        x = e.clientX;
        dragOffsetLeft = parseFloat(document.getElementById(left).style.width.substring(0, document.getElementById(left).style.width.length - 2)) - x;
        dragOffsetRight = parseFloat(document.getElementById(right).style.width.substring(0, document.getElementById(right).style.width.length - 2)) + x;
    }

    function dragRelease() {
        document.getElementById('panemover').style.width = '10px';
        document.getElementById('panemover').style.left = document.getElementById('pane').offsetLeft + 'px';
        split = parseFloat(document.getElementById('left').style.width) / (parseFloat(document.getElementById('left').style.width) 
                + parseFloat(document.getElementById('right').style.width));

        var splitVar = parseFloat(100 * (parseFloat(document.getElementById('left').style.width) / (parseFloat(document.getElementById('left').style.width) 
                       + parseFloat(document.getElementById('right').style.width)))).toFixed(2);
        setCookie("split", splitVar, 180*24*60*60);

        mousedown = false;
    }

    function drag(e, left, right) {
        if (!mousedown) {
            return;
        }
        x = e.clientX;
        tmpLeft = dragOffsetLeft + x;
        tmpRight = dragOffsetRight - x;

        minWidthLeft = (window.innerWidth - 7) * 0.30;
        minWidthRight = (window.innerWidth - 7) * 0.25;
        if (tmpLeft < minWidthLeft || tmpRight < minWidthRight) {
            return
        }

        document.getElementById('left').style.width = tmpLeft + 'px';
        document.getElementById('right').style.width = tmpRight + 'px';
    }

    // ========================================================
    // Drag to move toolbar/grading/status panel around
    function dragPanelStart(e, id) {
        if (e.target.tagName == "TEXTAREA" || e.target.tagName == "INPUT" || e.target.tagName == "SELECT") return; // disable dragging when editing textarea/input
        if (hasClass(e.target, "ui-resizable-handle")) return; // disable dragging when resizing panel
        dragging_panel = true;
        mouse_x = e.clientX;
        mouse_y = e.clientY;
        drag_id = id;
    }
    function dragPanel(e, id) {
        if(!dragging_panel) return;
        if(id != drag_id) return;

        var element = document.getElementById(id);
        element.style.left = (element.offsetLeft - (mouse_x - e.clientX)) + 'px';
        element.style.top = (element.offsetTop - (mouse_y - e.clientY)) + 'px';
        mouse_x = e.clientX;
        mouse_y = e.clientY;
        savePosition(id);
        /*
        // TODO: Add check for dragging off sight or add a function to reset panel positions somewhere?
        if((grade.offsetRight - grade.offsetWidth) < 5) {
            grade.style.right = '5px';
        }
        if((grade.offsetBottom - grade.offsetHeight) < 5) {
            grade.style.bottom = '5px';
        }
        */
    }

    function dragPanelEnd(e) {
        dragging_panel = false;
    }

    // checks if an element has a class using javascript, may or may not be useful
    function hasClass(element, cls) {
        return (' ' + element.className + ' ').indexOf(' ' + cls + ' ') > -1;
    }

    // Set grading panel and status panel to be resizable
    $('#stats').resizable({
        handles: 'n, e, s, w',
        minWidth: 50,
        maxWidth: 500,
        minHeight: 50,
        maxHeight: 500,
        resize: function () {
            saveSize("stats");
            savePosition("stats");
        }
    });

    $('#rubric').resizable({
        handles: 'n, e, s, w',
        minWidth: 200,
        maxWidth: 1000,
        minHeight: 200,
        maxHeight: 1000,
        resize: function () {
            saveSize("rubric");
            savePosition("rubric");
        }
    });

    function saveSize(id) {
        var ele = document.getElementById(id);
        setCookie( id + "_width", ele.offsetWidth, 180*24*60*60);
        setCookie(id + "_height", ele.offsetHeight, 180*24*60*60);
    }

    function savePosition(id) {
        var ele = document.getElementById(id);
        setCookie( id + "_left", ele.offsetLeft, 180*24*60*60);
        setCookie(id + "_top", ele.offsetTop, 180*24*60*60);
    }

    // Place the panel selected on top of other panels (if overlaping).
    function changeStackingOrder(e) {
        if(e.currentTarget.style.zIndex == "") return;
        var tmpElement = document.getElementById( e.currentTarget.id == "stats" ? "rubric" : "stats" );
        if( tmpElement.style.display != "none" && e.currentTarget.style.zIndex < tmpElement.style.zIndex) {
            var tmp = e.currentTarget.style.zIndex;
            e.currentTarget.style.zIndex = tmpElement.style.zIndex;
            tmpElement.style.zIndex = tmp;
        }
        e.stopPropagation();
    }

    eraseCookie("reset");

</script>

<?php include "../footer.php"; ?>
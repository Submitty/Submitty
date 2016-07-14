<?php
include "../header.php";

use app\models\User;
use lib\Database;

$account_subpages_unlock = true;

$split = floatval($_COOKIE["split"]);
$show_stats = isset($_COOKIE["show_stats"]) ? intval($_COOKIE["show_stats"]) : 0;
$show_rubric = isset($_COOKIE["show_rubric"]) ? intval($_COOKIE["show_rubric"]) : 0;
$show_left = isset($_COOKIE["show_left"]) ? intval($_COOKIE["show_left"]) : 1;
$show_right = isset($_COOKIE["show_right"]) ? intval($_COOKIE["show_right"]) : 1;
$rubric_late_days = 0;

print <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
HTML;
if(isset($_GET["hw"])) {
    $homework_id = $_GET["hw"];
    $params = array($homework_id);
    $db->query("SELECT rubric_id, rubric_name, rubric_parts_sep, rubric_late_days, rubric_due_date FROM rubrics WHERE rubric_id=?", $params);
    $rubric = $db->row();
}

if(isset($_GET["hw"]) && isset($rubric["rubric_id"])) {
    $rubric_id = $rubric["rubric_id"];
    $rubric_name = $rubric['rubric_name'];
    $rubric_sep = $rubric["rubric_parts_sep"];
    $rubric_late_days = $rubric['rubric_late_days'];
    $rubric_due_date = $rubric['rubric_due_date'];
    $student_rcs = null;
    $student_first_name = null;
    $student_last_name = null;
    $student_allowed_lates = 0;
    $position_completed = 0;
    $position_total = 0;
    $position_other = 0;
    $student_individual_graded = false;
    $previous_rcs = "";
    $next_rcs = "";

    if(isset($_GET["individual"]) || isset($_GET['prev']) || isset($_GET['next'])) {
        if(isset($_GET['individual'])) {
            $student_rcs = $_GET["individual"];
        } else if(isset($_GET['prev'])) {
            $student_rcs = $_GET['prev'];
        } else {
            $student_rcs = $_GET['next'];
        }

        $params = array($student_rcs);
        $db->query("SELECT * FROM students WHERE student_rcs=?", $params);
        $temp_row = $db->row();
        //$student_id = intval($temp_row["student_id"]);
        $student_first_name = $temp_row["student_first_name"];
        $student_last_name = $temp_row["student_last_name"];
        $params = array($temp_row['student_rcs'], $rubric['rubric_due_date']);
        $db->query("SELECT * FROM late_days WHERE student_rcs=? AND since_timestamp <= ? ORDER BY since_timestamp DESC LIMIT 1", $params);
        $lates = $db->row();
        $student_allowed_lates = isset($lates['allowed_lates']) ? intval($lates['allowed_lates']) : 0;

        $params = array($student_rcs, $rubric_id);
        $db->query("SELECT grade_id FROM grades WHERE student_rcs=? and rubric_id=?", $params);
        $temp_row = $db->row();
        $student_individual_graded = isset($temp_row["grade_id"]);
    }

    $params = array(User::$user_id, $rubric_id);
    $db->query("SELECT * FROM homework_grading_sections WHERE user_id=? AND rubric_id=? ORDER BY grading_section_id", $params);
    //$db->query("SELECT section_id FROM relationships_users WHERE user_id=? ORDER BY section_id", $params);
    foreach ($db->rows() as $section) {
        $params = array($rubric_id, intval($section["grading_section_id"]));
        $db->query("
SELECT
    s.student_rcs
    , s.student_first_name
    , s.student_last_name
    , case when g.number_graded is null then 0 else g.number_graded end
FROM
    students AS s
    LEFT JOIN (
    SELECT
        student_rcs
        , count(grade_id) as number_graded
    FROM
        grades
    WHERE
        rubric_id=?
    GROUP BY
        student_rcs
    ) AS g
    ON s.student_rcs=g.student_rcs
WHERE
    s.student_grading_id=?
ORDER BY
    s.student_rcs ASC
                ", $params);

        $prev_row = array('student_rcs' => "");
        $set_next = false;

        foreach ($db->rows() as $row) {
            $temp_row = $row;

            if($set_next) {
                $next_rcs = $temp_row['student_rcs'];
                $set_next = false;
            }

            if($student_rcs != null) {
                if($student_rcs == $temp_row['student_rcs']) {
                    $previous_rcs = $prev_row['student_rcs'];
                    $set_next = true;
                }
            }

            if(intval($temp_row["number_graded"]) == 0) {
                if($student_rcs == null) {
                    $params = array($row["student_rcs"]);
                    $db->query("SELECT * FROM students WHERE student_rcs=?", $params);
                    $row = $db->row();

                    $student_first_name = $row["student_first_name"];
                    $student_last_name = $row["student_last_name"];
                    $student_rcs = $row["student_rcs"];
                    $previous_rcs = $prev_row['student_rcs'];

                    $params = array($row['student_rcs'], $rubric_due_date);
                    $db->query("SELECT * FROM late_days WHERE student_rcs=? AND since_timestamp <= ?", $params);
                    $lates = $db->row();
                    $student_allowed_lates = intval($lates['allowed_lates']);

                    $set_next = true;
                }
            } else {
                $params = array($row['student_rcs'], $rubric_id);
                $db->query("SELECT grade_user_id FROM grades WHERE student_rcs=? AND rubric_id=?", $params);
                $temp_row = $db->row();

                if(intval($temp_row["grade_user_id"]) == \app\models\User::$user_id) {
                    $position_completed++;
                } else {
                    $position_other++;
                }
            }

            $position_total++;
            $prev_row = $row;
        }
    }

    if($student_rcs != null) {
        include "account-rubric.php";
    } else {
        if (User::$is_administrator) {
            Database::query("
SELECT
    s.student_grading_id,
    count(s.*) as total,
    case when gg.graded is null then 0 else gg.graded end
FROM
    students as s
    LEFT JOIN (
        SELECT
            ss.student_grading_id,
            count(*) as graded
        FROM
            grades as g
            LEFT JOIN (
                SELECT
                    student_grading_id,
                    student_rcs
                FROM
                    students
            ) as ss ON g.student_rcs = ss.student_rcs
        WHERE
            g.rubric_id=?
        GROUP BY ss.student_grading_id
    ) as gg ON s.student_grading_id = gg.student_grading_id
GROUP BY
    s.student_grading_id,
    gg.graded
ORDER BY
    s.student_grading_id", array($rubric_id));

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
                <h3 id="myModalLabel">{$rubric_name} Grading Status</h3>
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
                print "Section {$section['student_grading_id']}: {$section['percent']}% ({$section['graded']} / {$section['total']})<br />";
            }
            print <<<HTML
                </div>
                <br />
                Graders:
                <div style="margin-left: 20px">
HTML;
            Database::query("SELECT gs.*, u.* FROM homework_grading_sections as gs LEFT JOIN (SELECT * FROM users) as u ON gs.user_id = u.user_id WHERE gs.rubric_id=? ORDER BY gs.grading_section_id, u.user_rcs", array($rubric_id));
            $graders = array();
            foreach (Database::rows() as $row) {
                if(!isset($graders[$row['grading_section_id']])) {
                    $graders[$row['grading_section_id']] = array();
                }
                $graders[$row['grading_section_id']][] = "{$row['user_firstname']} {$row['user_lastname']} ({$row['user_rcs']})";
            }

            foreach ($sections as $section) {
                if(isset($graders[$section['student_grading_id']])) {
                    print $section['student_grading_id'] . ": " . implode(",", $graders[$section['student_grading_id']]) . "<br />";
                } else {
                    print $section['student_grading_id'] . ": Nobody";
                }
            }

            print <<<HTML
                </div>
            </div>

            <div class="modal-footer">
                <a class="btn" href="{$BASE_URL}/account/index.php">Select Different Homework</a>
                <a class="btn" href="{$BASE_URL}/account/account-summary.php?hw={$_GET["hw"]}">{$rubric_name} Overview</a>
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
                Congratulations, you have finished grading {$rubric_name}.
                <br/>
                <br/>
                <i style="color:#777;">You can review the grades you have saved by using the navigation buttons at the bottom-right of the page or by going to the homework overview page.</i>
            </div>

            <div class="modal-footer">
                <a class="btn" href="{$BASE_URL}/account/index.php">Select Different Homework</a>
                <a class="btn" href="{$BASE_URL}/account/account-summary.php?hw={$_GET["hw"]}">{$rubric_name} Overview</a>
                <!--<a class="btn btn-primary" href="/logout.php">Logout</a>-->
            </div>
        </div>
    </div>
HTML;
        }
    }
}
else if(!isset($_GET["hw"])) {

    $params = array();
    $db->query("SELECT rubric_name, rubric_id, rubric_due_date, rubric_late_days FROM rubrics ORDER BY rubric_due_date ASC, rubric_id ASC", $params);
    $results = $db->rows();

    if(count($results) > 0) {
        print <<<HTML
    <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
        <form action="{$BASE_URL}/account/index.php" method="get">
            <div class="modal-header">
                <h3 id="myModalLabel">Select Homework</h3>
            </div>

            <div class="modal-body" style="vertical-align: middle; padding-top:20px; padding-bottom:20px;">
                <select style="width:400px" name="hw">
HTML;

        $last = end($results);
        reset($results);
        $c = 0;
        $now = new DateTime('now');
        foreach($results as $row) {
            $homeworkDate = new DateTime($row['rubric_due_date']);
            if ($row['rubric_late_days'] > 0) {
                $homeworkDate->add(new DateInterval("PT{$row['rubric_late_days']}H"));
            }
            $extra = ($now < $homeworkDate) ? "(Gradable: {$homeworkDate->format("Y-m-d H:i:s")})" : "";
            echo "<option value='{$row['rubric_id']}' ".($last["rubric_id"] == $row["rubric_id"] ? "selected" : "").">{$row['rubric_name']} {$extra}</option>";
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
            <h3 id="myModalLabel">Homeworks Not Available Yet</h3>
        </div>

        <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
            No homeworks have been added to the system yet.
        </div>
    </div>
HTML;
    }
}
else {
    print <<<HTML
    <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
        <div class="modal-header">
            <h3 id="myModalLabel">Rubric Error</h3>
        </div>

        <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
            The rubric you are looking for cannot be found
        </div>

        <div class="modal-footer">
            <a class="btn" href="{$BASE_URL}/account/index.php">Select Different Homework</a>
        </div>
    </div>
HTML;
}
print <<<HTML
</div>
HTML;

// TODO: Rewrite this (Issue #43)
if(isset($_GET["hw"]) && isset($rubric_id)) {
    if($position_total == 0) {
        $position_total = 0.001;
    }

    ?>
<span id="grade" class="resbox draggable" style="padding:5px;" onmousedown="dragPanelStart(event, 'grade'); return false;" onmousemove="dragPanel(event, 'grade');"  onmouseup="dragPanelEnd(event);">
    <i title="Show/Hide Submission Info (Press S)" <?php echo "class='icon-status".(($show_stats == 0) ? "' ": " icon-selected'") ;?> onclick="handleKeyPress('KeyS')"></i>
    <i title="Show/Hide Grading Panel (Press G)" <?php echo "class='icon-grading-panel".(($show_rubric == 0) ? "' ": " icon-selected'") ;?> onclick="handleKeyPress('KeyG')"></i>
    <i title="Show/Hide Auto Grading Results (Press A)" <?php echo "class='icon-auto-grading-results".(($show_left == 0) ? "' ": " icon-selected'") ;?> onclick="handleKeyPress('KeyA');"></i>
    <i title="Show/Hide Files Viewer (Press F)" <?php echo "class='icon-files".(($show_right == 0) ? "' ": " icon-selected'") ;?> onclick="handleKeyPress('KeyF')"></i>
    <a <?php echo ($previous_rcs == "" ? "" : "href=\"{$BASE_URL}/account/index.php?course={$_GET['course']}&hw={$_GET['hw']}&prev={$previous_rcs}\""); ?> ><i title="Go to the previous student (Press Left Arrow)" class="icon-left <?php echo ($previous_rcs == "" ? 'icon-disabled"' : '"'); ?> ></i></a>
    <a href="<?php echo $BASE_URL; ?>/account/account-summary.php?hw=<?php echo $_GET["hw"]; ?>"><i title="Go to the main page (Press H)" class="icon-home" ></i></a>
    <a <?php echo ($next_rcs == "" ? "" : "href=\"{$BASE_URL}/account/index.php?course={$_GET['course']}&hw={$_GET['hw']}&next={$next_rcs}\""); ?> ><i title="Go to the next student (Press Right Arrow)" class="icon-right <?php echo ($next_rcs == "" ? 'icon-disabled"' : '"'); ?>></i></a>
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

    // var progressBar = document.getElementById('pro');
    // if (progressBar != null) {
    //     progressBar.style.width = (document.documentElement.clientWidth - 157) + 'px';
    // }

    document.getElementById('container').style.width = window.innerWidth + 'px';
    document.getElementById('container').style.height = (window.innerHeight - 40) + 'px';

    var split = 0;
    var width = window.innerWidth - 7;
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

    window.onload = updateDisplay();

    window.onresize = function(event) {
        document.getElementById('grade').style.left = parseFloat(window.innerWidth * document.getElementById('grade').offsetLeft/(width + 7)) + 'px';
        document.getElementById('rubric').style.left = parseFloat(window.innerWidth * document.getElementById('rubric').offsetLeft/(width + 7)) + 'px';
        document.getElementById('stats').style.left = parseFloat(window.innerWidth * document.getElementById('stats').offsetLeft/(width + 7)) + 'px';

        width = window.innerWidth - 7;
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
        if (e.target.type == "textarea" || e.target.type == "input")  return; // disable keyboard event when typing to textarea/input
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
                setCookie("show_stats", 0, 180*24*60*60);
                setCookie("show_rubric", 0, 180*24*60*60);
                setCookie("show_left", 1, 180*24*60*60);
                setCookie("show_right", 1, 180*24*60*60);
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
        split = parseFloat(document.getElementById('left').style.width) / (parseFloat(document.getElementById('left').style.width) + parseFloat(document.getElementById('right').style.width));

        var splitVar = parseFloat(100 * (parseFloat(document.getElementById('left').style.width) / (parseFloat(document.getElementById('left').style.width) + parseFloat(document.getElementById('right').style.width)))).toFixed(2);
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
        if (e.target.tagName == "TEXTAREA" || e.target.tagName == "INPUT" || e.target.tagName == "SELECT") // disable dragging when editing textarea/input
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
        maxHeight: 500
    });

    $('#rubric').resizable({
        handles: 'n, e, s, w',
        minWidth: 200,
        maxWidth: 1000,
        minHeight: 200,
        maxHeight: 1000
    });

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
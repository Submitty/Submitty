<?php
use app\models\User;

include "../header.php";

$account_subpages_unlock = true;

if (!User::$is_administrator) {
    if (isset($_GET['all']) && $_GET['all'] == "true") {
        $button = "<a class='btn' href='{$BASE_URL}/account/account-checkpoints-gradeable.php?course={$_GET['course']}'>View Your Sections</a>";
    }
    else {
        $button = "<a class='btn' href='{$BASE_URL}/account/account-checkpoints-gradeable.php?course={$_GET['course']}&all=true'>View All Sections</a>";
    }
}
else {
    $button = "";
}

print <<<HTML
<style type="text/css">
    body {
        overflow: scroll;
    }

    #container-g-checkpoints
    {
        width:700px;
        margin:100px auto;
        margin-top: 130px;
        background-color: #fff;
        border: 1px solid #999;
        border: 1px solid rgba(0,0,0,0.3);
        -webkit-border-radius: 6px;
        -moz-border-radius: 6px;
        border-radius: 6px;outline: 0;
        -webkit-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        -moz-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        -webkit-background-clip: padding-box;
        -moz-background-clip: padding-box;
        background-clip: padding-box;
    }
    #g-checkpoints-table td
    {
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -khtml-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    .tooltip-inner {
        white-space:pre-wrap;
    }
</style>

<div id="container-g-checkpoints">
    <div class="modal-header">
        <!--<h3 id="myModalLabel" style="width:20%; display:inline-block;">Labs</h3>-->
        <span style="width: 29%; display:inline-block;">{$button}</span>
        <div style="text-align:right; width:49%; display:inline-block;">
            <i class="icon-question-sign" rel="tooltip" title="No Color - No Credit
Dark Blue - Full Credit
Light Blue - Half Credit
Red - [SAVE ERROR] Refresh Page"></i>
        </div>

    </div>

    <div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
        <div class="bs-docs-example">
            <ul id="myTab" class="nav nav-tabs">
HTML;

$params = array(1);
$db->query("SELECT * FROM gradeable WHERE g_gradeable_type=? ORDER BY g_grade_start_date ASC", $params);

$first = true;

// remove lab tab references

foreach($db->rows() as $c_gradeable_row) {
    print <<<HTML
            <li class="lab_tab
HTML;
    print ($first)?" active":"";
    print <<<HTML
            "><a href="#lab{$c_gradeable_row["g_id"]}" data-toggle="tab">{$c_gradeable_row["g_title"]}</a></li>
HTML;
    $first = false;
}

print <<<HTML
            </ul>
            <div id="myTabContent" class="tab-content">
HTML;


$first = true;

// rename these vars without lab
foreach($db->rows() as $lab_row) {
    $params = array($lab_row['g_id']);
    $db->query("SELECT gc_title from gradeable_component WHERE g_id=?", $params);
    $lab_row_checkpoints = array();
    foreach($db->rows() as $row){
        array_push($lab_row_checkpoints, $row['gc_title']);
    }
    $active = ($first) ? 'active in' : '';
    print <<<HTML
                <div class="tab-pane fade {$active}" id="lab{$lab_row["g_id"]}">
                    <table class="table table-bordered striped-table" id="g-checkpoints-table" style=" border: 1px solid #AAA;">
                        <thead style="background: #E1E1E1;">
                            <tr>
                                <th>RCS ID</th>
HTML;
    foreach($lab_row_checkpoints as $checkpoint) {
        print <<<HTML
                                <th>{$checkpoint}</th>
HTML;
    }
    print <<<HTML
                            </tr>
                        </thead>
HTML;

    $params = array($user_id);
    if((isset($_GET["all"]) && $_GET["all"] == "true") || $user_is_administrator == true) {
        $params = array();
        $db->query("SELECT * FROM sections ORDER BY section_id ASC", $params);
    }
    else {
        $params = array($user_id);
        $db->query("SELECT * FROM relationships_users WHERE user_id=? ORDER BY section_id ASC", $params);
    }

    // TODO bring back the print lab part
    foreach($db->rows() as $section) {
        $count = count($lab_row_checkpoints) + 1;
        print <<<HTML
                        <tr class="info">
                            <td colspan="{$count}" style="text-align:center;" id="section-{$section['section_id']}">
                                    Students Enrolled in Section {$section["section_id"]}
                                   <!-- <a href="{$BASE_URL}/account/print/print_lab.php?course={$_GET['course']}&lab_id={$lab_row['lab_id']}&section_id={$section['section_id']}">
                                        <div class="icon-print"></div>
                                    </a>-->
                            </td>
                        </tr>
                        <tbody>
HTML;
        $params = array($lab_row["g_id"],intval($section["section_id"]));
        // rewrite this query
        $db->query("
        
SELECT
    s.student_rcs
    , s.student_id
    , s.student_first_name
    , s.student_last_name
    , case when gcds.grade_value_array is null then '{}' else gcds.grade_value_array end
    , case when gcds.grade_checkpoint_array is null then '{}' else gcds.grade_checkpoint_array end
    , g_id
FROM
    students AS s
    LEFT JOIN (
        SELECT
            array_agg(gcd_score) as grade_value_array
            , array_agg(gc_order) as grade_checkpoint_array
            , gd_user_id
            , g_id
        FROM
            gradeable_component_data AS gcd RIGHT JOIN (
                SELECT 
                    gd.g_id
                    ,gd_id
                    ,gc_id
                    ,gc_order
                    ,gd_user_id
                    
                FROM 
                    gradeable_data AS gd INNER JOIN (
                        SELECT
                            g.g_id
                            , gc_id
                            , gc_order
                        FROM 
                            gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id = gc.g_id
                        WHERE g.g_gradeable_type=1
                    ) AS components ON components.g_id = gd.g_id    
            ) AS data_components ON gcd.gc_id = data_components.gc_id AND gcd.gd_id = data_components.gd_id                
        WHERE
            g_id=? 
        GROUP BY
            gd_user_id
            , g_id
    ) AS gcds ON gcds.gd_user_id = s.student_rcs
WHERE
    s.student_section_id=?
ORDER BY
    s.student_rcs", $params);

        foreach($db->rows() as $row) {
            $grade_value_array = pgArrayToPhp($row['grade_value_array']);
            $grade_checkpoint_array = pgArrayToPhp($row['grade_checkpoint_array']);
            if (count($grade_checkpoint_array) > 0 && count($grade_value_array) == count($grade_checkpoint_array)) {
                $grades = array_combine($grade_checkpoint_array,$grade_value_array);
            }
            else {
                $grades = array();
            }

            $student_info = $row;
            print <<<HTML
                            <tr>
                                <td class="cell-all" id="cell-{$lab_row["g_id"]}-all-{$row["student_rcs"]}" cell-status="0">
                                    {$student_info["student_rcs"]} ({$student_info["student_last_name"]}, {$student_info["student_first_name"]})
                                </td>
HTML;
            $count = 1;

            foreach($lab_row_checkpoints as $checkpoint) {
                if(isset($grades[$count])) {
                    $grade_value = $grades[$count];
                }
                else {
                    $grade_value = 0;
                }
                $mode = $grade_value;

                if($mode == 0) {
                    $background_color = "transparent";
                    $background_color = "";
                }
                elseif($mode == 1) {
                    $background_color = "#149bdf";
                    $background_color = "background-color:#149bdf";
                }
                elseif($mode == 2) {
                    $background_color = "#88d0f4";
                    $background_color = "background-color:#88d0f4";
                }

                print <<<HTML
                                <td id="cell-{$lab_row["g_id"]}-check{$count}-{$row["student_rcs"]}" cell-status="{$mode}" style="{$background_color}"></td>
HTML;
                $count++;
            }
            print <<<HTML
                            </tr>
HTML;
        }
        print <<<HTML
                        </tbody>
HTML;
    }
    print <<<HTML
                    </table>
                </div>
HTML;

    $first = false;
}
print <<<HTML
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">

    //TODO rename stuff

    $("td[id^=cell-]").click(function() {
        var cell_status = (parseInt($(this).attr('cell-status')) + 1) % 3;
        var name = $(this).attr("id");
        name = name.split("-");
        var gradeable = name[1];
        var check = name[2].replace("check", "");
        var rcs = name[3];
        
        console.log("Name:" + name);
        console.log("Gradeable:" + gradeable);
        console.log("Check:" + check);
        console.log("RCS:" + rcs);
        console.log("Mode:" + cell_status);
            
        var url = "{$BASE_URL}/account/ajax/account-checkpoints-gradeable.php?course={$_GET['course']}&g_id=" + gradeable + "&check=" + check + "&rcs=" + rcs + "&mode=" + cell_status;

        if($(this).hasClass("cell-all")) {
            // Named cell
            $(this).attr('cell-status', cell_status);
            updateColor("td[id^=cell-" + gradeable + "-check][id$=-" + rcs + "]", cell_status, url);
        }
        else {
            // Non-named cell
            updateColor(this, cell_status, url);
        }
    });

    function updateColor(item, mode, url) {
        $(item).attr('cell-status', mode);

        if(mode == 0) {
            $(item).css("background-color", "");
            $(item).css("border-right", "15px solid #ddd");
        }
        else if(mode == 1) {
            $(item).css("background-color", "#149bdf");
            $(item).css("border-right", "15px solid #f9f9f9");
        }
        else if(mode == 2) {
            $(item).css("background-color", "#88d0f4");
            $(item).css("border-right", "15px solid #f9f9f9");
        }

        submitAJAX(url, updateSuccess, updateFail, item);
    }

    function updateSuccess(item) {
        $(item).stop(true, true).animate({"border-right-width":"0px"}, 400);
    }

    function updateFail(item) {
        $(item).css("border-right-width", "15px");
        $(item).stop(true, true).animate({"border-right-color":"#DA4F49"}, 400);
    }

    function submitAJAX(url, callBackSucess, callBackFail, item) {
        $.ajax(url, {
            type: "POST",
            data: {
                csrf_token: '{$_SESSION['csrf']}'
            }
        })
        .done(function(response) {
            if(response == "updated") {
                callBackSucess(item);
            }
            else {
                callBackFail(item);
                console.log(response);
            }
        })
        .fail(function() {
            window.alert("[SAVE ERROR] Refresh Page");
        });
    }

    $(document).ready(function(){
        $("[rel=tooltip]").tooltip({ placement: 'top'});
    });
</script>
HTML;

include "../footer.php";

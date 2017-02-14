<?php
use models\User;

include "../header.php";

$account_subpages_unlock = true;

if (!User::$is_administrator

   // FIXME
   //  && is_full_access_grader

   ) {

    if (isset($_GET['all']) && $_GET['all'] == "true") {
        $button = "<a class='btn' href='{$BASE_URL}/account/account-checkpoints-gradeable.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id={$_GET['g_id']}'>View Your Sections</a>";
    }
    else {
        $button = "<a class='btn' href='{$BASE_URL}/account/account-checkpoints-gradeable.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id={$_GET['g_id']}&all=true'>View ALL Sections</a>";
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
        width:75%;
        margin: 70px auto 100px;
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
HTML;

$params = array($_GET['g_id']);
$db->query("SELECT * FROM gradeable WHERE g_gradeable_type='1' AND g_id=?", $params);
$c_gradeable = $db->row();
print <<<HTML
<div id="container-g-checkpoints">
    <div class="modal-header">
        <h3 id="myModalLabel" style="width:70%; display:inline-block;">{$c_gradeable['g_title']}</h3>
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
HTML;

$params = array($c_gradeable['g_id']);
$db->query("SELECT gc_title from gradeable_component WHERE g_id=?", $params);
$c_gradeable_checkpoints = array();
foreach($db->rows() as $row){
    array_push($c_gradeable_checkpoints, $row['gc_title']);
}

$ta_instructions = (trim($c_gradeable['g_overall_ta_instructions']) == '') ? '' : '<b>Grading Instructions</b>: ' . $c_gradeable['g_overall_ta_instructions'];

print <<<HTML
            {$ta_instructions} <br /> <br />
            <table class="table table-bordered striped-table" id="g-checkpoints-table" style=" border: 1px solid #AAA;">
                <thead style="background: #E1E1E1;">
                    <tr>
                        <th></th>
                        <th>User ID</th>
                        <th>Name</th>
HTML;
foreach($c_gradeable_checkpoints as $checkpoint) {
    print <<<HTML
                            <th>{$checkpoint}</th>
HTML;
}
    print <<<HTML
                            </tr>
                        </thead>
HTML;

$grade_by_reg_section = $c_gradeable['g_grade_by_registration'];
$section_param = ($grade_by_reg_section ? 'sections_registration_id': 'sections_rotating_id');
$user_section_param = ($grade_by_reg_section ? 'registration_section': 'rotating_section');

$g_id = $c_gradeable['g_id'];

$params = array($user_id);
if((isset($_GET["all"]) && $_GET["all"] == "true") || $user_is_administrator == true){
    $params = array();
    $query = ($grade_by_reg_section ? "SELECT * FROM sections_registration ORDER BY sections_registration_id ASC"
                                    : "SELECT * FROM sections_rotating ORDER BY sections_rotating_id ASC");
    $db->query($query, $params);
}
else{
    if ($grade_by_reg_section) {
        $params = array($user_id);
    	$query = "SELECT * FROM grading_registration WHERE user_id=? ORDER BY sections_registration_id ASC";
        $db->query($query, $params);
    } else {
        $params = array($user_id,$g_id);
        $query = "SELECT * FROM grading_rotating WHERE user_id=? AND g_id=? ORDER BY sections_rotating_id ASC";
        $db->query($query, $params);
    }
}

foreach($db->rows() as $section) {
    $params = array($section[$section_param]);
    $db->query("SELECT COUNT(*) AS cnt FROM users WHERE ".$user_section_param."=?",$params);
    if($db->row()['cnt']==0) continue;
    
    $section_id = intval($section[$section_param]);
    $section_type = ($grade_by_reg_section ? "Registration": "Rotating");
    $enrolled_assignment = ($grade_by_reg_section ? "enrolled in": "assigned to");
    $count = count($c_gradeable_checkpoints) + 3;
    print <<<HTML
                    <tr class="info">
                        <td colspan="{$count}" style="text-align:center;" id="section-{$section_id}">
                                Students {$enrolled_assignment} {$section_type} Section {$section_id}
                                <a target=_blank href="{$BASE_URL}/account/print/print_checkpoints_gradeable.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id={$c_gradeable['g_id']}&section_id={$section_id}&grade_by_reg_section={$grade_by_reg_section}">
                                    <div class="icon-print"></div>
                                </a>
                        </td>
                    </tr>
                    <tbody>
HTML;
    $params = array($c_gradeable["g_id"],$section_id, 4);
    $db->query("
        
SELECT
    s.user_id
    , s.user_firstname
    , s.user_preferred_firstname
    , s.user_lastname
    , case when gcds.grade_value_array is null then '{}' else gcds.grade_value_array end
    , case when gcds.grade_checkpoint_array is null then '{}' else gcds.grade_checkpoint_array end
    , g_id
FROM
    users AS s
    LEFT JOIN (
        SELECT
            array_agg(gcd_score) as grade_value_array
            , array_agg(gc_order) as grade_checkpoint_array
            , gd_user_id
            , g_id
        FROM
            gradeable_component_data AS gcd INNER JOIN (
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
    ) AS gcds ON gcds.gd_user_id = s.user_id
WHERE s.".$user_section_param.
    "=?
    AND s.user_group=?
ORDER BY
    s.user_id", $params);

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
        $firstname = getDisplayName($student_info);
        print <<<HTML
                        <tr>
                            <td>{$section_id}</td>
                            <td style="white-space: nowrap;" class="cell-all" id="cell-{$c_gradeable["g_id"]}-all-{$row["user_id"]}" cell-status="0">
                            {$student_info["user_id"]}
                            </td>
                            <td style="white-space: nowrap;">{$firstname} {$student_info["user_lastname"]}</td>
HTML;
        $count = 1;

        foreach($c_gradeable_checkpoints as $checkpoint) {
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
            elseif($mode == 0.5) {
                $background_color = "#88d0f4";
                $background_color = "background-color:#88d0f4";
            }

            print <<<HTML
                            <td id="cell-{$c_gradeable["g_id"]}-check{$count}-{$row["user_id"]}" cell-status="{$mode}" style="{$background_color}"></td>
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
HTML;

print <<<HTML
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $("td[id^=cell-]").click(function() {
        var cell_status = (parseFloat($(this).attr('cell-status')) == 0) ? 1: parseFloat($(this).attr('cell-status')) - 0.5;
        var name = $(this).attr("id");
        name = name.split("-");
        var gradeable = "";
        for (var i = 1; i < name.length-2; i++) {
            if (i > 1) {
                gradeable += "-";
            }
            gradeable += name[i];
        }
        var check = name[name.length-2].replace("check", "");
        var user_id = name[name.length-1];
        
        var url = "{$BASE_URL}/account/ajax/account-checkpoints-gradeable.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id=" + gradeable + "&check=" + check + "&user_id=" + user_id + "&mode=" + cell_status;

        if($(this).hasClass("cell-all")) {
            // Named cell
            $(this).attr('cell-status', cell_status);
            updateColor("td[id^=cell-" + gradeable + "-check][id$=-" + user_id + "]", cell_status, url);
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
        else if(mode == 0.5) {
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

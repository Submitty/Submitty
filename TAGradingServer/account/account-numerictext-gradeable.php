<?php
//TODO add labels to numeric text fields
// Pull out tabs to separate URL
use app\models\User;

include "../header.php";

$account_subpages_unlock = true;

$button = "";
if (!User::$is_administrator) {
    if (isset($_GET['all']) && $_GET['all'] == "true") {
        $button = "<a class='btn' href='{$BASE_URL}/account/account-checkpoints-gradeable.php?course={$_GET['course']}&semester={$_GET['semester']}'>View Your Sections</a>";
    }
    else {
        $button = "<a class='btn' href='{$BASE_URL}/account/account-checkpoints-gradeable.php?course={$_GET['course']}&semester={$_GET['semester']}&all=true'>View All Sections</a>";
    }
}

print <<<HTML

<style type="text/css">
    body {
        overflow: scroll;
    }

    #container-nt{
        min-width:700px;
        width: 80%;
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

    .input-container{
        padding: 0px !important;
        width: 10px !important;
    }

    input[type="text"]{
        width: 90%;
        padding: 5px;
        background-color: transparent;
        -webkit-box-shadow: none;
        -moz-box-shadow: none;
        box-shadow: none;
        border: 0px solid #ccc;
        margin-bottom: 0px;
        -webkit-border-radius: 0px;
        -moz-border-radius: 0px;
        border-radius: 0px;
        height: 100%;
    }

    input:focus{
        outline: none !important;
    }
    
    ::-webkit-scrollbar {
        width:  2px !important;
        height: 6px;
        background-color:transparent;
    }

</style>

<div id="container-nt">
    <div class="modal-header">
        <h3 id="myModalLabel" style="width:20%; display:inline-block;">(╯°□°）╯︵ ┻━┻</h3>
        <span style="width: 79%; display: inline-block;">{$button}</span>
    </div>

    <div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
        <div class="bs-docs-example">
            <ul id="myTab" class="nav nav-tabs">
HTML;

// MAYBE REORDER THIS
$params = array();
$db->query("SELECT * FROM gradeable WHERE g_gradeable_type=2 ORDER BY g_id ASC", $params);

$first = true;
$nt_gradeables = $db->rows();
foreach($nt_gradeables as $nt_row){
    if($first) {
        print <<<HTML
                <li class="active"><a href="#nt_gradeable{$nt_row["g_id"]}" data-toggle="tab">{$nt_row['g_title']}</a></li>
HTML;
    }
    else {
        print <<<HTML
                <li><a href="#nt_gradeable{$nt_row["g_id"]}" data-toggle="tab">{$nt_row['g_title']}</a></li>
HTML;
    }
    $first = false;
}

print <<<HTML
            </ul>
            <div id="myTabContent" class="tab-content">
HTML;
$first = true;
foreach($nt_gradeables as $nt_row){
    //$disabled = ($nt_row['nt_gradeable_locked'] && !$user_is_administrator) ? "disabled" : "";
    $extra = ($first) ? ' active in' : '';
    
    $g_id = $nt_row['g_id'];
    // get the number of numeric nt_gradeables questions
    $params = array($g_id);
    $db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='false'", $params);
    $num_numeric = $db->row()['cnt'];
    // get the number of text questions
    $db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='true'", $params);
    $num_text = $db->row()['cnt'];

    $colspan = $num_numeric;
    $colspan2 = $num_text;
    print <<<HTML
                <div class="tab-pane fade{$extra}" id="nt_gradeable{$nt_row["g_id"]}">
                    <table class="table table-bordered" id="nt_gradeablesTable" style=" border: 1px solid #AAA;">
                        <thead style="background: #E1E1E1;">
                            <tr>
                                <th>User ID</th>
HTML;
    if ($colspan2 === 0){
        print <<<HTML
                                <th width="80%"colspan="{$colspan}">Grades</th>
HTML;
    }
    else{
        print <<<HTML
                                <th width="30%"colspan="{$colspan}">Grades</th>
HTML;
    }
    print <<<HTML
                                <th width="5%">Total</th>
HTML;
    if ($colspan2 > 0) {
        print <<<HTML
                                <th width="50%" colspan="{$colspan2}">Text</th>
HTML;
    }
    print <<<HTML
                            </tr>
                        </thead>
                        <tbody style="background: #f9f9f9;">
HTML;

    $grade_by_reg_section = $nt_row['g_grade_by_registration'];
    $section_param = ($grade_by_reg_section ? 'sections_registration_id': 'sections_rotating_id');
    $user_section_param = ($grade_by_reg_section ? 'registration_section': 'rotating_section');
    $params = array($user_id);
    if((isset($_GET["all"]) && $_GET["all"] == "true") || $user_is_administrator == true){
        $params = array();
        $query = ($grade_by_reg_section ? "SELECT * FROM sections_registration ORDER BY sections_registration_id ASC"
                                        : "SELECT * FROM sections_rotating ORDER BY sections_rotating_id ASC");
        $db->query($query, $params);
    }
    else{
        $params = array($user_id);
        $query = ($grade_by_reg_section ? "SELECT * FROM grading_registration WHERE user_id=? ORDER BY sections_registration_id ASC"
                                        : "SELECT * FROM grading_rotating WHERE user_id=? ORDER BY sections_rotating ASC");
        $db->query($query, $params);
    }

    $colspan += 2;
    $colspan += $colspan2;

    foreach($db->rows() as $section){
        $params = array($section[$section_param]);
        $db->query("SELECT COUNT(*) AS cnt FROM users WHERE ".$user_section_param."=?",$params);
        if($db->row()['cnt']==0) continue;
        
        $section_id = intval($section[$section_param]);
        $section_type = ($grade_by_reg_section ? "Registration": "Rotating");
		print <<<HTML
                            <tr class="info">
                                <td colspan="{$colspan}" style="text-align:center;">
                                    Students Enrolled in {$section_type} Section {$section_id}
                                </td>
                            </tr>
HTML;
        $params = array($nt_row['g_id'],intval($section_id),4);
        $db->query("
        
SELECT
    s.user_id
    , s.user_firstname
    , s.user_lastname
    , case when gcds.grade_value_array is null then '{}' else gcds.grade_value_array end
    , case when gcds.grade_text is null then '{}' else gcds.grade_text end
    , g_id
FROM
    users AS s
    LEFT JOIN (
        SELECT
            array_agg(gcd_score ORDER BY gc_order ASC) as grade_value_array
            , array_agg(gcd_component_comment ORDER BY gc_order ASC) as grade_text
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
                        WHERE g.g_gradeable_type=2
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
    
        $students_grades = $db->rows();

        $db->query("SELECT gc_title FROM gradeable_component WHERE g_id=? ORDER BY gc_order ASC", array($g_id));
        $titles = $db->rows();
        print <<<HTML
                    <tr style="background: #E1E1E1;">
                    <td></td>
HTML;
        for($i=0; $i<$num_numeric; ++$i){
            $title = $titles[$i];
            print <<<HTML
                    <td>{$title['gc_title']}</td>
HTML;
        }
        print <<<HTML
                    <td></td>
HTML;
        for($i=$num_numeric; $i<$num_numeric+$num_text; ++$i){
            $title = $titles[$i];
            print <<<HTML
                    <td>{$title['gc_title']}</td>
HTML;
        }

        print <<<HTML
                    </tr>
HTML;
        
        foreach($students_grades as $row){
            $student_info = $row;
            $temp = $row;
            
            print <<<HTML
                        </tr>
HTML;
            
            print <<<HTML
            
                            
                            <tr>
                                <td>
                                    {$student_info["user_id"]} ({$student_info["user_lastname"]}, {$student_info["user_firstname"]})
                                </td>
HTML;
            $question_grades=pgArrayToPhp($temp['grade_value_array']);
            //return an empty array of zeros here
            if (empty($question_grades)) {
                $question_grades = array();
                for ($i = 0; $i < $num_numeric; ++$i) {
                    $question_grades[$i] = 0;
                }
            }
            // calculate the overall grade for this nt_gradeable
            $total_grade = array_sum($question_grades);
            $text_fields = pgArrayToPhp($temp['grade_text']);
           
            if (empty($text_fields)) {
                $text_fields = array();
                for ($i = 0; $i < $num_text; ++$i) {
                    $text_fields[$i] = "";
                }
            }
            
            for($i = 0; $i < $num_numeric; ++$i) {
                print <<<HTML
                                <td class="input-container" style="border: 1px solid black">
                                    <input id="cell-{$nt_row["g_id"]}-{$row["user_id"]}-q{$i}" type="text" value="{$question_grades[$i]}" />
                                </td>
HTML;
            }
            print <<<HTML
                                <td style="width: 10px" id="cell-{$nt_row["g_id"]}-{$row['user_id']}-score">{$total_grade}</td>
HTML;
            
            //print the text fields
            for ($i = $num_numeric; $i <$num_numeric+$num_text; ++$i) {
                $text_field = isset($text_fields[$i]) ? $text_fields[$i] : "";
                print <<<HTML
                                <td class="input-container" style="border: 1px solid black">
                                    <input id="cell-{$nt_row["g_id"]}-{$row["user_id"]}-t{$i}" elem="text" type="text" value="{$text_field}" />
                                </td>
HTML;
            }
            print <<<HTML
                            </tr>
HTML;
        }
    }
	print <<<HTML
                        </tbody>
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
HTML;

$js_array_questions = "";
$js_array_text = "";
foreach ($nt_gradeables as $nt_gradeable) {
    $params = array($nt_gradeable['g_id']);
    $db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='false'", $params);
    $num_numeric = $db->row()['cnt'];
    $db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='true'", $params);
    $num_text = $db->row()['cnt'];
    $js_array_questions .= $nt_gradeable['g_id'].':"'.$num_numeric.'",';
    $js_array_text .= $nt_gradeable['g_id'].':"'.$num_text.'",';
}

echo <<<HTML
	<script type="text/javascript">
        var questions = {{$js_array_questions}};
        var text_fields = {{$js_array_text}};
        var url = "";

        $("input[id^=cell-]").change(function() {

            var grade = $(this).val();
            var name = $(this).attr("id");
            name = name.split("-");
            var nt_gradeable = name[1];
            var user_id = name[2];

            if ($(this).attr('elem') == 'text') {

            }
            else {
                if(isNaN(grade) && grade != "-") {
                    $(this).val("0");
                }
                else {
                    if(grade == "" || grade == "-") {
                        grade = "0";
                        $(this).val(grade);
                    }
                    else {
                        $(this).val(sciNotationToDecimal(grade));
                    }
                }
            }
            var total = 0;
            var extra = "";
            for (var i = 0; i < questions[nt_gradeable]; i++) {
                var score = parseFloat($("#cell-"+nt_gradeable+"-"+user_id+"-q"+i).val());
                if (isNaN(score)) {
                    score = 0;
                }
                extra += "&q"+i+"="+score;
                total += score;
            }

            for (var j = questions[nt_gradeable]; j <questions[nt_gradeable]+text_fields[nt_gradeable]; ++j){
                var text = $("#cell-"+nt_gradeable+"-"+user_id+"-t"+j).val();
                extra += "&t"+j+"="+text;
            }

            $("#cell-"+nt_gradeable+"-"+user_id+"-score").text(total);
            url = "{$BASE_URL}/account/ajax/account-numerictext-gradeable.php?course={$_GET['course']}&semester={$_GET['semester']}&id=" + nt_gradeable + "&user_id=" + user_id + "&grade=" + total + extra; 
            updateColor(this, url);
        });

        // TODO FIX this
        function sciNotationToDecimal(sciStr){
            rep = sciStr.toString().split('e');
            if (rep.length < 2) return sciStr;
            mantissaLen = rep[0].length-2;
            return rep[0] + rep[0].substring(2) + Array(parseInt(rep[1].substr(1))-mantissaLen).join('0');
        }

        function updateColor(item, url) {
            $(item).css("border-right", "15px solid #149bdf");
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
                updateFail(item);
                window.alert("[SAVE ERROR] Refresh Page");
            });
        }
	</script>
HTML;

include "../footer.php";
?>
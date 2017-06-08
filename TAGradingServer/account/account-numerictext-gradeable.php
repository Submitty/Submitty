<?php
// Pull out tabs to separate URL
use \models\User;

include "../header.php";

$account_subpages_unlock = true;

$button = "";
if (!User::$is_administrator) {
    if (isset($_GET['all']) && $_GET['all'] == "true") {
        $button = "<a class='btn' href='{$BASE_URL}/account/account-numerictext-gradeable.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id={$_GET['g_id']}'>View Your Sections</a>";
    }
    else {

      //
      // FIXME  : THIS SHOULD ONLY BE AVAILABLE TO FULL ACCESS GRADERS
      //

        $button = "<a class='btn' href='{$BASE_URL}/account/account-numerictext-gradeable.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id={$_GET['g_id']}&all=true'>View All Sections</a>";
    }
}

if(User::$user_group == 1){
    $csv_button = "<label>Upload CSV (WARNING! Previously entered data may be overwritten!): Do not include a header row. Format CSV using one column for student id and one column for each field. Columns and field types must match.</label></br><input type=\"file\" id=\"csvUpload\" accept=\".csv, .txt\" onchange=\"csvUpload()\">";
    $csv_upload_functions = "
        function csvUpload(){
            var f = $('#csvUpload').get(0).files[0];
            
            if(f){
                var reader = new FileReader();
                reader.readAsText(f);
                reader.onload = function(evt) {
                  parseCsv(reader.result);
                  
                }
                reader.onerror = function(evt){
                    console.error(\"nope\");
                }
            } 
        }
        
        function parseCsv(csv){
            url = \"{$BASE_URL}/account/ajax/account-numerictext-gradeable.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id={$_GET['g_id']}\";
            var lines = csv.split(/\\r\\n|\\n/);
            console.log(lines);
            console.log(url);
            $.ajax({
                type:\"POST\",
                url:url,
                data: {
                    csrf_token: '{$_SESSION['csrf']}',
                    parsedCsv: lines,
                    action:\"csv\"
                },
                success: function(data, text){
                    location.reload();
                },
                error: function(request, status, error){
                    window.alert(\"An error has occurred. Contact an administrator.\");
                }
            });
        }";
}
else{
    $csv_button = "";
    $csv_upload_functions = "";
}

print <<<HTML

<style type="text/css">
    body {
        overflow: scroll;
    }

    #container-nt{
        min-width:700px;
        width: 80%;
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
HTML;

$params = array($_GET['g_id']);
$db->query("SELECT * FROM gradeable WHERE g_gradeable_type=2 AND g_id=?", $params);

$nt_gradeable = $db->row();

print <<<HTML
<div id="container-nt">
    <div class="modal-header">
        <h3 id="myModalLabel" style="width:70%; display:inline-block;">{$nt_gradeable['g_title']}</h3>
HTML;
if(User::$user_group == 1) {
    print <<<HTML
    <input type="file" id="csvUpload" accept=".csv, .txt" onchange="csvUpload()">
    <label for="csvUpload">Upload CSV</label>
HTML;
}
print <<<HTML

        <span style="width: 79%; display: inline-block;">{$button}</span>
    </div>

    <div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
        <div class="bs-docs-example">
HTML;


$g_id = $nt_gradeable['g_id'];
// get the number of numeric nt_gradeables questions
$params = array($g_id);
$db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='false'", $params);
$num_numeric = $db->row()['cnt'];
// get the number of text questions
$db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='true'", $params);
$num_text = $db->row()['cnt'];

$params = array($g_id);
$db->query("
SELECT 
    array_agg(gc_max_value ORDER BY gc_order ASC) as max_scores
    FROM gradeable_component
    WHERE g_id=?
", $params);

$max_scores = pgArrayToPhp($db->row()['max_scores']);

$colspan = $num_numeric;
$colspan2 = $num_text;
$ta_instructions = (trim($nt_gradeable['g_overall_ta_instructions']) == '') ? '' : '<b>Grading Instructions</b>: ' . $nt_gradeable['g_overall_ta_instructions'];
print <<<HTML
                {$ta_instructions} <br /> <br />
                <table class="table table-bordered" id="nt_gradeablesTable" style=" border: 1px solid #AAA;">
                    <thead style="background: #E1E1E1;">
                        <tr>
                            <th></th>
                            <th>User ID</th>
                            <th>Name</th>
HTML;
if ($colspan2 === 0){ 
    print <<<HTML
                            <th width="80%"colspan="{$colspan}">Grades</th> 
HTML;
}
else if($colspan !== 0){
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

$grade_by_reg_section = $nt_gradeable['g_grade_by_registration'];
$section_param = ($grade_by_reg_section ? 'sections_registration_id': 'sections_rotating_id');
$user_section_param = ($grade_by_reg_section ? 'registration_section': 'rotating_section');


$params = array($user_id);
if((isset($_GET["all"]) && $_GET["all"] == "true") || $user_is_administrator == true) {
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

$colspan += 4;
$colspan += $colspan2;
$student_cnt = 0;

$sections = $db->rows();
if ((isset($_GET["all"]) && $_GET["all"] == "true") || $user_is_administrator == true) {
    $sections[] = array($section_param => null);
}

foreach ($sections as $section) {
    $params = array($section[$section_param]);
    if ($section[$section_param] === null) {
        $db->query("SELECT COUNT(*) AS cnt FROM users WHERE ".$user_section_param." IS NULL");
    }
    else {
        $db->query("SELECT COUNT(*) AS cnt FROM users WHERE ".$user_section_param." = ?",$params);
    }

    if ($db->row()['cnt'] == 0) {
        continue;
    }
    
    $section_id = ($section[$section_param] !== null) ? intval($section[$section_param]) : "null";
    $section_type = ($grade_by_reg_section ? "Registration": "Rotating");
    $enrolled_assignment = ($grade_by_reg_section ? "enrolled in": "assigned to");
    print <<<HTML
                        <tr class="info">
                            <td colspan="{$colspan}" style="text-align:center;">
                                Students {$enrolled_assignment} {$section_type} Section {$section_id}
                            </td>
                        </tr>
HTML;

    if ($section[$section_param] === null) {
        $user_query = "s.{$user_section_param} IS NULL";
        $params = array($nt_gradeable['g_id']);
    }
    else {
        $user_query = "s.{$user_section_param} = ?";
        $params = array($nt_gradeable['g_id'], $section[$section_param]);
    }


    $db->query("
        
SELECT
    s.user_id
    , s.user_firstname
    , s.user_preferred_firstname
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
WHERE {$user_query}
ORDER BY
    s.user_id", $params);
    
    $students_grades = $db->rows();

    $db->query("SELECT gc_title FROM gradeable_component WHERE g_id=? ORDER BY gc_order ASC", array($g_id));
    $titles = $db->rows();
    print <<<HTML
                        <tr style="background: #E1E1E1;">
                            <td colspan='3'></td>
HTML;
    for($i=0; $i<$num_numeric; ++$i){
        $title = $titles[$i];
        print <<<HTML
                            <td>{$title['gc_title']} ({$max_scores[$i]})</td>
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
        $firstname = getDisplayName($student_info);
        
        print <<<HTML
                        <tr id="student-row-{$student_cnt}">
                            <td>{$section_id}</td>
                            <td style="white-space: nowrap;">{$student_info["user_id"]}</td>
                            <td style="white-space: nowrap;">{$firstname} {$student_info["user_lastname"]}</td>
HTML;
        $student_cnt++;
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
                                <input id="cell-{$nt_gradeable["g_id"]}-{$row["user_id"]}-q{$i}" type="text" value="{$question_grades[$i]}" />
                            </td>
HTML;
        }
        print <<<HTML
                            <td style="width: 10px" id="cell-{$nt_gradeable["g_id"]}-{$row['user_id']}-score">{$total_grade}</td>
HTML;
        
        for ($i = $num_numeric; $i < $num_numeric+$num_text; ++$i) {
            $text_field = isset($text_fields[$i]) ? $text_fields[$i] : "";
            print <<<HTML
                            <td class="input-container" style="border: 1px solid black">
                                <input id="cell-{$nt_gradeable["g_id"]}-{$row["user_id"]}-t{$i}" elem="text" type="text" value="{$text_field}" />
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
HTML;

print <<<HTML
            </div>
        </div>
    </div>
</div>
HTML;

$params = array($nt_gradeable['g_id']);
$db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='false'", $params);
$num_numeric = $db->row()['cnt'];
$db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='true'", $params);
$num_text = $db->row()['cnt'];

echo <<<HTML
	<script type="text/javascript">
        var questions = {$num_numeric};
        var text_fields = {$num_text};
        var url = "";

        $("input[id^=cell-]").change(function() {
            var grade = $(this).val();
            var name = $(this).attr("id");
            name = name.split("-");
            var nt_gradeable = "";
            for (var i = 1; i < name.length-2; i++) {
                if (i > 1) {
                    nt_gradeable += "-";
                }
                nt_gradeable += name[i];
            }
            var user_id = name[name.length-2];

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
            for (var i = 0; i < questions; i++) {
                var score = parseFloat($("#cell-"+nt_gradeable+"-"+user_id+"-q"+i).val());
                if (isNaN(score)) {
                    score = 0;
                }
                extra += "&q"+i+"="+score;
                total += score;
            }

            for (var j = questions; j < questions+text_fields; ++j){
                var text = $("#cell-"+nt_gradeable+"-"+user_id+"-t"+j).val();
                text = encodeURIComponent(text); //this makes it so & don't get deleted when entered by user
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

    if(User::$user_group == 1){
        ECHO <<< HTML
        <script>
        function csvUpload(){
                        
            var confirmation = window.confirm("WARNING! \\nPreviously entered data may be overwritten! " +
             "This action is irreversible! Are you sure you want to continue?\\n\\n Do not include a header row in your CSV. Format CSV using one column for " +
              "student id and one column for each field. Columns and field types must match.");
              if(confirmation){
                    var f = $('#csvUpload').get(0).files[0];
                    
                    if(f){
                        var reader = new FileReader();
                        reader.readAsText(f);
                        reader.onload = function(evt) {
                          parseCsv(reader.result);                          
                        }
                        reader.onerror = function(evt){
                            console.error(evt);
                        }
                    }
              } else{
                  var f = $('#csvUpload');
                  f.replaceWith(f = f.clone(true));
              }
        }
        
        function parseCsv(csv){
            url = "{$BASE_URL}/account/ajax/account-numerictext-gradeable.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id={$_GET['g_id']}";
            var lines = csv.trim().split(/\\r\\n|\\n/);
            $.ajax({
                type:"POST",
                url:url,
                data: {
                    csrf_token: '{$_SESSION['csrf']}',
                    parsedCsv: lines,
                    action:"csv"
                },
                success: function(data, text){
                    location.reload();
                },
                error: function(request, status, error){
                    window.alert("An error has occurred. Contact an administrator.");
                }
            });
        }
        </script>
HTML;
    }
include "../footer.php";
?>
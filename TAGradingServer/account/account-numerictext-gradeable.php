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

    #container-tests
    {
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

    .input-container
    {
        padding: 0px !important;
        width: 10px !important;
    }

    input[type="text"]
    {
        width: 80%;
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
        height: 30px;
    }

    input:focus
    {
        outline: none !important;
    }
    
    ::-webkit-scrollbar {
        width:  2px !important;
        height: 6px;
        background-color:transparent;
    }

</style>

<div id="container-tests">
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
$tests = $db->rows();
foreach($tests as $test_row){
    //$locked = ($test_row['test_locked']) ? "(Locked)" : "";
    if($first) {
        print <<<HTML
                <li class="active"><a href="#test{$test_row["g_id"]}" data-toggle="tab">{$test_row['g_title']} {$locked}</a></li>
HTML;
    }
    else {
        print <<<HTML
                <li><a href="#test{$test_row["g_id"]}" data-toggle="tab">{$test_row['g_title']}{$locked}</a></li>
HTML;
    }

    $first = false;
}

print <<<HTML
            </ul>
            <div id="myTabContent" class="tab-content">
HTML;
$first = true;
foreach($tests as $test_row){
    //$disabled = ($test_row['test_locked'] && !$user_is_administrator) ? "disabled" : "";
    $extra = ($first) ? ' active in' : '';
    
    // get the number of numeric tests questions
    $params = array($g_id);
    $db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='false'", $params);
    $num_numeric = $db->row()['cnt'];

    // get the number of text questions
    $db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='true'", $params);
    $num_text = $db->row()['cnt'];
    
    $colspan = $num_numeric;
    $colspan2 = $num_text;
    print <<<HTML
                <div class="tab-pane fade{$extra}" id="test{$test_row["g_id"]}">
                    <table class="table table-bordered" id="testsTable" style=" border: 1px solid #AAA;">
                        <thead style="background: #E1E1E1;">
                            <tr>
                                <th width="30%">RCS ID</th>
                                <th colspan="{$colspan}">Grades</th>
                                <th width="5%">Total</th>
HTML;
    if ($colspan2 > 0) {
        print <<<HTML
                                <th width="30%" colspan="{$colspan2}">Text</th>
HTML;
    }
    print <<<HTML
                            </tr>
                        </thead>

                        <tbody style="background: #f9f9f9;">
HTML;

    $params = array($user_id);
    if((isset($_GET["all"]) && $_GET["all"] == "true") || $user_is_administrator == true){
        $params = array();
        $db->query("SELECT * FROM sections ORDER BY section_id ASC", $params);
    }
    else{
        $params = array($user_id);
        $db->query("SELECT * FROM relationships_users WHERE user_id=? ORDER BY section_id ASC", $params);
    }

    $colspan += 2;
    $colspan += $colspan2;

    foreach($db->rows() as $section){
        $section_id = intval($section['section_id']);
		print <<<HTML
                            <tr class="info">
                                <td colspan="{$colspan}" style="text-align:center;">
                                    Students Enrolled in Section {$section_id}
                                </td>
                            </tr>
HTML;
        $params = array($test_row['g_id'],intval($section["section_id"])); 
        $db->query("
        
SELECT
    s.student_rcs
    , s.student_id
    , s.student_first_name
    , s.student_last_name
    , case when gcds.grade_value_array is null then '{}' else gcds.grade_value_array end
    , case when gcds.grade_checkpoint_array is null then '{}' else gcds.grade_checkpoint_array end
    , g_id
    , gc_max_value
FROM
    students AS s
    LEFT JOIN (
        SELECT
            array_agg(gcd_score) as grade_value_array
            , array_agg(gc_order) as grade_checkpoint_array
            , gd_user_id
            , g_id
            , gc_max_value
        FROM
            gradeable_component_data AS gcd INNER JOIN (
                SELECT 
                    gd.g_id
                    ,gd_id
                    ,gc_id
                    ,gc_order
                    ,gd_user_id
                    ,gc_max_value
                FROM 
                    gradeable_data AS gd INNER JOIN (
                        SELECT
                            g.g_id
                            , gc_id
                            , gc_order
                            , gc_max_value
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
            , gc_max_value
    ) AS gcds ON gcds.gd_user_id = s.student_rcs
WHERE
    s.student_section_id=?
ORDER BY
    s.student_rcs", $params);
        
        /*$db->query("SELECT s.*,gt.grade_test_value,gt.grade_test_questions,gt.grade_test_text FROM students AS s LEFT JOIN (SELECT * FROM grades_tests WHERE test_id=?) 
                    AS gt ON s.student_rcs=gt.student_rcs WHERE s.student_section_id=? ORDER BY student_rcs ASC", $params);*/
        foreach($db->rows() as $row){
            $student_info = $row;
            $temp = $row;
            print <<<HTML
                            <tr>
                                <td style="width:30%;">
                                    {$student_info["student_rcs"]} ({$student_info["student_last_name"]}, {$student_info["student_first_name"]})
                                </td>
HTML;
            if (isset($temp['grade_value_array'])) {
                $question_grades = pgArrayToPhp($temp['grade_value_array']);
            }
            else {
                $question_grades = array();
                for ($i = 0; $i < $num_numeric; $i++) {
                    $question_grades[$i] = 0;
                }
            }
            
            // calculate the overall grade for this test
            $test_grade = array_sum($question_grades);
           
           //get the text fields
           /*
            if (isset($temp['grade_test_text'])) {
                $text_fields = pgArrayToPhp($temp['grade_test_text']);
            }
            else {
                $text_fields = array();
                for ($i = 0; $i < $test_row['test_text_fields']; $i++) {
                    $text_fields[] = "";
                }
            }
            for($i = 0; $i < $test_row['test_questions']; $i++) {
                print <<<HTML
                                <td class="input-container" style="border: 1px solid black">
                                    <input id="cell-{$test_row["test_id"]}-{$row["student_rcs"]}-q{$i}" type="text" value="{$question_grades[$i]}" {$disabled} />
                                </td>
HTML;
            }*/
            print <<<HTML
                                <td style="width: 10px" id="cell-{$test_row["g_id"]}-{$row['student_rcs']}-score">{$test_grade}</td>
HTML;
            // print the text fields
            /*for ($i = 0; $i < $test_row['test_text_fields']; $i++) {
                print <<<HTML
                                <td class="input-container" style="border: 1px solid black">
                                    <input id="cell-{$test_row["test_id"]}-{$row["student_rcs"]}-t{$i}" elem="text" type="text" value="{$text_fields[$i]}" {$disabled} />
                                </td>
HTML;
            }*/
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

$js_array_grades = "";
$js_array_questions = "";
$js_array_text = "";
foreach ($tests as $test) {
    $params = array($g_id);
    $db->query("SELECT SUM(gc_max_value) AS max_grade FROM gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? GROUP BY gc_id", $params);
    $max_grade = $db->row()['max_grade'];
    $db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='false'", $params);
    $num_numeric = $db->row()['cnt'];
    $db->query("SELECT COUNT(*) AS cnt from gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id WHERE g.g_id=? AND gc_is_text='true'", $params);
    $num_text = $db->row()['cnt'];
    
    
    $js_array_grades .= $test['g_id'].':"'.$max_grade.'",';
    $js_array_questions .= $test['g_id'].':"'.$num_numeric.'",';
    $js_array_text .= $test['g_id'].':"'.$num_text.'",';
}

echo <<<HTML
	<script type="text/javascript">
        var grades = {{$js_array_grades}};
        var questions = {{$js_array_questions}};
        var text_fields = {{$js_array_text}};
        var url = "";

		$("input[id^=cell-]").change(function() {

			var grade = $(this).val();
			var name = $(this).attr("id");
			name = name.split("-");
			var test = name[1];
			var rcs = name[2];

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
                        $(this).val(grade);
                    }
                }
            }
			var total = 0;
			var extra = "";
			for (var i = 0; i < questions[test]; i++) {
			    var score = parseFloat($("#cell-"+test+"-"+rcs+"-q"+i).val());
			    if (isNaN(score)) {
			        score = 0;
			    }
			    extra += "&q"+i+"="+score;
                total += score;
			}
			for (var j = 0; j < text_fields[test]; j++) {
			    var text = $("#cell-"+test+"-"+rcs+"-t"+j).val();
			    extra += "&t"+j+"="+text;
			}
			$("#cell-"+test+"-"+rcs+"-score").text(total);
			url = "{$BASE_URL}/account/ajax/account-numerictext-gradeable.php?course={$_GET['course']}&id=" + test + "&rcs=" + rcs + "&grade=" + total + extra; 
            updateColor(this, url);
		});

		function updateColor(item, url) {
			$(item).css("border-right", "15px solid #149bdf");
			// alert(url);
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


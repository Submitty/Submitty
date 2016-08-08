<?php
use app\models\User;

include "../header.php";

$account_subpages_unlock = true;

if (!User::$is_administrator) {
    if (isset($_GET['all']) && $_GET['all'] == "true") {
        $button = "<a class='btn' href='{$BASE_URL}/account/account-tests.php?course={$_GET['course']}&semester={$_GET['semester']}'>View Your Sections</a>";
    }
    else {
        $button = "<a class='btn' href='{$BASE_URL}/account/account-tests.php?course={$_GET['course']}&semester={$_GET['semester']}&all=true'>View All Sections</a>";
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

    .input-container
    {
        padding: 0px !important;
    }

    input[type="text"]
    {
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
        height: 30px;
    }

    input:focus
    {
        outline: none !important;
    }

</style>

<div id="container-tests">
    <div class="modal-header">
        <h3 id="myModalLabel" style="width:20%; display:inline-block;">Tests</h3>
        <span style="width: 79%; display: inline-block;">{$button}</span>
    </div>

    <div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
        <div class="bs-docs-example">
            <ul id="myTab" class="nav nav-tabs">
HTML;

$params = array();
$db->query("SELECT * FROM tests ORDER BY test_type DESC, test_id ASC", $params);

$first = true;
$tests = $db->rows();
foreach($tests as $test_row)
{
    $locked = ($test_row['test_locked']) ? "(Locked)" : "";
    if($first) {
        print <<<HTML
                <li class="active"><a href="#test{$test_row["test_id"]}" data-toggle="tab">{$test_row['test_type']} {$test_row["test_number"]} {$locked}</a></li>
HTML;
    }
    else {
        print <<<HTML
                <li><a href="#test{$test_row["test_id"]}" data-toggle="tab">{$test_row['test_type']} {$test_row["test_number"]} {$locked}</a></li>
HTML;
    }

    $first = false;
}

print <<<HTML


            </ul>

            <div id="myTabContent" class="tab-content">
HTML;
$first = true;
foreach($tests as $test_row)
{
    $disabled = ($test_row['test_locked'] && !$user_is_administrator) ? "disabled" : "";
    $extra = ($first) ? ' active in' : '';
    $colspan = $test_row['test_questions'];
    $colspan2 = $test_row['test_text_fields'];
    print <<<HTML
                <div class="tab-pane fade{$extra}" id="test{$test_row["test_id"]}">
                    <table class="table table-bordered" id="testsTable" style=" border: 1px solid #AAA;">
                        <thead style="background: #E1E1E1;">
                            <tr>
                                <th width="40%">RCS ID</th>
                                <th colspan="{$colspan}">Grades</th>
                                <th>Total</th>
HTML;
    if ($colspan2 > 0) {
        print <<<HTML
                                <th colspan="{$colspan2}">Text</th>
HTML;
    }
    print <<<HTML

                            </tr>
                        </thead>

                        <tbody style="background: #f9f9f9;">
HTML;

    $params = array($user_id);
    if((isset($_GET["all"]) && $_GET["all"] == "true") || $user_is_administrator == true)
    {
        $params = array();
        $db->query("SELECT * FROM sections ORDER BY section_id ASC", $params);
    }
    else
    {
        $params = array($user_id);
        $db->query("SELECT * FROM relationships_users WHERE user_id=? ORDER BY section_id ASC", $params);
    }

    $colspan += 2;
    $colspan += $colspan2;

    foreach($db->rows() as $section)
    {
        $section_id = intval($section['section_id']);
		print <<<HTML

                            <tr class="info">
                                <td colspan="{$colspan}" style="text-align:center;">
                                    Students Enrolled in Section {$section_id}
                                </td>
                            </tr>
HTML;
        $params = array($test_row['test_id'],intval($section["section_id"]));
        $db->query("SELECT s.*,gt.grade_test_value,gt.grade_test_questions,gt.grade_test_text FROM students AS s LEFT JOIN (SELECT * FROM grades_tests WHERE test_id=?) 
                    AS gt ON s.student_rcs=gt.student_rcs WHERE s.student_section_id=? ORDER BY student_rcs ASC", $params);
        foreach($db->rows() as $row)
        {
            $student_info = $row;
            $temp = $row;
            print <<<HTML
                            <tr>
                                <td style="width:40%;">
                                    {$student_info["student_rcs"]} ({$student_info["student_last_name"]}, {$student_info["student_first_name"]})
                                </td>
HTML;
            //$params = array(intval($test_row["test_id"]), intval($row["student_id"]));
            //$db->query("SELECT grade_test_value FROM grades_tests WHERE test_id=? AND student_id=?", $params);
            //$temp = $db->row();
            $test_grade = (isset($temp["grade_test_value"]) ? $temp["grade_test_value"] : "0");
            if (isset($temp['grade_test_questions'])) {
                $question_grades = pgArrayToPhp($temp['grade_test_questions']);
            }
            else {
                $question_grades = array();
                for ($i = 0; $i < $test_row['test_questions']; $i++) {
                    $question_grades[] = 0;
                }
            }

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
            }
            print <<<HTML
                                <td style="width: 40px" id="cell-{$test_row["test_id"]}-{$row['student_rcs']}-score">{$test_grade}</td>
HTML;
            for ($i = 0; $i < $test_row['test_text_fields']; $i++) {
                print <<<HTML
                                <td class="input-container" style="border: 1px solid black">
                                    <input id="cell-{$test_row["test_id"]}-{$row["student_rcs"]}-t{$i}" elem="text" type="text" value="{$text_fields[$i]}" {$disabled} />
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

$js_array_grades = "";
$js_array_questions = "";
$js_array_text = "";
foreach ($tests as $test) {
    $js_array_grades .= $test['test_id'].':"'.$test['test_max_grade'].'",';
    $js_array_questions .= $test['test_id'].':"'.$test['test_questions'].'",';
    $js_array_text .= $test['test_id'].':"'.$test['test_text_fields'].'",';
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
			url = "{$BASE_URL}/account/ajax/account-tests.php?course={$_GET['course']}&semester={$_GET['semester']}&test=" + test + "&rcs=" + rcs + "&grade=" + total + extra;
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


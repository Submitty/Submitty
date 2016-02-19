<?php
include "../header.php";

$account_subpages_unlock = true;

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
        <h3 id="myModalLabel" style="width:50%; display:inline-block;">Other</h3>
    </div>

    <div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
        <div class="bs-docs-example">
            <ul id="myTab" class="nav nav-tabs">
HTML;

$params = array();
$db->query("SELECT * FROM other_grades ORDER BY other_due_date, other_id", $params);

$first = true;
$others = $db->rows();
foreach($others as $other)
{
    if($first) {
        print <<<HTML
                <li class="active"><a href="#other-{$other["oid"]}" data-toggle="tab">{$other["other_name"]}</a></li>
HTML;
    }
    else {
        print <<<HTML
                <li><a href="#other-{$other["oid"]}" data-toggle="tab">{$other["other_name"]}</a></li>
HTML;
    }

    $first = false;
}

print <<<HTML
            </ul>
            <div id="myTabContent" class="tab-content">
HTML;
$first = true;
foreach($others as $other) {
    $extra = ($first) ? ' active in' : '';
    print <<<HTML
                <div class="tab-pane fade{$extra}" id="other-{$other["oid"]}">
                    <table class="table table-bordered" id="othersTable" style=" border: 1px solid #AAA;">
                        <thead style="background: #E1E1E1;">
                            <tr>
                                <th width="40%">RCS ID</th>
                                <th>Grade</th>
                                <th>Text</th>
HTML;
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

    foreach($db->rows() as $section)
    {
        $section_id = intval($section['section_id']);
        print <<<HTML

                            <tr class="info">
                                <td colspan="3" style="text-align:center;">
                                    Students Enrolled in Section {$section_id}
                                </td>
                            </tr>
HTML;
        $params = array($other['oid'],intval($section["section_id"]));
        $db->query("SELECT s.*, gt.grades_other_id, gt.oid, gt.grades_other_score, gt.grades_other_text FROM students AS s LEFT JOIN (SELECT * FROM grades_others WHERE oid=?) AS gt ON s.student_rcs=gt.student_rcs WHERE s.student_section_id=? ORDER BY student_rcs ASC", $params);
        foreach($db->rows() as $row)
        {
            print <<<HTML
                            <tr>
                                <td style="width:40%;">
                                    {$row["student_rcs"]} ({$row["student_last_name"]}, {$row["student_first_name"]})
                                </td>
                                <td class="input-container" style="width:1%; border: 1px solid black">
                                    <input style="" id="cell-{$other["oid"]}-{$row["student_rcs"]}-score" type="text" value="{$row['grades_other_score']}" />
                                </td>
                                <td class="input-container" style="border: 1px solid black">
                                    <input style="width:96%;" id="cell-{$other['oid']}-{$row['student_rcs']}-text" type="text" value="{$row['grades_other_text']}" />
                                </td>
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

echo <<<HTML
	<script type="text/javascript">
		$("input[id^=cell-]").change(function() {
			var name = $(this).attr("id");
			name = name.split("-");
			var oid = name[1];
			var rcs = name[2];

			var score = parseFloat($("input#cell-" + oid + "-" + rcs + "-score").val());
			var text = $("input#cell-" + oid + "-" + rcs + "-text").val();

            if(isNaN(score) || score < 0) {
                score = 0;
                $("input#cell-" + oid + "-" + rcs + "-score").val("0");
            }

			var url = "{$BASE_URL}/account/ajax/account-other.php?course={$_GET['course']}&oid=" + oid + "&rcs=" + rcs;
            updateColor(this, url, score, text);
		});

		function updateColor(item, url, score, text) {
			$(item).css("border-right", "15px solid #149bdf");
			submitAJAX(url, updateSuccess, updateFail, item, score, text);
		}

		function updateSuccess(item) {
			$(item).stop(true, true).animate({"border-right-width":"0px"}, 400);
		}

		function updateFail(item) {
			$(item).css("border-right-width", "15px");
			$(item).stop(true, true).animate({"border-right-color":"#DA4F49"}, 400);
		}

		function submitAJAX(url, callBackSucess, callBackFail, item, score, text) {
			$.ajax(url, {
			    type: "POST",
			    data: {
			        score: score,
			        text: text
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


<?php

require_once "../header.php";

check_administrator();

$output = "";

$db->query("
SELECT
    r.*,q.rubric_parts,q.rubric_questions,q.rubric_score,q.rubric_ec
FROM
    rubrics as r
LEFT JOIN(
    SELECT
        rubric_id as rid,
        count(distinct(question_part_number)) as rubric_parts,
        count(question_id) as rubric_questions,
        sum(case when question_extra_credit = 0 then question_total else 0 end) as rubric_score,
        sum(case when question_extra_credit = 1 then question_total else 0 end) as rubric_ec
    FROM
        questions
    GROUP BY
        rubric_id
) as q ON r.rubric_id = q.rid
ORDER BY rubric_id ASC",array());

$output .= <<<HTML
<style type="text/css">
    body {
        overflow: scroll;
    }

    select {
        margin-top:7px;
        width: 60px;
        min-width: 60px;
    }

    #container-rubrics
    {
        width:1200px;
        margin:100px auto;
        margin-top: 100px;
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

    #table-rubrics {
        /*width: 80%;*/
        margin-left: 30px;
        margin-top: 10px;
    }

    #table-rubrics td {
        display: inline-block;
        overflow: auto;
        padding-top: 10px;
        padding-bottom: 10px;
    }

    #table-rubrics tr {
        border-bottom: 1px solid darkgray;
        margin-top: 5px;
    }

    #table-rubrics td.rubrics-id {
        width: 50px;
    }

    #table-rubrics td.rubrics-name {
        width: 200px;
    }

    #table-rubrics td.rubrics-parts {
        width: 160px;
    }

    #table-rubrics td.rubrics-questions {
        width: 160px;
    }

    #table-rubrics td.rubrics-score {
        width: 160px;
    }

    #table-rubrics td.rubrics-due {
        width: 260px;
    }

    #table-rubrics td.rubrics-options {
        width: 100px;
    }


    .edit-rubrics-icon {
        display: block;
        float:left;
        margin-right:20px;
        margin-top: 5px;
        position: relative;
        width: 24px;
        height: 24px;
        overflow: hidden;
    }

    .edit-rubrics-confirm {
        max-width: none;
        position: absolute;
        top:0;
        left:-280px;
    }

    .edit-rubrics-cancel {
        max-width: none;
        position: absolute;
        top:0;
        left:-310px;
    }

    .submit-button {
        float: right;
        margin-top: -28px;
        margin-right: 170px;
    }

</style>
<script type="text/javascript">
    function deleteRubric(rubric_id) {
        var rubric_name = $('td#rubric-'+rubric_id+'-title').text();
        var c = window.confirm("Are you sure you want to delete '" + rubric_name + "'?");
        if (c == true) {
            $.ajax('{$BASE_URL}/account/ajax/admin-rubrics.php?course={$_GET['course']}&semester={$_GET['semester']}&action=delete&id='+rubric_id, {
                type: "POST",
		        data: {
                    csrf_token: '{$_SESSION['csrf']}'
                }
            })
            .done(function(response) {
                var res_array = response.split("|");
                if (res_array[0] == "success") {
                    window.alert("'" + rubric_name + "' deleted");
                    $('tr#rubric-'+rubric_id).remove();
                }
                else {
                    window.alert("'" + rubric_name + "' could not be deleted");
                }
            })
            .fail(function() {
                window.alert("[ERROR] Refresh Page");
            });
        }
    }

    function fixSequences() {
        $.ajax('{$BASE_URL}/account/ajax/admin-rubrics.php?course={$_GET['course']}&semester={$_GET['semester']}&action=sequence', {
            type: "POST",
            data: {
                csrf_token: '{$_SESSION['csrf']}'
            }
        })
        .done(function(response) {
            var res_array = response.split("|");
            if (res_array[0] == "success") {
                window.alert("DB Sequences recalculated");
            }
            else {
                console.log(response);
                window.alert("[DB ERROR] Refresh page");
            }
        })
        .fail(function() {
            window.alert("[AJAX ERROR] Refresh page");
        });
    }
</script>
<div id="container-rubrics">
    <div class="modal-header">
        <h3 id="myModalRubricel">Manage Rubrics</h3>
        <span class="submit-button">
            <input class="btn btn-primary" onclick="window.location.href='{$BASE_URL}/account/admin-rubric.php?course={$_GET['course']}&semester={$_GET['semester']}'" type="submit" value="Create New Rubric"/>
            &nbsp;&nbsp;
            <input class="btn btn-primary" onclick="fixSequences();" type="submit" value="Fix DB Sequences" />
        </span>
    </div>
    <table id="table-rubrics">
        <tr>
            <td class="rubrics-id">ID</td>
            <td class="rubrics-name">Name</td>
            <td class="rubrics-parts">Parts</td>
            <td class="rubrics-questions">Questions</td>
            <td class="rubrics-score">Score</td>
            <td class="rubrics-due">Due Date</td>
            <td class="rubrics-options">Options</td>
        </tr>
HTML;

foreach ($db->rows() as $rubric) {
    $output .= <<<HTML
        <tr id='rubric-{$rubric['rubric_id']}'">
            <td class="rubrics-id" id="rubric-{$rubric['rubric_id']}-id">{$rubric['rubric_id']}</td>
            <td class="rubrics-name" id="rubric-{$rubric['rubric_id']}-title">{$rubric['rubric_name']}</td>
            <td class="rubrics-parts" id="rubric-{$rubric['rubric_id']}-parts">{$rubric['rubric_parts']}</td>
            <td class="rubrics-questions" id="rubric-{$rubric['rubric_id']}-questions">{$rubric['rubric_questions']}</td>
            <td class="rubrics-score" id="rubric-{$rubric['rubric_id']}-score">{$rubric['rubric_score']} ({$rubric['rubric_ec']})</td>
            <td class="rubrics-due" id="rubric-{$rubric['rubric_id']}-due">{$rubric['rubric_due_date']}</td>
            <td id="rubric-{$rubric['rubric_id']}-options"><a href="{$BASE_URL}/account/admin-rubric.php?course={$_GET['course']}&semester={$_GET['semester']}&action=edit&id={$rubric['rubric_id']}">Edit</a> |
            <a onclick="deleteRubric({$rubric['rubric_id']});">Delete</a></td>
        </tr>
HTML;
}

$output .= <<<HTML
    </table><br />
</div>
HTML;

print $output;

include "../footer.php";
?>
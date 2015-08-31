<?php

require_once "../header.php";

check_administrator();

$output = "";

$db->query("SELECT * FROM tests ORDER BY test_number ASC",array());

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

    #container-tests
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

    #table-tests {
        /*width: 80%;*/
        margin-left: 30px;
        margin-top: 10px;
    }

    #table-tests td {
        display: inline-block;
        overflow: auto;
        padding-top: 10px;
        padding-bottom: 10px;
    }

    #table-tests tr {
        border-bottom: 1px solid darkgray;
        margin-top: 5px;
    }

    #table-tests td.tests-number,
    #table-tests td.tests-score,
    #table-tests td.tests-curve,
    #table-tests td.tests-questions,
    #table-tests td.tests-text,
    #table-tests td.tests-locked {
        width: 140px;
    }

    #table-tests td.tests-number input,
    #table-tests td.tests-questions input,
    #table-tests td.tests-text input,
    #table-tests td.tests-score input,
    #table-tests td.tests-curve input,
    #table-tests td.tests-locked input {
        margin-bottom: 0;
        width: 60px;
    }

    #table-tests td.tests-options {
        width: 100px;
    }


    .edit-tests-icon {
        display: block;
        float:left;
        margin-right:20px;
        margin-top: 5px;
        position: relative;
        width: 24px;
        height: 24px;
        overflow: hidden;
    }

    .edit-tests-confirm {
        max-width: none;
        position: absolute;
        top:0;
        left:-280px;
    }

    .edit-tests-cancel {
        max-width: none;
        position: absolute;
        top:0;
        left:-310px;
    }

</style>
<script type="text/javascript">
    function deleteTest(test_id) {
        var test_number = $('#test-'+test_id).attr('test-number');
        var c = window.confirm("Are you sure you want to delete Test " + test_number + "?");
        if (c == true) {
            $.ajax('{$BASE_URL}/account/ajax/admin-tests.php?course={$_GET['course']}&action=delete&id='+test_id)
                .done(function(response) {
                    var res_array = response.split("|");
                    if (res_array[0] == "success") {
                        window.alert("Test " + test_number + " deleted");
                        $('tr#test-'+test_id).remove();
                    }
                    else {
                        window.alert("Test " + test_number + " could not be deleted");
                        console.log(response);
                    }
                })
                .fail(function() {
                    window.alert("[ERROR] Refresh Page");
                    console.log(response);
                });
        }
    }

    function createTest() {
        var number    = $('input#new-test-number').val();
        var questions = $('input#new-test-questions').val();
        var text      = $('input#new-test-text').val();
        var score     = $('input#new-test-score').val();
        var curve     = $('input#new-test-curve').val();
        var locked    = $('input#new-test-locked').val();

        $.ajax('{$BASE_URL}/account/ajax/admin-tests.php?course={$_GET['course']}&action=new',
            {
                type:'GET',
                data: {
                    number: number,
                    questions: questions,
                    text: text,
                    score: score,
                    curve: curve,
                    locked: locked
                }
            })
            .done(function(response) {
                var res_array = response.split("|");
                if (res_array[0] == "success") {
                    var html_response = "<tr id='test-"+res_array[1]+"' test-number='"+number+"'>" +
                     "<td class='tests-number' id='test-"+res_array[1]+"-number'>Test "+number+"</td>" +
                     "<td class='tests-score' id='test-"+res_array[1]+"-score'>"+score+"</td>" +
                     "<td class='tests-curve' id='test-"+res_array[1]+"-curve'>"+curve+"</td>" +
                     "<td class='tests-questions' id='test-"+res_array[1]+"-questions'>"+questions+"</td>" +
                     "<td class='tests-text' id='test-"+res_array[1]+"-text'>"+text+"</td>" +
                     "<td class='tests-locked' id='test-"+res_array[1]+"-locked'>"+locked+"</td>" +
                     "<td class='tests-options' id='test-"+res_array[1]+"-options'><a onclick='editTest("+res_array[1]+");'>Edit</a> | "+
                     "<a onclick=\"deleteTest("+res_array[1]+");\">Delete</a></td>" +
                     "</tr>";
                    $('table#table-tests').append(html_response);
                    window.alert("Test "+number+" created");
                }
                else {
                    window.alert("Test " + number + " not created");
                    console.log(response);
                }
            })
            .fail(function() {
                window.alert("[ERROR] Refresh Page");
                console.log(response);
            });

        $('input#new-test-number').val("");
        $('input#new-test-questions').val("");
        $('input#new-test-text').val("");
        $('input#new-test-score').val("");
        $('input#new-test-curve').val("");
        $('input#new-test-locked').val("");
    }

    function editTest(test_id) {
        var number = $('tr#test-'+test_id).attr('test-number');
        var questions   = $('td#test-'+test_id+'-questions').text();
        var text = $('td#test-'+test_id+'-text').text();
        var score  = $('td#test-'+test_id+'-score').text();
        var curve  = $('td#test-'+test_id+'-curve').text();
        var locked = $('td#test-'+test_id+'-locked').text();

        $('#test-'+test_id+'-number').html("<input type='text' id='edit-test-"+test_id+"-number' old_value='"+number+"' value='"+number+"' />");
        $('#test-'+test_id+'-score').html("<input type='text' id='edit-test-"+test_id+"-score' old_value='"+score+"' value='"+score+"' />");
        $('#test-'+test_id+'-curve').html("<input type='text' id='edit-test-"+test_id+"-curve' old_value='"+curve+"' value='"+curve+"' />");
        $('#test-'+test_id+'-questions').html("<input type='text' id='edit-test-"+test_id+"-questions' old_value='"+questions+"' value='"+questions+"' />");
        $('#test-'+test_id+'-text').html("<input type='text' id='edit-test-"+test_id+"-text' old_value='"+text+"' value='"+text+"' />");
        $('#test-'+test_id+'-locked').html("<input type='text' id='edit-test-"+test_id+"-locked' old_value='"+locked+"' value='"+locked+"' />");
        $('#test-'+test_id+'-options').html("<a class='edit-tests-icon' onclick='submitEdit("+test_id+");'>"+
        "<img class='edit-tests-confirm' src='../toolbox/include/bootstrap/img/glyphicons-halflings.png' /></a>" +
        "<a class='edit-tests-icon' onclick='cancelEdit("+test_id+");'><img class='edit-tests-cancel' src='../toolbox/include/bootstrap/img/glyphicons-halflings.png' /></a>");
    }

    function submitEdit(test_id) {
        var old_number = $('tr#test-'+test_id).attr('test-number');
        var new_number = $('input#edit-test-'+test_id+'-number').val();
        var questions_sel   = $('input#edit-test-'+test_id+'-questions');
        var new_questions   = questions_sel.val();
        var old_questions   = questions_sel.attr('old_value');
        var text_sel = $('input#edit-test-'+test_id+'-text');
        var new_text = text_sel.val();
        var old_text = text_sel.attr('old_value');
        var score_sel  = $('input#edit-test-'+test_id+'-score');
        var new_score  = score_sel.val();
        var old_score  = score_sel.attr('old_value');
        var curve_sel  = $('input#edit-test-'+test_id+'-curve');
        var new_curve  = curve_sel.val();
        var old_curve  = curve_sel.attr('old_value');
        var locked_sel  = $('input#edit-test-'+test_id+'-locked');
        var new_locked  = locked_sel.val();
        var old_locked  = locked_sel.attr('old_value');

        var html_response = "";
        var number;
        var questions;
        var text;
        var score;
        var curve;
        var locked;

        $.ajax('{$BASE_URL}/account/ajax/admin-tests.php?course={$_GET['course']}&action=edit', {
            type: 'GET',
            data: {
                id: test_id,
                number: new_number,
                questions:   new_questions,
                text: new_text,
                score: new_score,
                curve: new_curve,
                locked: new_locked
            }
        })
        .done(function(response) {
            var res_array = response.split("|");
            if (res_array[0] == "success") {
                number = new_number;
                score = new_score;
                curve = new_curve;
                questions = new_questions;
                text = new_text;
                locked = new_locked;
                window.alert("Test edited");
            }
            else {
                number = old_number;
                score = old_score;
                curve = old_curve;
                questions = old_questions;
                text = old_text;
                locked = old_locked;
                window.alert("Test edit failure");
                console.log(response);
            }
            html_response = "" +
                     "<td class='tests-number' id='test-"+res_array[1]+"-number'>Test "+number+"</td>" +
                     "<td class='tests-score' id='test-"+res_array[1]+"-score'>"+score+"</td>" +
                     "<td class='tests-curve' id='test-"+res_array[1]+"-curve'>"+curve+"</td>" +
                     "<td class='tests-questions' id='test-"+res_array[1]+"-questions'>"+questions+"</td>" +
                     "<td class='tests-text' id='test-"+res_array[1]+"-text'>"+text+"</td>" +
                     "<td class='tests-locked' id='test-"+res_array[1]+"-locked'>"+locked+"</td>" +
                     "<td class='tests-options' id='test-"+res_array[1]+"-options'><a onclick='editTest("+test_id+");'>Edit</a> | "+
                     "<a onclick='deleteTest("+test_id+");'>Delete</a></td>" +
                     "";
            $('tr#test-'+test_id).html(html_response);
            $('tr#test-'+test_id).attr('test-number',number);
            //$('#test-'+test_id+'-options').removeClass('tests-options');
            //$('table#table-tests').append(html_response);
        })
        .fail(function() {
            html_response = "" +
                     "<td class='tests-number' id='test-"+test_id+"-number'>Test "+old_number+"</td>" +
                     "<td class='tests-score' id='test-"+test_id+"-score'>"+old_score+"</td>" +
                     "<td class='tests-curve' id='test-"+test_id+"-curve'>"+old_curve+"</td>" +
                     "<td class='tests-questions' id='test-"+test_id+"-questions'>"+old_questions+"</td>" +
                     "<td class='tests-text' id='test-"+test_id+"-text'>"+old_text+"</td>" +
                     "<td class='tests-locked' id='test-"+test_id+"-locked'>"+old_locked+"</td>" +
                     "<td class='tests-options' id='test-"+test_id+"-options'><a onclick='editTest("+test_id+");'>Edit</a> | "+
                     "<a onclick='deleteTest("+test_id+");'>Delete</a></td>" +
                     "";
            $('tr#test-'+test_id).html(html_response);
            $('tr#test-'+test_id).attr('test-number',old_number);
            //$('#test-'+test_id+'-options').removeClass('tests-options');
            window.alert("[ERROR] Refresh Page");
            console.log(response);
        });
    }

    function cancelEdit(test_id) {
        var old_number = $('tr#test-'+test_id).attr('test-number');
        var old_score  = $('input#edit-test-'+test_id+'-score').attr('old_value');
        var old_curve   = $('input#edit-test-'+test_id+'-curve').attr('old_value');
        var old_questions  = $('input#edit-test-'+test_id+'-questions').attr('old_value');
        var old_text = $('input#edit-test-'+test_id+'-text').attr('old_value');
        var old_locked = $('input#edit-test-'+test_id+'-locked').attr('old_value');

        var html_response = "" +
             "<td class='tests-number' id='test-"+test_id+"-number'>Test "+old_number+"</td>" +
             "<td class='tests-score' id='test-"+test_id+"-score'>"+old_score+"</td>" +
             "<td class='tests-curve' id='test-"+test_id+"-curve'>"+old_curve+"</td>" +
             "<td class='tests-questions' id='test-"+test_id+"-questions'>"+old_questions+"</td>" +
             "<td class='tests-text' id='test-"+test_id+"-text'>"+old_text+"</td>" +
             "<td class='tests-locked' id='test-"+test_id+"-locked'>"+old_locked+"</td>" +
             "<td class='tests-options' id='test-"+test_id+"-options'><a onclick='editTest("+test_id+");'>Edit</a> | "+
             "<a onclick='deleteTest("+test_id+");'>Delete</a></td>";
        $('tr#test-'+test_id).html(html_response);
        $('tr#test-'+test_id).attr('test-number',old_number);
    }
</script>
<div id="container-tests">
    <div class="modal-header">
        <h3 id="myModalTestel">Manage Tests</h3>
    </div>
    <table id="table-tests">
        <tr>
            <td class="tests-number">Number</td>
            <td class="tests-score">Max Score</td>
            <td class="tests-curve">Curve</td>
            <td class="tests-questions">Questions</td>
            <td class="tests-text">Text Fields</td>
            <td class="tests-locked">Locked</td>
            <td >Options</td>
        </tr>
HTML;

$last_test = 0;

foreach ($db->rows() as $test) {
    $last_test = intval($test['test_number']);
    $test['test_locked'] = ($test['test_locked']) ? 1 : 0;
    $output .= <<<HTML
        <tr id='test-{$test['test_id']}' test-number="{$test['test_number']}">
            <td class="tests-number" id="test-{$test['test_id']}-number">Test {$test['test_number']}</td>
            <td class="tests-score" id="test-{$test['test_id']}-score">{$test['test_max_grade']}</td>
            <td class="tests-curve" id="test-{$test['test_id']}-curve">{$test['test_curve']}</td>
            <td class="tests-questions" id="test-{$test['test_id']}-questions">{$test['test_questions']}</td>
            <td class="tests-text" id="test-{$test['test_id']}-text">{$test['test_text_fields']}</td>
            <td class="tests-locked" id="test-{$test['test_id']}-locked">{$test['test_locked']}</td>
            <td id="test-{$test['test_id']}-options"><a onclick="editTest({$test['test_id']})">Edit</a> |
            <a onclick="deleteTest({$test['test_id']});">Delete</a></td>
        </tr>
HTML;
}
$last_test++;

$output .= <<<HTML
    </table><br />
    <span style="float:left;margin-left:10px;margin-top:5px;margin-right:5px;">New Test:</span>
    <input style="width: 70px" type="text" size="10" id='new-test-number' placeholder="Number" />&nbsp;
    <input style="width: 70px" type="text" size="70" id='new-test-score' placeholder="Max Score" /> &nbsp;
    <input style="width: 70px" type="text" size="70" id="new-test-curve" placeholder="Curve" /> &nbsp;
    <input style="width: 70px" type="text" size="10" id='new-test-questions' placeholder="Questions" />&nbsp;
    <input style="width: 70px" type="text" size="10" id='new-test-text' placeholder="Text Fields" />&nbsp;
    <input style="width: 70px" type="text" size="10" id='new-test-locked' placeholder="Locked" />&nbsp;
    <span style="float:right;margin-right: 400px">
        <input class="btn btn-primary" onclick="createTest();" type="submit" value="Create New Test"/>
    </span>
</div>
HTML;

print $output;

include "../footer.php";
?>
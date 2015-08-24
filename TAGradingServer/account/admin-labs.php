<?php

require_once "../header.php";

$output = "";

if($user_is_administrator) {
    $account_subpages_unlock = true;
}
else {
    die("Not allowed on this part of the site");
}

$db->query("SELECT * FROM labs ORDER BY lab_number ASC",array());

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

    #container-labs
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

    #table-labs {
        /*width: 80%;*/
        margin-left: 30px;
        margin-top: 10px;
    }

    #table-labs td {
        display: inline-block;
        overflow: auto;
        padding-top: 10px;
        padding-bottom: 10px;
    }

    #table-labs tr {
        border-bottom: 1px solid darkgray;
        margin-top: 5px;
    }

    #table-labs td.labs-number {
        width: 80px;
    }

    #table-labs td.labs-number input,
    #table-labs td.labs-code input {
        margin-bottom: 0;
        width: 60px;
    }

    #table-labs td.labs-checkpoints {
        width: 600px;
    }

    #table-labs td.labs-checkpoints input {
        margin-bottom: 0;
        width: 550px;
    }

    #table-labs td.labs-code {
        width: 80px;
    }

    #table-labs td.labs-options {
        width: 100px;
    }


    .edit-labs-icon {
        display: block;
        float:left;
        margin-right:20px;
        margin-top: 5px;
        position: relative;
        width: 24px;
        height: 24px;
        overflow: hidden;
    }

    .edit-labs-confirm {
        max-width: none;
        position: absolute;
        top:0;
        left:-280px;
    }

    .edit-labs-cancel {
        max-width: none;
        position: absolute;
        top:0;
        left:-310px;
    }

</style>
<script type="text/javascript">
    function deleteLab(lab_id) {
        var lab_number = $('#lab-'+lab_id).attr('lab-number');
        var c = window.confirm("Are you sure you want to delete Lab " + lab_number + "?");
        if (c == true) {
            $.ajax('{$BASE_URL}/account/ajax/admin-labs.php?course={$_GET['course']}&action=delete&id='+lab_id)
                .done(function(response) {
                    var res_array = response.split("|");
                    if (res_array[0] == "success") {
                        window.alert("Lab " + lab_number + " deleted");
                        $('tr#lab-'+lab_id).remove();
                    }
                    else {
                        window.alert("Lab " + lab_number + " could not be deleted");
                    }
                })
                .fail(function() {
                    window.alert("[ERROR] Refresh Page");
                });
        }
    }

    function createLab() {
        var number = $('input#new-lab-number').val();
        var code   = $('input#new-lab-code').val();
        var checks = $('input#new-lab-checkpoints').val();

        $.ajax('{$BASE_URL}/account/ajax/admin-labs.php?course={$_GET['course']}&action=new',
            {
                type:'GET',
                data: {
                    number: number,
                    code: code,
                    checkpoints: checks
                }
            })
            .done(function(response) {
                var res_array = response.split("|");
                if (res_array[0] == "success") {
                    var html_response = "<tr id='lab-"+res_array[1]+"' lab-number='"+number+"'>" +
                     "<td class='labs-number' id='lab-"+res_array[1]+"-number'>Lab "+number+"</td>" +
                     "<td class='labs-checkpoints' id='lab-"+res_array[1]+"-checkpoints'>"+checks+"</td>" +
                     "<td class='labs-code' id='lab-"+res_array[1]+"-code'>"+code+"</td>" +
                     "<td class='labs-options' id='lab-"+res_array[1]+"-options'><a onclick='editLab("+res_array[1]+");'>Edit</a> | "+
                     "<a onclick=\"deleteLab("+res_array[1]+");\">Delete</a></td>" +
                     "</tr>";
                    $('table#table-labs').append(html_response);
                    window.alert("Lab "+number+" created");
                }
                else {
                    window.alert("Lab " + number + " not created");
                }
            })
            .fail(function() {
                window.alert("[ERROR] Refresh Page");
            });

        $('input#new-lab-number').val("");
        $('input#new-lab-code').val("");
        $('input#new-lab-checkpoints').val("");
    }

    function editLab(lab_id) {
        var number = $('tr#lab-'+lab_id).attr('lab-number');
        var code   = $('td#lab-'+lab_id+'-code').text();
        var checks = $('td#lab-'+lab_id+'-checkpoints').text();

        $('#lab-'+lab_id+'-number').html("<input type='text' id='edit-lab-"+lab_id+"-number' old_value='"+number+"' value='"+number+"' />");
        $('#lab-'+lab_id+'-checkpoints').html("<input type='text' id='edit-lab-"+lab_id+"-checkpoints' old_value='"+checks+"' value='"+checks+"' />");
        $('#lab-'+lab_id+'-code').html("<input type='text' id='edit-lab-"+lab_id+"-code' old_value='"+code+"' value='"+code+"' />");
        $('#lab-'+lab_id+'-options').html("<a class='edit-labs-icon' onclick='submitEdit("+lab_id+");'>"+
        "<img class='edit-labs-confirm' src='../toolbox/include/bootstrap/img/glyphicons-halflings.png' /></a>" +
        "<a class='edit-labs-icon' onclick='cancelEdit("+lab_id+");'><img class='edit-labs-cancel' src='../toolbox/include/bootstrap/img/glyphicons-halflings.png' /></a>");

        //$('#lab-'+lab_id+'-options').addClass('labs-options');

        console.log($('#lab-'+lab_id+'-number'));
    }

    function submitEdit(lab_id) {
        var old_number = $('tr#lab-'+lab_id).attr('lab-number');
        var new_number = $('input#edit-lab-'+lab_id+'-number').val();
        var code_sel   = $('input#edit-lab-'+lab_id+'-code');
        var new_code   = code_sel.val();
        var old_code   = code_sel.attr('old_value');
        var checks_sel = $('input#edit-lab-'+lab_id+'-checkpoints');
        var new_checks = checks_sel.val();
        var old_checks = checks_sel.attr('old_value');

        var html_response = "";
        var number;
        var code;
        var checks;

        $.ajax('{$BASE_URL}/account/ajax/admin-labs.php?course={$_GET['course']}&action=edit', {
            type: 'GET',
            data: {
                id: lab_id,
                number: new_number,
                code:   new_code,
                checkpoints: new_checks
            }
        })
        .done(function(response) {
            var res_array = response.split("|");
            if (res_array[0] == "success") {
                number = new_number;
                checks = new_checks;
                code = new_code;

                window.alert("Lab edited");
            }
            else {
                number = old_number;
                checks = old_checks;
                code = old_code;
                window.alert("Lab edit failure");
                console.log(response);
            }
            html_response = "" +
                     "<td class='labs-number' id='lab-"+res_array[1]+"-number'>Lab "+number+"</td>" +
                     "<td class='labs-checkpoints' id='lab-"+res_array[1]+"-checkpoints'>"+checks+"</td>" +
                     "<td class='labs-code' id='lab-"+res_array[1]+"-code'>"+code+"</td>" +
                     "<td class='labs-options' id='lab-"+res_array[1]+"-options'><a onclick='editLab("+lab_id+");'>Edit</a> | "+
                     "<a onclick='deleteLab("+lab_id+");'>Delete</a></td>" +
                     "";
            $('tr#lab-'+lab_id).html(html_response);
            $('tr#lab-'+lab_id).attr('lab-number',number);
            //$('#lab-'+lab_id+'-options').removeClass('labs-options');
            //$('table#table-labs').append(html_response);
        })
        .fail(function() {
            html_response = "" +
                     "<td class='labs-number' id='lab-"+lab_id+"-number'>Lab "+old_number+"</td>" +
                     "<td class='labs-checkpoints' id='lab-"+lab_id+"-checkpoints'>"+old_checks+"</td>" +
                     "<td class='labs-code' id='lab-"+lab_id+"-code'>"+old_code+"</td>" +
                     "<td class='labs-options' id='lab-"+lab_id+"-options'><a onclick='editLab("+lab_id+");'>Edit</a> | "+
                     "<a onclick='deleteLab("+lab_id+");'>Delete</a></td>" +
                     "";
            $('tr#lab-'+lab_id).html(html_response);
            $('tr#lab-'+lab_id).attr('lab-number',old_number);
            //$('#lab-'+lab_id+'-options').removeClass('labs-options');
            window.alert("[ERROR] Refresh Page");
        });
    }

    function cancelEdit(lab_id) {
        var old_number = $('tr#lab-'+lab_id).attr('lab-number');
        var old_code   = $('input#edit-lab-'+lab_id+'-code').attr('old_value');
        var old_checks = $('input#edit-lab-'+lab_id+'-checkpoints').attr('old_value');
        var html_response = "" +
             "<td class='labs-number' id='lab-"+lab_id+"-number'>Lab "+old_number+"</td>" +
             "<td class='labs-checkpoints' id='lab-"+lab_id+"-checkpoints'>"+old_checks+"</td>" +
             "<td class='labs-code' id='lab-"+lab_id+"-code'>"+old_code+"</td>" +
             "<td class='labs-options' id='lab-"+lab_id+"-options'><a onclick='editLab("+lab_id+");'>Edit</a> | "+
             "<a onclick='deleteLab("+lab_id+");'>Delete</a></td>";
        $('tr#lab-'+lab_id).html(html_response);
        $('tr#lab-'+lab_id).attr('lab-number',old_number);
    }
</script>
<div id="container-labs">
    <div class="modal-header">
        <h3 id="myModalLabel">Manage Labs</h3>
    </div>
    <table id="table-labs">
        <tr>
            <td class="labs-number">Number</td>
            <td class="labs-checkpoints">Checkpoints</td>
            <td class="labs-code">Code</td>
            <td class="labs-options">Options</td>
        </tr>
HTML;

$last_lab = 0;

foreach ($db->rows() as $lab) {
    $last_lab = intval($lab['lab_number']);
    $output .= <<<HTML
        <tr id='lab-{$lab['lab_id']}' lab-number="{$lab['lab_number']}">
            <td class="labs-number" id="lab-{$lab['lab_id']}-number">Lab {$lab['lab_number']}</td>
            <td class="labs-checkpoints" id="lab-{$lab['lab_id']}-checkpoints">{$lab['lab_checkpoints']}</td>
            <td class="labs-code" id="lab-{$lab['lab_id']}-code">{$lab['lab_code']}</td>
            <td id="lab-{$lab['lab_id']}-options"><a onclick="editLab({$lab['lab_id']})">Edit</a> |
            <a onclick="deleteLab({$lab['lab_id']});">Delete</a></td>
        </tr>
HTML;
}
$last_lab++;

$output .= <<<HTML
    </table><br />
    <span style="float:left;margin-left:10px;margin-top:5px;margin-right:5px;">New Lab:</span>
    <input style="width: 70px" type="text" size="10" id='new-lab-number' placeholder="Number" />&nbsp;
    <input style="width: 500px" type="text" size="70" id='new-lab-checkpoints' placeholder="Checkpoint 1,Checkpoint 2,..." /> &nbsp;
    <input style="width: 70px" type="text" size="10" id='new-lab-code' placeholder="LMS Code" />&nbsp;
    <span style="float:right;margin-right: 280px">
        <input class="btn btn-primary" onclick="createLab();" type="submit" value="Create New Lab"/>
    </span>
</div>
HTML;

print $output;

include "../footer.php";
?>
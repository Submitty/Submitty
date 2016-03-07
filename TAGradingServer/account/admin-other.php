<?php

require_once "../header.php";

check_administrator();

$output = "";

$db->query("SELECT * FROM other_grades ORDER BY other_due_date ASC",array());

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

    #container-other
    {
        width:1200px;
        margin: 100px auto;
        background-color: #fff;
        border: 1px solid #999;
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

    #table-other {
        margin-left: 30px;
        margin-top: 10px;
    }

    #table-other td {
        display: inline-block;
        overflow: auto;
        padding-top: 10px;
        padding-bottom: 10px;
    }

    #table-other tr {
        border-bottom: 1px solid darkgray;
        margin-top: 5px;
    }

    #table-other td.other-id,
    #table-other td.other-name,
    #table-other td.other-score,
    #table-other td.other-due {
        width: 240px;
    }

    #table-other td.other-score input {
        width: 40px;
        margin-bottom: 0;
    }

    #table-other td.other-id input,
    #table-other td.other-name input,
    #table-other td.other-due input {
        margin-bottom: 0;
        width: 200px;
    }

    #table-other td.other-options {
        width: 100px;
    }


    .edit-other-icon {
        display: block;
        float:left;
        margin-right:20px;
        margin-top: 5px;
        position: relative;
        width: 24px;
        height: 24px;
        overflow: hidden;
    }

    .edit-other-confirm {
        max-width: none;
        position: absolute;
        top:0;
        left:-280px;
    }

    .edit-other-cancel {
        max-width: none;
        position: absolute;
        top:0;
        left:-310px;
    }

</style>
<script type="text/javascript">
    function deleteOther(other_id) {
        var other_name = $('#other-'+other_id+'-name').val();
        var c = window.confirm("Are you sure you want to delete " + other_name + "?");
        if (c == true) {
            $.ajax('{$BASE_URL}/account/ajax/admin-other.php?course={$_GET['course']}&action=delete&oid='+other_id, {
                type: "POST",
		        data: {
                    csrf_token: '{$_SESSION['csrf']}'
                }
            })
            .done(function(response) {
                var res_array = response.split("|");
                if (res_array[0] == "success") {
                    window.alert(other_name + " deleted");
                    $('tr#other-'+other_id).remove();
                }
                else {
                    window.alert(other_name + " could not be deleted");
                    console.log(response);
                }
            })
            .fail(function() {
                window.alert("[ERROR] Refresh Page");
                console.log(response);
            });
        }
    }

    function createOther() {
        var id    = $('input#new-other-id').val().toLowerCase();
        var name = $('input#new-other-name').val();
        var score     = $('input#new-other-score').val();
        var due_date     = $('input#new-other-due').val();
        console.log(id);

        $.ajax('{$BASE_URL}/account/ajax/admin-other.php?course={$_GET['course']}&action=new', {
            type:'POST',
            data: {
                id: id,
                name: name,
                score: score,
                due_date: due_date,
                csrf_token: '{$_SESSION['csrf']}'
            }
        })
        .done(function(response) {
            var res_array = response.split("|");
            if (res_array[0] == "success") {
                var oid = res_array[1];
                var html_response = "<tr id='other-"+oid+"-row'>" +
                 "<td class='other-id' id='other-"+oid+"-id'>"+id+"</td>" +
                 "<td class='other-name' id='other-"+oid+"-name'>"+name+"</td>" +
                 "<td class='other-score' id='other-"+oid+"-score'>"+score+"</td>" +
                 "<td class='other-due' id='other-"+oid+"-due'>"+due_date+"</td>" +
                 "<td class='other-options' id='other-"+oid+"-options'><a onclick='editOther("+oid+");'>Edit</a> | "+
                 "<a onclick=\"deleteOther("+oid+");\">Delete</a></td>" +
                 "</tr>";
                $('table#table-other').append(html_response);
                window.alert(name+" created");
            }
            else {
                window.alert(name + " not created");
                console.log(response);
            }
        })
        .fail(function() {
            window.alert("[ERROR] Refresh Page");
            console.log(response);
        });

        $('input#new-other-id').val("");
        $('input#new-other-name').val("");
        $('input#new-other-score').val("");
        var d = new Date();
        $('input#new-other-due').val(pad(d.getMonth()+1, 2)+"/"+pad(d.getDate(), 2)+"/"+d.getFullYear()+" "+pad(d.getHours(), 2)+":"+pad(d.getMinutes(), 2)+":"+pad(d.getSeconds(), 2));
    }

    function pad(n, width, z) {
        z = z || '0';
        n = n + '';
        return n.length >= width ? n : new Array(width - n.length + 1).join(z) + n;
    }

    function editOther(other_id) {
        var id   = $('td#other-'+other_id+'-id').text();
        var name = $('td#other-'+other_id+'-name').text();
        var score  = $('td#other-'+other_id+'-score').text();
        var due_date = $('td#other-'+other_id+'-due').text();

        $('#other-'+other_id+'-id').html("<input type='text' id='edit-other-"+other_id+"-id' old_value='"+id+"' value='"+id+"' />");
        $('#other-'+other_id+'-name').html("<input type='text' id='edit-other-"+other_id+"-name' old_value='"+name+"' value='"+name+"' />");
        $('#other-'+other_id+'-score').html("<input type='text' id='edit-other-"+other_id+"-score' old_value='"+score+"' value='"+score+"' />");
        $('#other-'+other_id+'-due').html("<input type='text' id='edit-other-"+other_id+"-due' old_value='"+due_date+"' value='"+due_date+"' />");
        $('#other-'+other_id+'-options').html("<a class='edit-other-icon' onclick='submitEdit("+other_id+");'>"+
        "<img class='edit-other-confirm' src='../toolbox/include/bootstrap/img/glyphicons-halflings.png' /></a>" +
        "<a class='edit-other-icon' onclick='cancelEdit("+other_id+");'><img class='edit-other-cancel' src='../toolbox/include/bootstrap/img/glyphicons-halflings.png' /></a>");

        var datepicker = $('#edit-other-'+other_id+'-due');
        datepicker.datetimepicker({
            dateFormat: "mm/dd/yy",
            timeFormat: "HH:mm:ss",
            showTimezone: false
        });

        datepicker.datetimepicker('setDate', due_date);
    }

    function submitEdit(other_id) {
        var id_sel = $('input#edit-other-'+other_id+'-id');
        var old_id = id_sel.attr('old_value');
        var new_id = id_sel.val();
        var name_sel   = $('input#edit-other-'+other_id+'-name');
        var old_name   = name_sel.attr('old_value');
        var new_name   = name_sel.val();
        var score_sel = $('input#edit-other-'+other_id+'-score');
        var old_score = score_sel.attr('old_value');
        var new_score = score_sel.val();
        var due_sel  = $('input#edit-other-'+other_id+'-due');
        var old_due  = due_sel.attr('old_value');
        var new_due  = due_sel.val();

        var html_response = "";
        var id;
        var name;
        var score;
        var due_date;

        $.ajax('{$BASE_URL}/account/ajax/admin-other.php?course={$_GET['course']}&action=edit', {
            type: 'POST',
            data: {
                oid: other_id,
                id: new_id,
                name: new_name,
                score: new_score,
                due_date: new_due,
                csrf_token: '{$_SESSION['csrf']}'
            }
        })
        .done(function(response) {
            var res_array = response.split("|");
            if (res_array[0] == "success") {
                id = new_id;
                name = new_name;
                score = new_score;
                due_date = new_due;
                window.alert(name+" edited");
            }
            else {
                id = old_id;
                name = old_name;
                score = old_score;
                due_date = old_due;
                window.alert(name+" edit failure");
                console.log(response);
            }
            html_response = "" +
                     "<td class='other-id' id='other-"+other_id+"-id'>"+id+"</td>" +
                     "<td class='other-name' id='other-"+other_id+"-name'>"+name+"</td>" +
                     "<td class='other-score' id='other-"+other_id+"-score'>"+score+"</td>" +
                     "<td class='other-due' id='other-"+other_id+"-due'>"+due_date+"</td>" +
                     "<td class='other-options' id='other-"+other_id+"-options'><a onclick='editOther("+other_id+");'>Edit</a> | "+
                     "<a onclick='deleteOther("+other_id+");'>Delete</a></td>" +
                     "";
            $('tr#other-'+other_id+'-row').html(html_response);
        })
        .fail(function() {
            html_response = "" +
                     "<td class='other-id' id='other-"+other_id+"-id'>"+old_id+"</td>" +
                     "<td class='other-name' id='other-"+other_id+"-name'>"+old_name+"</td>" +
                     "<td class='other-score' id='other-"+other_id+"-score'>"+old_score+"</td>" +
                     "<td class='other-due' id='other-"+other_id+"-due'>"+old_due+"</td>" +
                     "<td class='other-options' id='other-"+other_id+"-options'><a onclick='editOther("+other_id+");'>Edit</a> | "+
                     "<a onclick='deleteOther("+other_id+");'>Delete</a></td>";
            $('tr#other-'+other_id).html(html_response);
            window.alert("[ERROR] Refresh Page");
            console.log(response);
        });
    }

    function cancelEdit(other_id) {
        var old_id = $('input#edit-other-'+other_id+'-id').attr('old_value');
        var old_name   = $('input#edit-other-'+other_id+'-name').attr('old_value');
        var old_score  = $('input#edit-other-'+other_id+'-score').attr('old_value');
        var old_due  = $('input#edit-other-'+other_id+'-due').attr('old_value');

        var html_response = "" +
             "<td class='other-id' id='other-"+other_id+"-id'>"+old_id+"</td>" +
             "<td class='other-name' id='other-"+other_id+"-name'>"+old_name+"</td>" +
             "<td class='other-score' id='other-"+other_id+"-score'>"+old_score+"</td>" +
             "<td class='other-due' id='other-"+other_id+"-due'>"+old_due+"</td>" +
             "<td class='other-options' id='other-"+other_id+"-options'><a onclick='editOther("+other_id+");'>Edit</a> | "+
             "<a onclick='deleteOther("+other_id+");'>Delete</a></td>";
        $('tr#other-'+other_id+'-row').html(html_response);
    }
</script>
<div id="container-other">
    <div class="modal-header">
        <h3 id="myModalOtherel">Manage other</h3>
    </div>
    <table id="table-other">
        <tr>
            <td class="other-id">ID</td>
            <td class="other-name">Name</td>
            <td class="other-score">Max Score</td>
            <td class="other-due">Due Date</td>
            <td >Options</td>
        </tr>
HTML;

foreach ($db->rows() as $other) {
    $temp = explode(" ", $other['other_due_date']);
    $temp[0] = explode("-", $temp[0]);
    $due_date = $temp[0][1]."/".$temp[0][2]."/".$temp[0][0]." ".$temp[1];
    $output .= <<<HTML
        <tr id='other-{$other['oid']}-row'>
            <td class="other-id" id="other-{$other['oid']}-id">{$other['other_id']}</td>
            <td class="other-name" id="other-{$other['oid']}-name">{$other['other_name']}</td>
            <td class="other-score" id="other-{$other['oid']}-score">{$other['other_score']}</td>
            <td class="other-due" id="other-{$other['oid']}-due">{$due_date}</td>
            <td class="other-options" id="other-{$other['oid']}-options"><a onclick="editOther({$other['oid']})">Edit</a> |
            <a onclick="deleteOther({$other['oid']});">Delete</a></td>
        </tr>
HTML;
}

$output .= <<<HTML
    </table><br />
    <span style="float:left;margin-left:10px;margin-top:5px;margin-right:5px;">New:</span>
    <input style="width: 200px" type="text" size="10" id="new-other-id" placeholder="ID" />&nbsp;
    <input style="width: 200px" type="text" size="10" id='new-other-name' placeholder="Name" />&nbsp;
    <input style="width: 70px" type="text" size="70" id='new-other-score' placeholder="Score" /> &nbsp;
    <input style="cursor: auto; width: 250px" type="text" size="70" id="new-other-due" class="datepicker" placeholder="Due Date" /> &nbsp;
    <span style="float:right;margin-right: 200px">
        <input class="btn btn-primary" onclick="createOther();" type="submit" value="Create New"/>
    </span>
</div>
<script>
var datepicker = $('.datepicker');
datepicker.datetimepicker({
    dateFormat: "mm/dd/yy",
    timeFormat: "HH:mm:ss",
    showTimezone: false
});
datepicker.datetimepicker('setDate', new Date());
</script>
HTML;

// <input name="date_submit" class="datepicker" type="text" style="cursor: auto; background-color: #FFF; width: 250px;">
print $output;

include "../footer.php";
<?php
include "../header.php";

check_administrator();

echo <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
    <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
        <form onsubmit="submitAJAX('ajax/admin-hw-report.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id='); return false;">
            <div class="modal-header">
                <h3 id="myModalLabel">Generate Homework Report</h3>
            </div>

            <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
                Homework: <select name="gradeable" id='g_id' style="margin-left:5px;">
                    <option value="-1">No Homework</option>
HTML;
                    $params = array();
                    $db->query("SELECT * FROM gradeable AS g INNER JOIN electronic_gradeable AS eg ON g.g_id=eg.g_id ORDER BY eg_submission_due_date ASC", $params);
                    $temp = $db->rows();
                    for($i = 0; $i < count($temp); $i++)
                    {
                        $row = $temp[$i];
                        echo '<option' . ($i == count($temp) -1 ? " selected " : "") . ' value='.$row['g_id'].'>' . $row["g_title"] . '</option>';
                    }
echo <<<HTML
                </select>
                <div class="loading-gif" style="display:none;"><span style="float: left;margin-left: -100px;margin-top: 10px;">Generating...</span><img src="{$BASE_URL}/toolbox/include/custom/img/loading.gif"></div>
            </div>

            <div class="modal-footer">
                <div style="float:left; text-align:left;">
                    <label style="display: inline;"><input type="radio" style="margin-top:0" name="type" value='all' checked="checked" /> Grade all submissions for selected and past homeworks</label><br />
                    <label style="display: inline;"><input type="radio" style="margin-top:0" name="type" value='default' /> Fully grade selected homework and regrade past homeworks)</label><br />
                    <label style="display: inline;"><input type="radio" style="margin-top:0" name="type" value='regrade' /> Only regrade selected and past homeworks (ignore new grades)</label><br />
                </div>
                <div style="width:50%; margin-left: 50%; margin-top: 20px; float: left;">
                    <input class="btn btn-primary" type="submit" value="Generate Homework Reports"/>
                </div>
            </div>
        </form>
    </div>
</div>
<script type="text/javascript">
    function successAJAX() {
        window.alert("Homeworks successfully updated!");
    }

    function failureAJAX() {
        window.alert("Homeworks update failed!");
    }

    function submitAJAX(url)
    {
        $('.loading-gif').css("display","block");

        var gradeble = $('select#g_id').val();
        url = url+gradeable;

        var inp = $('input[name=type]:checked');
        console.log(inp.val());
        if (inp.val() == 'all') {
            url = url + "&all=1";
        }
        else if (inp.val() == 'regrade') {
            url = url + "&regrade=1";
        }

        $.ajax(url, {
		    type: "POST",
		    data: {
                csrf_token: '{$_SESSION['csrf']}'
            }
        })
        .done(function(response) {
            $('.loading-gif').css("display","none");
            if(response == "updated") {
                successAJAX();
            }
            else {
                failureAJAX();
                console.log(response);
            }
        })
        .fail(function() {
            $('.loading-gif').css("display","none");
            window.alert("[SAVE ERROR] Refresh Page");
        });
    }
</script>
HTML;

include "../footer.php";


<?php
include "../header.php";

check_administrator();

echo <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
    <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
        <form onsubmit="submitAJAX('ajax/admin-hw-report.php?course={$_GET['course']}&hw=');return false;">
            <div class="modal-header">
                <h3 id="myModalLabel">Generate Homework Report</h3>
            </div>

            <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
                Homework: <select name="hw" id='hw' style="margin-left:5px;">
                    <option value="-1">No Homework</option>
HTML;
                    $params = array();
                    $db->query("SELECT * FROM rubrics ORDER BY rubric_due_date ASC", $params);
                    $temp = $db->rows();
                    for($i = 0; $i < count($temp); $i++)
                    {
                        $row = $temp[$i];
                        echo '<option' . ($i == count($temp) -1 ? " selected " : "") . ' value='.$row['rubric_id'].'>' . $row["rubric_name"] . '</option>';
                    }
echo <<<HTML
                </select>
                <div class="loading-gif" style="display:none;"><span style="float: left;margin-left: -100px;margin-top: 10px;">Generating...</span><img src="{$BASE_URL}/toolbox/include/custom/img/loading.gif"></div>
            </div>

            <div class="modal-footer">
                <div style="width:50%; float:left; text-align:left;">
                    <label style="display: inline;"><input type="checkbox" style="margin-top:0" name="regrade" id="regrade" /> Only Regrades (ignore new grades)</label><br />
                    <label style="display: inline;"><input type="checkbox" style="margin-top:0" name="all" id="all" /> Grade All Homeworks</label><br />
                    <label style="display: inline;"><input type="checkbox" style="margin-top:0" name="email" id="email" /> Email Homework Results</label>
                </div>
                <div style="width:50%; float:right; margin-top:15px;">
                    <input class="btn btn-primary" type="submit" value="Generate HW Reports"/>
                </div>
            </div>
        </form>
    </div>
</div>
<script type="text/javascript">
    function successAJAX() {
        window.alert("Homeworks successfully updated!");
    }
    function printAJAX(x) {
        window.alert("printing $x");
    }

    function failureAJAX() {
        window.alert("Homeworks update failed!");
    }

    function submitAJAX(url)
    {
        $('.loading-gif').css("display","block");

        var homework = $('select#hw').val();
        url = url+homework;
        if ($('input#regrade').prop('checked') == true) {
            url = url + "&regrade=1";
        }
        if ($('input#all').prop('checked') == true) {
            url = url + "&all=1";
        }
        if ($('input#email').prop('checked') == true) {
            url = url + "&email=1";
        }

        $.ajax(url)
            .done(function(response) {
                $('.loading-gif').css("display","none");
                if(response == "updated")
                {
		    printAJAX("test");
                    successAJAX();
                }
                else
                {
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


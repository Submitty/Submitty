<?php
include "../header.php";

check_administrator();

echo <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
    <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="Grading Done" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
        <form onsubmit="submitAJAX('ajax/admin-hw-report.php?course={$_GET['course']}&semester={$_GET['semester']}'); return false;">
            <div class="modal-header">
                <h3 id="myModalLabel">Generate Homework Report</h3>
	    </div>
            <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
		<p>
		Each time a grader presses "Submit Homework Grade" or "Submit Homework Re-Grade", the homework report for that student for that electronic gradeable will be created or updated.  
		</p>
		<p>By pressing "Generate Homework Reports", you will update all homework reports for all students for all electronic gradeables.  <em>Note: This action should not be necessary, except perhaps to propagate changes to late days that affect the homework status of other electronic gradeables.</em>
		</p>
            </div>

            <div class="modal-footer">
                <div class="loading-gif" style="display:none;"><span style="float: left;margin-left: -100px;margin-top: 10px;">Generating...</span><img src="{$BASE_URL}/toolbox/include/custom/img/loading.gif"></div>
                <div style="width:50%; margin-left: 50%; margin-top: 20px; float: right;">
                    <input class="btn btn-primary" type="submit" value="Generate Homework Reports"/>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    function successAJAX() {
        window.alert("Homeworks successfully updated!");
    }

    function failureAJAX() {
        window.alert("Homeworks update failed!");
    }

    function submitAJAX(url){
        $('.loading-gif').css("display","block");
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

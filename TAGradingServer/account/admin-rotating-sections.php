<?php
include "../header.php";

check_administrator();

echo <<<HTML
<style type="text/css">
    body {
        overflow: scroll;
    }

    #container-grading {
        width: 600px;
        margin: 100px auto;
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
</style>
<div id="container-grading">
    <form action="{$BASE_URL}/account/submit/admin-rotating-sections.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="{$_SESSION['csrf']}" />
        <div class="modal-header">
            <h3 id="myModalLabel">Setup Rotating Sections</h3>
        </div>

        <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
HTML;
if (isset($_GET['update']) && $_GET['update'] == '1') {
    echo "<div style='color:red'>Rotating Sections Updated</div><br />";
}

echo <<<HTML
           Place students in
            <input type="text" name="sections" placeholder="#" style="width:25px" /> rotating sections
                <select name="arrange_type">
                    <option value="random">randomly</option>
                    <option value="alphabetically">alphabetically</option>
                </select>
        </div>

        <div class="modal-footer">
            <div style="width:50%; float:right; margin-top:5px;">
                <input class="btn btn-primary" type="submit" value="Setup Rotating Sections" />
            </div>
        </div>
    </form>
</div>
HTML;

include "../footer.php";


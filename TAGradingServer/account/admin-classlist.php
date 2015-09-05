<?php
include "../header.php";

check_administrator();

if($user_is_administrator)
{
    $account_subpages_unlock = true;
    echo <<<HTML
    <div id="container" style="width:100%; margin-top:40px;">
        <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="classlist" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
            <form action="{$BASE_URL}/account/submit/admin-classlist.php" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h3 id="myModalLabel">Upload Classlist</h3>
                </div>

                <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
HTML;
    if (isset($_GET['update']) && $_GET['update'] == '1') {
        echo "<div style='color:red'>Classlist Updated</div><br />";
    }
    echo <<<HTML
                    <input type="file" name="classlist" id="classlist"><br />
                    What to do with students in DB, but not classlist?
                    <select name="missing_students">
                        <option value="-2">Nothing</option>
                        <option value="-1">Delete</option>
HTML;
    \lib\Database::query("SELECT * FROM sections ORDER BY section_id");
    foreach(\lib\Database::rows() as $section) {
        echo "                        <option value='{$section['section_id']}'>Move to {$section['section_title']}</option>";
    }
    echo <<<HTML
                    </select><br />
                    Ignore students marked manual from above option? <input type="checkbox" name="ignore_manual" checked="checked" />
                </div>

                <div class="modal-footer">
                    <div style="width:50%; float:right; margin-top:5px;">
                        <input class="btn btn-primary" type="submit" value="Upload Classlist" />
                    </div>
                </div>
            </form>
        </div>
    </div>
HTML;

    include "../footer.php";
}
        
        
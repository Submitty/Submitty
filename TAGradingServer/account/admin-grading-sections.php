<?php
include "../header.php";

check_administrator();
\lib\Database::query("SELECT * FROM sections ORDER BY section_id");

echo <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
    <div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="classlist" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
        <form action="{$BASE_URL}/account/submit/admin-grading-sections.php" method="post" enctype="multipart/form-data">
            <div class="modal-header">
                <h3 id="myModalLabel">Setup Grading Sections</h3>
            </div>

            <div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
HTML;
if (isset($_GET['update']) && $_GET['update'] == '1') {
    echo "<div style='color:red'>Grading Sections Updated</div><br />";
}
echo <<<HTML
                <input type="radio" name="type" value="section" checked="checked" /> Setup grading sections by section
HTML;
foreach (\lib\Database::rows() as $section) {
    echo <<<HTML
                <div style="margin-left: 30px">
                    {$section['section_title']}: <input style="width: 25px" type="text" name="section_{$section['section_id']}" value="{$section['section_id']}" />
                </div>
HTML;
}
echo <<<HTML
                <input type="radio" name="type" value="random" /> Place students in
                <input type="text" name="random_number" placeholder="#" style="width:25px" /> grading sections randomly
                <div style="margin-left: 30px">
                    <input type="checkbox" name="random_skip_disabled" value="1" checked="checked" /> Ignore students in disabled sections (place them in own section)
                </div>
            </div>

            <div class="modal-footer">
                <div style="width:50%; float:right; margin-top:5px;">
                    <input class="btn btn-primary" type="submit" value="Setup Grading Sections" />
                </div>
            </div>
        </form>
    </div>
</div>
HTML;

include "../footer.php";


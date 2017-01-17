<?php

include "../header.php";

check_administrator();

$account_subpages_unlock = true;
echo <<<HTML
<div id="container" style="width:100%; margin-top:40px;">
	<div class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="classlist" aria-hidden="false" style="display: block; margin-top:5%; z-index:100;">
		<form action="{$BASE_URL}/account/submit/admin-classlist.php?course={$_GET['course']}&semester={$_GET['semester']}" method="post" enctype="multipart/form-data">
			<input type="hidden" name="csrf_token" value="{$_SESSION['csrf']}" />
			<div class="modal-header">
				<h3 id="myModalLabel">Upload Classlist</h3>
			</div>

			<div class="modal-body" style="padding-top:20px; padding-bottom:20px;">
HTML;
if (isset($_GET['update']) && $_GET['update'] == '1') {
	$updated =  isset($_GET['updated'])  ? intval($_GET['updated'])  : 0;
	$inserted = isset($_GET['inserted']) ? intval($_GET['inserted']) : 0;
	$deleted =  isset($_GET['deleted'])  ? intval($_GET['deleted'])  : 0;
	$moved =    isset($_GET['moved'])    ? intval($_GET['moved'])    : 0;
	echo <<<HTML
				<div style='color:red'>
					Classlist Updated:<br />
					<span style="margin-left: 20px">{$inserted} students added</span><br />
					<span style="margin-left: 20px">{$updated} students updated</span><br />
				</div><br />
HTML;

}
echo <<<HTML
				Upload Classlist: <input type="file" name="classlist" id="classlist"><br />
				Ignore students marked manual in the classlist? <input type="checkbox" name="ignore_manual_1" checked="checked" /><br />
				What to do with students in DB, but not classlist?
				<select name="missing_students">
					<option value="-2">Nothing</option>
					<option value="-1">Delete</option>
HTML;
\lib\Database::query("SELECT * FROM sections_registration ORDER BY sections_registration_id");
foreach(\lib\Database::rows() as $section) {
	echo "                        <option value='{$section['sections_registration_id']}'>Move to Section {$section['sections_registration_id']}</option>";
}
echo <<<HTML
				</select><br />
				Ignore students marked manual from above option? <input type="checkbox" name="ignore_manual_2" checked="checked" />
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

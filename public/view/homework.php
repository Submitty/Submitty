<?php
$course = htmlspecialchars($_GET["course"]);
$semester = htmlspecialchars($_GET["semester"]);

print('<!-- Course Container -->');
require_once("view/".$semester."_".$course."_container.php");
print('<!-- Course CSS -->');
print('<link href="resources/'.$semester."_".$course.'_main.css" rel="stylesheet"></link>');
?>
<link href='http://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic,700italic' rel='stylesheet' type='text/css'>
<link href='http://fonts.googleapis.com/css?family=PT+Sans:700,700italic' rel='stylesheet' type='text/css'>
<link href="resources/bootmin.css" rel="stylesheet"></link>
<link href="resources/badge.css" rel="stylesheet"></link>
<script src="resources/script/main.js"></script>

<!-- DIFF VIEWER STUFF -->
<script src='diff-viewer/jquery.js'></script>
<script src='diff-viewer/underscore.js'></script>
<script src='diff-viewer/highlight.js'></script>
<script src='diff-viewer/diff.js'></script>
<script src='diff-viewer/diff_queue.js'></script>
<link href="diff-viewer/diff.css" rel="stylesheet"></link>
<link href='https://fonts.googleapis.com/css?family=Inconsolata' rel='stylesheet' type='text/css'>
<?php $user = $_SESSION["id"]; ?>

<!-- FUNCTIONS USED BY THE PULL-DOWN MENUS -->
<script type="text/javascript">
function assignment_changed(){

   var php_course = "<?php echo $course; ?>";
   var php_semester = "<?php echo $semester; ?>";
  window.location.href="?semester="+php_semester+"&course="+php_course+"&assignment_id="+document.getElementById('hwlist').value;
}
function version_changed(){
   var php_course = "<?php echo $course; ?>";
   var php_semester = "<?php echo $semester; ?>";
  window.location.href="?semester="+php_semester+"&course="+php_course+"&assignment_id="+document.getElementById('hwlist').value+"&assignment_version="+document.getElementById('versionlist').value;
}
</script>

<!--- IDENTIFY USER & SELECT WHICH HOMEWORK NUMBER -->
<div id="HWsubmission">
	<h2 class="label">Homework Submission for <em> <?php echo $user;?> </em></h2>
	<?php
	if (on_dev_team($user)) {
		echo "<font color=\"ff0000\" size=+5>on dev team</font>";
		echo "<br>the Dev Team = ";
		for ($i=0; $i<count($dev_team); $i++) {
			echo " ".$dev_team[$i];
		}
	}
	?>
	<?php
	$path_front = get_path_front_course($semester,$course);;
	$priority_path = "$path_front/reports/summary_html/".$username."_priority.html";
	if (file_exists($priority_path)){
		$priority_file = file_get_contents($priority_path);
		echo $priority_file;
	}
	?>

	<div class="sub"> <!-- sub -->
		<form class="form_submit" action="">
			<label>Select Lab or Homework:</label>
			<select id="hwlist" name="assignment_id" onchange="assignment_changed();">
				<?php
				for ($i = 0; $i < count($all_assignments); $i++)
				{
					$FLAG = "";
					if ($all_assignments[$i]["released"] != true)
					{
						if (on_dev_team($user)) {
							$FLAG = " NOT RELEASED";
						} else {
							continue;
						}
					}
					echo "<option value=".$all_assignments[$i]["assignment_id"];
					if ($all_assignments[$i]["assignment_id"] == $assignment_id)
					{
						echo " selected";
					}
					echo '>'.$all_assignments[$i]["assignment_name"].$FLAG;
					echo "</option>";
				}
				?>
			</select>
		</form>
	</div> <!-- end sub -->

	<h2 class="label">Assignment: <?php echo $assignment_name;?></h2>

	<div class="panel-body"> <!-- panel-body -->
		<?php
		if ($status && $status != "") {
			echo '  <div class="outer_box">';
			echo '  <h3 class="label2">';
			echo $status;
			echo '  </h3>';
			echo '</div>';
		}
		?>
		<!--- UPLOAD NEW VERSION -->
		<div class="outer_box"> <!-- outer_box -->
			<h3 class="label">Upload New Version</h3>
			<p class="sub">
                <?php require_once("view/".$semester."_".$course."_upload_message.php"); ?>


				</p>
				<form class="form_submit" action="?page=upload&semester=<?php echo $semester?>&course=<?php echo $course?>&assignment_id=<?php echo $assignment_id?>"
					method="post" enctype="multipart/form-data"
					onsubmit="return check_for_upload('<?php echo $assignment_name;?>', '<?php echo $highest_version;?>', '<?php echo $max_submissions;?>')">
					<label for="file" class="label">Select File:</label>
					<input type="file" name="file" id="file" />
					<input type="submit" name="submit" value="Submit File" class="btn btn-primary">
				</form>
		</div> <!-- end outer_box -->

		<!------------------------------------------------------------------------>
		<!-- "IF AT LEAST ONE SUBMISSION... " -->
			<!-- INFO ON ALL VERSIONS -->
			<?php
			if ($assignment_version >= 1)
			{
				?>
				<div class="outer_box">

				<h3 class="label">Review Submissions</h3>

				<!-- ACTIVE SUBMISION INFO -->

				<div class="sub-text"><b>Active  Submission Version #
					<?php echo $submitting_version." of ".$highest_version.": </b> ";
					if ($submitting_version_in_grading_queue)
					{
						echo " is currently being graded.";
					}
					else
					{
						echo "  Score: ".$submitting_version_score;
					}
					?>


				<!-- SELECT A PREVIOUS SUBMISSION -->
					<div class="row">
						<form class="form_submit" action="">
							<label><em>Select Submission Version:</em></label>
							<input type="input" readonly="readonly" name="assignment_id" value="<?php echo $assignment_id;?>" style="display: none">
							<select id="versionlist" name="assignment_version" onchange="version_changed();">
								<?php
								for ($i = 1; $i <= $highest_version; $i++) {
									echo '<option value="'.$i.' " ';
									if ($i == $assignment_version)
									{
										echo 'selected';
									}
									echo ' > ';
									echo 'Version #'.$i;
									echo '&nbsp;&nbsp';
									echo 'Score: ';
									// <!-- FIX ME: INSERT SCORE FOR THIS VERSION... -->
									echo $select_submission_data[$i-1]["score"];
									echo '&nbsp;&nbsp';
									if ($select_submission_data[$i-1]["days_late"] != "")
									{
										echo 'Days Late: ';
										echo $select_submission_data[$i-1]["days_late"];
									}
									if ($i == $submitting_version) {
										echo '&nbsp;&nbsp ACTIVE';
									}
									echo ' </option>';
								}
								?>
							</select>
						</form>
					</div>
					<div>
						<!-- CHANGE ACTIVE VERSION -->
						<?php
						if ($assignment_version != $submitting_version) {
							echo '<a href="?page=update&semester='.$semester.'&course='.$course.'&assignment_id='.$assignment_id.'&assignment_version='.$assignment_version;
							echo 'style="text-align:center;"><input type="submit" class="btn btn-primary" value="Set Version '.$assignment_version.' as Active  Submission Version"></input></a><br><br>';
						}
						?>
					</div>
				</div>
				<!-- <?php
				//$date_submitted = get_submission_time($user,$semester,$course,$assignment_id,$assignment_version);
				//echo "<p><b>Date Submitted = ".$date_submitted."</b></p>";
				?> -->
				<!-- SUBMITTED FILES -->
				<div class="row sub-text">
					<h4>Submitted Files:
                    <?php
                        if ($download_files === true){
                            echo '<a class = "view_file" style="font-weight: 400; margin-left: 8px;" href="?page=viewfile&semester='.$semester.'&course='.$course.'&assignment_id='.$assignment_id.'&assignment_version='.$assignment_version.'&file_name=all">Download All (as zip)</a>';
                        }
                    ?>
                </h4>
                    <?php
						foreach($submitted_files as $file) {
							echo '<div class="box">';
								echo '<p class="file-header">'.$file["name"].' ('.$file["size"].'kb)';
                                if ($download_files === true){
                                    echo '<a class = "view_file" href="?page=viewfile&semester='.$semester.'&course='.$course.'&assignment_id='.$assignment_id.'&assignment_version='.$assignment_version.'&file_name='.$file["name"].'">Download</a>';
                                }
                                else if (strtolower($file["name"]) == "readme.txt"){
                                    echo '<a class = "view_file" href="?page=viewfile&semester='.$semester.'&course='.$course.'&assignment_id='.$assignment_id.'&assignment_version='.$assignment_version.'&file_name='.$file["name"].'">Download</a>';
                                }
                                echo '</p>';
								echo '</div>';
							}
							?>
				</div>
				<?php if ($assignment_version_in_grading_queue) {?>
					<span>Version <?php echo $assignment_version;?> is currently being graded.</span>
					<?php
				}
				else {
					//Box with grades, outputs and diffs
					render("homework_graded_display",array(
						"assignment_message"=>$assignment_message,
						"homework_tests"=>$homework_tests,
						"viewing_version_score"=>$viewing_version_score,
                        "points_visible"=>$points_visible,
                        "view_points"=>$view_points,
                        "view_hidden_points"=>$view_hidden_points,
					));
				} ?>

				<!-- END OF "IS GRADED?" -->
				</div>  <!-- end outer_box -->

				<?php
                if ($ta_grades === true){
    				echo '<div class="outer_box"> <!-- outer_box -->';
    				if ($ta_grade_released == true) {
    					//<!--- TA GRADE -->
    						$path_front = get_path_front_course($semester,$course);;
    						$gradefile_path = "$path_front/reports/$assignment_id/".$username.".txt";
    						if (!file_exists($gradefile_path)) {
    							echo '<h3 class="label2">TA grade not available</h3>';
    						}
    						else
    						{
    							$grade_file = file_get_contents($gradefile_path);
    							echo '<h3 class="label">TA grade</h3>';
    							echo "<em><p>Please see the <a href=\"http://www.cs.rpi.edu/academics/courses/fall14/csci1200/announcements.php\">Announcements</a>
    								page for the curve for this homework.</p></em>";
    								echo "<pre>".$grade_file."</pre>";
    						}
    				}
    				else
    				{
    					echo '<h3 class="label2">TA grades for this homework not released yet</h3>';
    				}
    				//<!-- END OF "IF AT LEAST ONE SUBMISSION... " -->
    				echo "</div> <!-- end outer_box -->";
                }

			}

            if ($grade_summary === true){
    		echo '<div class="outer_box"> <!-- outer_box -->';
    			$path_front = get_path_front_course($semester,$course);;
    			$gradefile_path = "$path_front/reports/summary_html/".$username."_summary.html";
    			if (!file_exists($gradefile_path))
    			{
    				echo '<h3 class="label2">Grade Summary not available</h3>';
    			}
    			else
    			{
    				$grade_file = file_get_contents($gradefile_path);
    				echo $grade_file;
    			}
    		echo "</div> <!-- end outer_box -->";
            }
	echo "</div> <!-- end panel-body -->";
	?>
	<!------------------------------------------------------------------------>
</div> <!-- end HWsubmission -->

</body>
<script>
	function check_for_upload(assignment, versions_used, versions_allowed) {
		versions_used = parseInt(versions_used);
		versions_allowed = parseInt(versions_allowed);
		if (versions_used >= versions_allowed) {
			var message = confirm("Are you sure you want to upload for " + assignment + " ?  You have already used up all of your free submissions (" + versions_used + " / " + versions_allowed + ").  Uploading may result in loss of points.");
			return message;
		}
		return true;
	}
</script>
<script>
	// Go through diff queue and run viewer
	loadDiffQueue();
</script>
<script>
	//Set time between asking server if the homework has been graded
	//Last argument in ms
	//TODO: Set time between server requests (currently at 5 seconds = 5000ms)
	//                                                     (previously at 1 minute = 60000ms)
	<?php if ($assignment_version_in_grading_queue || $submitting_version_in_grading_queue) {?>
		init_refresh_on_update("<?php echo $semester;?>", "<?php echo $course;?>", "<?php echo $assignment_id;?>","<?php echo $assignment_version?>", "<?php echo $submitting_version;?>", "<?php echo !$assignment_version_in_grading_queue;?>", "<?php echo !$submitting_version_in_grading_queue;?>", 5000);
		<?php } ?>
	</script>
</div>

</html>

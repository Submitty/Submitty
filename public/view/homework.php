<?php
print('<!-- Course Container -->');
require_once("view/".$course."_container.php");
print('<!-- Course CSS -->');
print('<link href="resources/'.$course.'_main.css" rel="stylesheet"></link>');
?>
<link href="resources/bootmin.css" rel="stylesheet"></link>
<link href="resources/badge.css" rel="stylesheet"></link>

<script src="resources/script/main.js"></script>

<?php $course = htmlspecialchars($_GET["course"]);?>


<!-- DIFF VIEWER STUFF -->
<script src='diff-viewer/jquery.js'></script>
<script src='diff-viewer/underscore.js'></script>
<script src='diff-viewer/highlight.js'></script>
<script src='diff-viewer/diff.js'></script>
<script src='diff-viewer/diff_queue.js'></script>
<link href="diff-viewer/diff.css" rel="stylesheet"></link>

<link href='https://fonts.googleapis.com/css?family=Inconsolata' rel='stylesheet' type='text/css'>


<!-- FUNCTIONS USED BY THE PULL-DOWN MENUS -->
<script type="text/javascript">
function assignment_changed(){
   var php_course = "<?php echo $course; ?>";
  window.location.href="?course="+php_course+"&assignment_id="+document.getElementById('hwlist').value;
}
function version_changed(){
   var php_course = "<?php echo $course; ?>";
  window.location.href="?course="+php_course+"&assignment_id="+document.getElementById('hwlist').value+"&assignment_version="+document.getElementById('versionlist').value;
}
</script>



<!--- IDENTIFY USER & SELECT WHICH HOMEWORK NUMBER -->
<?php if ($status && $status != "") {?>
    <div class="panel-body">
        <div class="box">
            <h3 style="margin-top:0; margin-bottom:0">
                <?php echo $status; ?>
            </h3>
        </div>
    </div>
<?php } ?>
<h2>Homework Submission for <em> <?php echo $user;?> </em></h2>

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
//if (on_dev_team($user)) {

   //<!--- PRIORITY HELP QUEUE SUMMARY HTML RAINBOW CHART -->
//   echo "<div class=\"panel-body\">";
//   echo "<div class=\"box\">";

   $path_front = get_path_front($course);
   $priority_path = "$path_front/reports/summary_html/".$username."_priority.html";

   if (!file_exists($priority_path)) {
  //    echo "<h3>GRADE SUMMARY not available</h3>";
   } else {
      $priority_file = file_get_contents($priority_path);
      echo $priority_file;
   }

//   echo "</div>";
//   echo "</div>";

//}
?>



<div class="panel-body" style="text-align: left;">
  <span style="new-text"><b>Select Lab or Homework:</b></span>
  <form action="">
    <select id="hwlist" name="assignment_id" onchange="assignment_changed();">
       <?php for ($i = 0; $i < count($all_assignments); $i++) {
            $FLAG = "";

                if ($all_assignments[$i]["released"] != true) {
	           if (on_dev_team($user)) {
		      $FLAG = " NOT RELEASED";
		   } else {
	              continue;
	           }
	        }
       ?>
            <option value="<?php echo $all_assignments[$i]["assignment_id"];?>"
 	    <?php
               if ($all_assignments[$i]["assignment_id"] == $assignment_id) {?> selected <?php }?>><?php echo $all_assignments[$i]["assignment_name"].$FLAG;
            ?>
            </option>
      <?php } ?>
    </select>
  </form>
</div>


<h2>Assignment: <?php echo $assignment_name;?></h2>


<div class="panel-body">


  <!--- UPLOAD NEW VERSION -->
  <div class="box">
    <h3>Upload New Version</h3>
    <p>Prepare your assignment for submission exactly as
      described on the <a href="<?php echo $link_absolute;?>/homework.php">homework submission</a>
      webpage.  By clicking "Submit File" you are confirming that
      you have read, understand, and agree to follow
      the <a href="<?php echo $link_absolute;?>academic_integrity.php">Homework
      Collaboration and Academic Integrity Policy</a> for this course.
    </p>
    <form action="?page=upload&course=<?php echo $course?>&assignment_id=<?php echo $assignment_id?>"
	  method="post" enctype="multipart/form-data" onsubmit="return check_for_upload('<?php echo $assignment_name;?>', '<?php echo $highest_version;?>', '<?php echo $max_submissions;?>')">
      <label for="file" style="margin-right: 5%;"><b>Select File:</b></label>
      <input type="file" name="file" id="file" style="display: inline" />
      <span class="group-btn">
        <input type="submit" name="submit" value="Submit File" class="btn btn-primary" style="margin-top: 10px">
      </span>
    </form>
  </div>






<!------------------------------------------------------------------------>

<!-- "IF AT LEAST ONE SUBMISSION... " -->
<?php if ($assignment_version >= 1) {?>
  <!-- INFO ON ALL VERSIONS -->
  <div class="box">
    <h3>Review Submissions</h3>
	<!-- SELECT A PREVIOUS SUBMISSION -->
    <div class="row">
        <div style="margin-left: 20px">
            <form action="">
              <label>Select Submission Version:</label>
              <input type="input" readonly="readonly" name="assignment_id" value="<?php echo $assignment_id;?>" style="display: none">
              <select id="versionlist" name="assignment_version" onchange="version_changed();">
                <?php for ($i = 1; $i <= $highest_version; $i++) {?>
                  <option value="<?php echo $i;?>" <?php if ($i == $assignment_version) {?> selected <?php }?> >
                    Version #<?php echo $i;?>
                    &nbsp;&nbsp
                    Score:
                    <?php echo $select_submission_data[$i-1]["score"]; ?>
                    &nbsp;&nbsp
                    <?php if ($select_submission_data[$i-1]["days_late"] != "") {?>
                        Days Late:
                        <?php echo $select_submission_data[$i-1]["days_late"]; ?>
                    <?php } ?>
                    <?php if ($i == $submitting_version) { ?>
                        &nbsp;&nbsp
                        ACTIVE
                    <?php } ?>
                  </option>
                <?php }?>
              </select>
            </form>
        </div>

        <div style="margin-left: 20px">
            <!-- CHANGE ACTIVE VERSION -->
            <?php if ($assignment_version != $submitting_version) { ?>
                <a href="?page=update&course=<?php echo $course;?>&assignment_id=<?php echo $assignment_id;?>&assignment_version=<?php echo $assignment_version?>"style="text-align:center;">
                    <input type="submit" class="btn btn-primary" value="Set Version <?php echo $assignment_version;?> as Active Submission Version"></input>
                </a>
                <br><br>
            <?php } ?>
        </div>
    </div><!-- End of row -->

	<!-- SUBMITTED FILES -->
    <div class="row">
        <div style="margin-left: 20px; margin-right: 20px; ">
            <ul class="list-group">
              <li class="list-group-item list-group-item-active">
                Submitted Files
              </li>
              <?php foreach($submitted_files as $file) {?>
                  <li class="list-group-item">
                    <?php echo $file["name"];?> (<?php echo $file["size"];?>kb)
                  </li>
              <?php } ?>
            </ul>
        </div>
    </div>
    <?php if ($assignment_version_in_grading_queue) {?>
        <span>Version <?php echo $assignment_version;?> is currently being graded.</span>
    <?php } else {
        //Box with grades, outputs and diffs
        render("homework_graded_display",array(
            "assignment_message"=>$assignment_message,
            "homework_tests"=>$homework_tests,
            "viewing_version_score"=>$viewing_version_score,
            "points_visible"=>$points_visible
            ));
    } ?>
  </div><!-- End of box -->
</div><!-- End of panel-body -->

<?php

if ($ta_grade_released == true) {

   //<!--- TA GRADE -->
   echo "<div class=\"panel-body\">";
   echo "<div class=\"box\">";

   $path_front = get_path_front($course);

   $gradefile_path = "$path_front/reports/$assignment_id/".$username.".txt";

   if (!file_exists($gradefile_path)) {
      echo "<h3>TA grade not available</h3>";
   } else {
      $grade_file = file_get_contents($gradefile_path);
      echo "<h3>TA grade</h3>";
      echo "<em><p>Please see the <a href=\"http://www.cs.rpi.edu/academics/courses/fall14/csci1200/announcements.php\">Announcements</a>
                page for the curve for this homework.</p></em>";
      echo "<pre>".$grade_file."</pre>";
   }

   echo "</div>";
   echo "</div>";

} else {
  //echo "<h3>TA grades for this homework not released yet</h3>";
}

//<!-- END OF "IF AT LEAST ONE SUBMISSION... " -->
}
 //<?php } ? >


//if (on_dev_team($user)) {

   //<!--- SUMMARY HTML RAINBOW CHART -->
   echo "<div class=\"panel-body\">";
   echo "<div class=\"box\">";

   $path_front = get_path_front($course);
   $gradefile_path = "$path_front/reports/summary_html/".$username."_summary.html";

   if (!file_exists($gradefile_path)) {
      echo "<h3>GRADE SUMMARY not available</h3>";
   } else {
      $grade_file = file_get_contents($gradefile_path);
      echo $grade_file;
   }

   echo "</div>";
   echo "</div>";

//}

?>


<!------------------------------------------------------------------------>

</table>
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
//                                       (previously at 1 minute = 60000ms)
<?php if ($assignment_version_in_grading_queue || $submitting_version_in_grading_queue) {?>
init_refresh_on_update("<?php echo $course;?>", "<?php echo $assignment_id;?>","<?php echo $assignment_version?>", "<?php echo $submitting_version;?>", "<?php echo !$assignment_version_in_grading_queue;?>", "<?php echo !$submitting_version_in_grading_queue;?>", 5000);
<?php } ?>
</script>



</html>

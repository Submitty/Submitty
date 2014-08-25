<?php require_once("../private/view/".$course."_container.php");?>

<link href="resources/bootmin.css" rel="stylesheet"></link>
<link href="resources/main.css" rel="stylesheet"></link>
<script src="resources/script/main.js"></script>

<?php $course =    $course = htmlspecialchars($_GET["course"]); ?>


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

<div class="panel-body" style="text-align: left;">
  <span><b>Select Lab or Homework:</b></span>
  <form action="">
    <select id="hwlist" name="assignment_id" onchange="assignment_changed();">
      <?php for ($i = 0; $i < count($all_assignments); $i++) {?>
            <option value="<?php echo $all_assignments[$i]["assignment_id"];?>" 
		    <?php if ($all_assignments[$i]["assignment_id"] == $assignment_id) {?> selected <?php }?>><?php echo $all_assignments[$i]["assignment_name"];?></option>
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


  <!-- ACTIVE SUBMISION INFO -->
<!--  <div class="box">
    <h3>Active  Submission Version 
    <?php 
       echo $submitting_version."</b>"; 
       if ($submitting_version_in_grading_queue) {
           echo " is currently being graded.";
       } else {
          echo " score: ".$submitting_version_score;
       }
    ?>
    </h3>
    <p><?php echo $highest_version;?> submissions used out of <?php echo $max_submissions;?>.</p>

  </div>
-->

  <!-- INFO ON ALL VERSIONS -->
  <div class="box">
    <h3>Review Submissions</h3>
      
<!--
    <div class="row" style="margin: 0;">
      <div class="col-sm-10" style="padding: 0;">
-->

	<!-- SELECT A PREVIOUS SUBMISSION -->
	<form action="">
          <label>Select Submission Version:</label>
          <input type="input" readonly="readonly" name="assignment_id" value="<?php echo $assignment_id;?>" style="display: none">
          <select id="versionlist" name="assignment_version" onchange="version_changed();">
            <?php for ($i = 1; $i <= $highest_version; $i++) {?>
                  <option value="<?php echo $i;?>" <?php if ($i == $assignment_version) {?> selected <?php }?> >
                    Version #<?php echo $i;?>
		    &nbsp;&nbsp 
		    Score:
		    <!-- FIX ME: INSERT SCORE FOR THIS VERSION... -->
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


        <!-- CHANGE ACTIVE VERSION -->
        <?php if ($assignment_version != $submitting_version) { ?>
	<a href="?page=update&course=<?php echo $course;?>&assignment_id=<?php echo $assignment_id;?>&assignment_version=<?php echo $assignment_version?>" 
	   style="text-align:center;"><input type="submit" class="btn btn-primary" value="Set Version <?php echo $assignment_version;?> as Active Submission Version"></input></a><br><br>
	<?php } ?>


	<!-- SUBMITTED FILES -->
        <ul class="list-group">
          <li class="list-group-item list-group-item-active">
            Submitted Files
          </li>
          <?php foreach($submitted_files as $file) {?>
          <li class="list-group-item">
            <?php echo $file;?>
               </li>
          <?php } ?>
        </ul>
<!--	     
      </div><!-- End Column ~>
<!--  FIXME
      <div class="col-sm-1" style="padding: 0;"></div>
      <!--<<<<<<< HEAD~>
<!--  FIXME
      <div class="col-sm-6" style="padding: 0;">
-->

   <?php if ($assignment_version_in_grading_queue) {?>

        <span>Version <?php echo $assignment_version;?> is currently being graded.</span>

   <?php } else {?>




<!-- DETAILS ON INDIVIDUAL TESTS --> 

  <div class="row" style="margin-left: 10px; margin-right: 10px">
    <div class="box2" style="border-radius: 3px;    padding: 0px;    border: 1px solid #cccccc;    height: 100%;  width: 100%;   margin: 5px; position: relative; float: left;    background:rgba(255,255,255,0.8);">
        <div>
              <h4 style="margin-left: 10px; text-align: left;display:inline-block;">
                    Total
              </h4>
              <span class="badge">
                    <?php echo $viewing_version_score." / ".$points_visible;?>
              </span>
        </div>
    </div>

    <?php $counter = 0;
    foreach($homework_tests as $test) {?>
    <br clear="all">

    <div class="box2" style="border-radius: 3px;    padding: 0px;    border: 1px solid #cccccc;    height: 100%;  width: 100%;   margin: 5px; position: relative; float: left;    background:rgba(255,255,255,0.8);">
      <?php //score, points, and points possible are set.  Is not hidden and is not extra credit
        if (isset($test["score"]) && isset($test["points_possible"]) && $test["points_possible"] != 0 && 
            ($test["is_hidden"] === false || $test["is_hidden"] == "false" || $test["is_hidden"] == "False") && 
            ($test["is_extra_credit"] === false || $test["is_extra_credit"] === "false" || $test["is_extra_credit"] === "False")   
        ) {
                if (!($test["points_possible"] > 0)) {
                   $part_percent = 1;
                } else {
                   $part_percent = $test["score"] / $test["points_possible"];
                }
                if ($part_percent == 1) {
                   $class = "badge green-background";
                } else if ($part_percent >= 0.5) {
                   $class = "badge yellow-background";
                } else {
                   $class = "badge red-background";
                }
          } else {
                $class = "badge";
          } ?>
            
    <div>
      <h4 style="margin-left: 10px; text-align: left;display:inline-block;">
        <?php echo $test["title"];?>
      </h4>
      <!-- BADGE TEST SCORE -->
      <span class="<?php echo $class;?>">
        <?php 
            if ($test["is_hidden"] === true || $test["is_hidden"] == "true" || $test["is_hidden"] == "True") {?>
                Hidden Test Case
                </span>
                </div><!-- End div -->
                </div><!-- End Box2 -->
                <?php continue;
            }?>
            <?php echo $test["score"]." / ".$test["points_possible"];
            if ($test["is_extra_credit"] === true || $test["is_extra_credit"] === "true" || $test["is_extra_credit"] === "True") {
                echo " Extra Credit";
            }
        ?>
      </span>
      <?php if ((isset($test["diff"]) && $test["diff"] != "") || (isset($test["diffs"]) && count($test["diffs"]) > 0)) {?>
          <span>
            <a href="#" onclick="return toggleDiv('sidebysidediff<?php echo $counter;?>');">Details</a>
          </span>
      <?php }?>
      <?php if ($test["message"] != "") {?>
      <!--<div>-->
      <!--<span>&nbsp;&nbsp;-->&nbsp;&nbsp;&nbsp;<em><?php echo $test["message"]; ?></em><!--</span>-->
      <!--</div>-->
      <?php } ?>
    </div>
    <div id="sidebysidediff<?php echo $counter;?>" style="display:none">

      <?php if (isset($test["compilation_output"]) && $test["compilation_output"] != ""){?>
      <b>Compilation output:</b>
      <pre><?php echo $test["compilation_output"]; ?></pre>
      <?php }?>

<!--
      <?php echo $test["diff"]["student"]; ?>
      <?php echo $test["diff"]["instructor"]; ?>
      <?php echo $test["diff"]["difference"]; ?>
-->


      <!-- SIDE BY SIDE INDIVIDUAL DIFF -->
      <?php if ($test["diff"] != ""){?>
      <?php if (isset($test["message"]) && $test["message"] != "") {?>
            <div>
              <em><?php echo $test["message"];?></em>
            </div>
        <?php }?>
           <!-- STUDENT INSTRUCTOR OUTPUT -->
	   <div class="col-md-6">
             <div class="panel panel-default" id="<?php echo $test["title"]; ?>_student">
	       <?php echo str_replace(" ", "&nbsp;", $test["diff"]["student"]); ?>
	        </div>
	   </div>
	   <!-- INSTRUCTOR OUTPUT -->
	   <div class="col-md-6">
             <div class="panel panel-default" id="<?php echo $test["title"]; ?>_instructor">
	       <?php echo str_replace(" ", "&nbsp;", $test["diff"]["instructor"]); ?>
	        </div>
	   </div>
	   <script>
             diff_queue.push("<?php echo $test["title"]; ?>");
             diff_objects["<?php echo $test["title"]; ?>"] = <?php echo $test["diff"]["difference"]; ?>;
	   </script>
      <!-- end if -->
      <?php }?>
      <!-- END SIDE BY SIDE INDIVIDUAL DIFF -->
      <!-- MULTIPLE DIFFS -->
      <?php foreach ($test["diffs"] as $diff) {?>

      <span>
      <?php if (isset($diff["description"]) && $diff["description"] != "") {?>
      <b><?php echo $diff["description"];?></b>
      <?php } ?>

        <?php if (isset($diff["message"]) && $diff["message"] != "") {?>
      <!--  <br> 
            <span>&nbsp;&nbsp;-->&nbsp;&nbsp;&nbsp;<em><?php echo $diff["message"]; ?></em><!--</span>-->
        <?php }?>
      </span>
        <div class="row">
            <div class="col-md-6">


	      <!-- WARNING spaces/newlines in the inner divs are critical -->
	      <!-- FIXME, should get rid of the newline after the div
	           open and get rid of the "+1" on the line index in
	           the diff.js file -->
                <div class="panel panel-default" id="<?php echo $diff["diff_id"]; ?>_student">
<?php echo str_replace(" ", "&nbsp;", $diff["student"]); ?></div>
            </div>
            <div class="col-md-6">
                <div class="panel panel-default" id="<?php echo $diff["diff_id"]; ?>_instructor">
<?php echo str_replace(" ", "&nbsp;", $diff["instructor"]); ?></div>
            </div>
            <script>
                diff_queue.push("<?php echo $diff["diff_id"]; ?>");
                diff_objects["<?php echo $diff["diff_id"]; ?>"] = <?php echo $diff["difference"]; ?>;
            </script>
        </div>
        <?php } ?>
        <!-- END MULTIPLE DIFFS -->


    </div><!-- end sidebysidediff# -->
      <?php $counter++;?>
   </div><!-- end box2 -->
   <?php }?><!-- end foreach homework_tests as test-->
   

   <!-- END OF "IS GRADED?" -->    
   <?php } ?>

  </div>

<!-- END OF "IF AT LEAST ONE SUBMISSION... " -->
<?php } ?>

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

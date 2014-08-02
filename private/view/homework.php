<?php require_once("../private/view/".$course."_container.php");?>

<link href="resources/bootmin.css" rel="stylesheet"></link>
<link href="resources/main.css" rel="stylesheet"></link>

<?php $course =    $course = htmlspecialchars($_GET["course"]); ?>


<!-- DIFF VIEWER STUFF -->
<script src='diff-viewer/jquery.js'></script>
<script src='diff-viewer/underscore.js'></script>
<script src='diff-viewer/highlight.js'></script>
<script src='diff-viewer/diff.js'></script>
<script src='diff-viewer/diff_queue.js'></script>
<link href="diff-viewer/diff.css" rel="stylesheet"></link>

<link href='https://fonts.googleapis.com/css?family=Inconsolata' rel='stylesheet' type='text/css'>

<?php if ($points_possible == 0) {
    $percent = 0;
} else {
    $percent = (int)($points_received * 100 / $points_possible);
}
?>



<?php $user = $_SESSION["id"]; ?>


<script type="text/javascript">
function assignment_changed(){
   var php_course = "<?php echo $course; ?>";
<!--  window.location.href="?assignment_id="+document.getElementById('hwlist').value;-->
  window.location.href="?course="+php_course+"&assignment_id="+document.getElementById('hwlist').value;
}
function version_changed(){
<!--  window.location.href="?assignment_version="+document.getElementById('assignmentlist').value;-->
   var php_course = "<?php echo $course; ?>";
  window.location.href="?course="+php_course+"&assignment_id="+document.getElementById('hwlist').value+"&assignment_version="+document.getElementById('versionlist').value;
}
</script>

<!--<td class=main_panel valign=top height=100%>--> 

<!--    <div class="panel panel-default" style="max-width:none">-->

<!--        <div class="panel-body">--><!-- Panel Body Summary -->


	  <!--<h2 style="float: left; margin-left: 10px;">Homework Submission for <em> <?php echo $user;?> </em></h2>-->
     <h2>Homework Submission for <em> <?php echo $user;?> </em></h2>

            <div class="panel-body" style="text-align: left;"> <!-- Body homework select -->

<!--<h2 style="float: left; margin-left: 10px;">Welcome <?php echo $assignment_name;?></h2>-->

<!--<h2>Select Homework</h2>-->

                <span><b>Select Lab or Homework:</b></span>
                <form action="">
                    <select id="hwlist" name="assignment_id" onchange="assignment_changed();">
                    <?php for ($i = 0; $i < count($all_assignments); $i++) {?>
                        <option value="<?php echo $all_assignments[$i]["assignment_id"];?>" <?php if ($all_assignments[$i]["assignment_id"] == $assignment_id) {?> selected <?php }?>><?php echo $all_assignments[$i]["assignment_name"];?></option>
                    <?php } ?>
                    </select>
                    <!-- <input type="submit" value="Go">-->
                </form>
            </div><!-- End Homework Select -->


<h2>Assignment: <?php echo $assignment_name;?></h2>



            <div class="panel-body">
                <div class="box">
<h3>Upload New Version</h3>
                <p>Prepare your assignment for submission exactly as described on the <a href="<?php echo $link_absolute;?>/homework.php">homework submission</a> webpage.
<!--		</p>
		<p>-->
		  By clicking "Send File" you are confirming that you have read, understand, and 
                  agree to follow the <a href="<?php echo $link_absolute;?>academic_integrity.php">Homework Collaboration and Academic Integrity Policy</a> for this course.
		</p>

                <form action="?page=upload&course=<?php echo $course?>&assignment_id=<?php echo $assignment_id?>" 
		      method="post" enctype="multipart/form-data" 
                      onsubmit=" return check_for_upload('<?php echo $assignment_name;?>', '<?php echo $highest_version;?>', '<?php echo $max_submissions;?>');">
                    <label for="file" style="margin-right: 5%;"><b>Select File:</b></label>
                    <input type="file" name="file" id="file" style="display: inline" />
                    <span class="group-btn">
                        <input type="submit" name="submit" value="Send File" class="btn btn-primary" style="margin-top: 10px">
                    </span>
                </form>
                </div>
            </div><!-- End Upload New Homework -->





            <div class="panel-body"><!-- Summary Table -->
                <div class="box">
<h3>Review Previous Submissions</h3>
<!--		  <h3><?php echo $assignment_name." Version ".$submitting_version." ".$user; ?></h3>-->

                    <div class="row" style="margin: 0;">
                        <div class="col-sm-10" style="padding: 0;">
<!--                          <span>Summary:</span>-->
                            <?php if ($assignment_version >= 1) {?>
                                <?php if ($submitting_version_in_grading_queue) {?>
                                    <br><span><b>Active Submission Version: <?php echo $submitting_version;?></b>. 
                                                 It is currently being graded.
                                <?php } else {?>
                                    <br><span><b>Active Submission Version: <?php echo $submitting_version;?></b>. 
                                                 Automated grading score: <b><?php echo $submitting_version_score;?></b>
                                <?php } ?>
                                <br><br>
                                <div class="row">
                                    <div style="float: left; margin-left: 15px;">
                                        <form action="">
                                            <label>Select Submission Version:</label>
                                            <input type="input" readonly="readonly" name="assignment_id" value="<?php echo $assignment_id;?>" style="display: none">
                                            <select id="versionlist" name="assignment_version" onchange="version_changed();">
                                                <?php for ($i = 1; $i <= $highest_version; $i++) {?>
                                                    <option value="<?php echo $i;?>" <?php if ($i == $assignment_version) {?> selected <?php }?>>
                                                                Version <?php echo $i;?></option>
                                                <?php }?>
                                            </select>
                                    <!--        <input type="submit" value="Go">-->
                                        </form>
                            <?php if ($assignment_version_in_grading_queue) {?>
                                <span>Version <?php echo $assignment_version;?> is currently being graded.</span><br><br>
                            <?php } ?>

					  <?php if ($assignment_version != $submitting_version) { ?>

                                        <a href="?page=update&course=<?php echo $course;?>&assignment_id=<?php echo $assignment_id;?>&assignment_version=<?php echo $assignment_version?>" 
                                                             style="text-align:center;"><input type="submit" class="btn btn-primary" value="Set Version <?php echo $assignment_version;?> as Active Submission Version"></input></a><br><br>
					  <?php } ?>
                                    </div>
                                </div><!-- End Row -->
                            <?php } else {?>
                                <br><span>You have not submitted anything for this assignment!</span>
                            <?php }?>
                    </div><!-- End Column -->
                    <div class="col-sm-1" style="padding: 0;"></div>
                </div><!-- End Row -->
                    <div class="row" style="padding: 0;">
                        <div class="col-sm-6">
                            <?php if (!$assignment_version_in_grading_queue) {?>
<!--			         not in grading queue<br>-->

<!--                                    <?php echo "highest_version ".$highest_version."<br>";  ?>-->

<!--                                    <?php echo "version_results ".var_dump($version_results)."<br>";  ?>-->

<!--
                                    <?php echo "username ".$username."<br>";  ?>


                                    <?php echo "points_received ".$points_received."<br>";  ?>
                                    <?php echo "points_possible ".$points_possible."<br>";  ?>

                                    <?php echo "count(homework_tests) ".count($homework_tests)."<br>";  ?>

				    <?php echo "count(testcases_results) ".count($testcases_results)."<br>";  ?>
				    <?php echo "count(testcases_info) ".count($testcases_info)."<br>";  ?>
-->

<!--                                    <?php foreach($homework_summary as $item) { echo "hi".$item["title"]."<br>"; } ?> 
-->

			    

                                <ul class="list-group">
                                    <?php foreach($homework_summary as $item) {?>

                                        <?php if (isset($item["score"]) && isset($item["points_possible"]) && $item["points_possible"] != 0) {
                                            if (!($item["points_possible"] > 0)) {
                                                $part_percent = 1;
                                            } else {
                                                $part_percent = $item["score"] / $item["points_possible"];
                                            }
                                            if ($part_percent == 1) {
                                                $class = "";
                                            } else if ($part_percent >= 0.5) {
                                                $class = " list-group-item-warning";
                                            } else {
                                                $class = " list-group-item-danger";
                                            }
                                        } else {
                                            $class = "";
                                        }?>
                                      <li class="list-group-item <?php echo $class;?>">
                                          <span class="badge">
                                            <?php if (isset($item["score"])) {
                                                echo $item["score"];
                                                if (isset($item["points_possible"])) {
                                                    echo "/".$item["points_possible"];
                                                }
                                            } else if (isset($item["value"])) {
                                                echo $item["value"];
                                            }
                                            ?>
                                          </span>
                                          <?php echo $item["title"];?>
                                      </li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                            </div>
                            <div class="col-sm-6">
                            <div class="box">
                            <ul class="list-group">
                                <li class="list-group-item list-group-item-heading" style="text-decoration:underline">
                                    Submitted Files
                                </li>
                                <?php foreach($submitted_files as $file) {?>
                                    <li class="list-group-item">
                                        <?php echo $file;?>
                                    </li>
                                <?php } ?>
                            </ul>
                            </div>
                        </div><!-- End Column -->
                    </div><!-- End Row -->
                </div><!-- End Box -->
            </div><!-- End Summary Table -->




            <?php if (isset($TA_grade) && $TA_grade) {?>
            <div class="panel-body" style="text-align: right"><!-- TA Grade -->
                <button type="button">Show TA Grade</button>
                <div id="TA-grade">
                </div>
            </div>
            <?php }?>
        </div><!-- Ends Panel Body Summary -->
    </div><!-- Ends panel-default -->
    <div class="panel panel-default"><!-- Homework Output Compare And Diff -->
        <div class="row" style="margin-left: 10px; margin-right: 10px">
            <?php foreach($homework_tests as $test) {?>

<!--
	    A TEST! title '<?php echo $test["title"] ?>' <br>
	    A TEST! message '<?php echo $test["message"] ?>' <br>
	    A TEST! diff '<?php echo $test["diff"] ?>' <br>
-->

<br clear="all">

<!--<h2>DIFF</h2>-->

                <?php if (isset($test["score"]) && isset($test["points_possible"]) && $test["points_possible"] != 0) {
                    if (!($test["points_possible"] > 0)) {
                        $part_percent = 1;
                    } else {
                        $part_percent = $test["score"] / $test["points_possible"];
                    }
                    if ($part_percent == 1) {
                        $class = "badge alert-success";
                    } else if ($part_percent >= 0.5) {
                        $class = "badge alert-warning";
                    } else {
                        $class = "badge alert-danger";
                    }
                } else {
                    $class = "badge";
                }?>
                <div>
                    <h4 style="margin-left: 10px; text-align: left;display:inline-block;">
                        <?php echo $test["title"];?>
                    </h4>
                    <span class="<?php echo $class;?>">
                        <?php echo $test["score"]."/".$test["points_possible"];?>
                    </span>
                    <?php if ($test["message"] != ""){?>
<br>
		    <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<em><?php echo $test["message"]; ?></em></span>
		    <?php }?>
                </div>
                
                <?php if ($test["diff"] != ""){?>
                <div class="col-md-6">
                    <div class="panel panel-default" id="<?php echo $test["title"]; ?>_student">
<?php echo str_replace(" ", "&nbsp;", $test["diff"]["student"]); ?>
</div>
<!--<?php echo $test["diff"]["student"]; ?>-->
                </div>
                <div class="col-md-6">
                    <div class="panel panel-default" id="<?php echo $test["title"]; ?>_instructor">
<?php echo str_replace(" ", "&nbsp;", $test["diff"]["instructor"]); ?>
</div>
<!--<?php echo $test["diff"]["instructor"]; ?>-->
                </div>

<!--MYDIFF
<?php  echo $test["diff"]["difference"]; ?>
-->


                <script>
                diff_queue.push("<?php echo $test["title"]; ?>");
                diff_objects["<?php echo $test["title"]; ?>"] = <?php echo $test["diff"]["difference"]; ?>;
                </script>

                <?php }?>
                
            <?php } ?>
        </div>
<!--    </div>--><!-- End Homework Output Compare And Diff -->
<!--</div>--><!-- End Col Blog-Main -->




</table>
</body>
<?php if (strlen($error) > 0) {?>
    <script>
        alert("<?php echo $error;?>");
    </script>
<?php }?>
<script>
function check_for_upload(assignment, versions_used, versions_allowed) {
    versions_used = parseInt(versions_used);
    versions_allowed = parseInt(versions_allowed);
    if (versions_used < versions_allowed) {
        var message = confirm("Are you sure you want to upload for " + assignment + " ?  You have used " + versions_used + " / " + versions_allowed);
    } else {
        var message = confirm("Are you sure you want to upload for " + assignment + " ?  You have used all free uploads.  Uploading may result in a deduction of points.");
    }
    if (message == true) {
        return true;
    } else {
        return false;
    }
}
</script>
<script>
// Go through diff queue and run viewer
loadDiffQueue();
</script>
</html>

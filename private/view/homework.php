<!--link href="resources/bootstrap/css/bootstrap.min.css" rel="stylesheet"-->
<link href="resources/bootmin.css" rel="stylesheet"></link>
<link href="resources/main.css" rel="stylesheet"></link>

<!-- DIFF VIEWER STUFF -->
<script src='diff-viewer/jquery.js'></script>
<script src='diff-viewer/underscore.js'></script>
<script src='diff-viewer/highlight.js'></script>
<script src='diff-viewer/diff.js'></script>
<script src='diff-viewer/diff_queue.js'></script>
<link href="diff-viewer/diff.css" rel="stylesheet"></link>

<?php if ($points_possible == 0) {
    $pecent = 0;
} else {
    $percent = (int)($points_received * 100 / $points_possible);
}
?>


<?php require_once("../private/view/nav_container.php");?>


<td class=main_panel valign=top height=100%>
    <div class="panel panel-default" style="max-width:none">
        <div class="panel-body"><!-- Panel Body Summary -->
            <h2><?php echo $assignment_name;?></h2>
            <div class="panel-body" style="text-align: right;"> <!-- Body homework select -->
                <span>Select Homework</span>
                <form action="">
                    <select name="assignment_id">
                    <?php for ($i = 0; $i < count($all_assignments); $i++) {?>
                        <option value="<?php echo $all_assignments[$i]["assignment_id"];?>" <?php if ($all_assignments[$i]["assignment_id"] == $assignment_id) {?> selected <?php }?>><?php echo $all_assignments[$i]["assignment_name"];?></option>
                    <?php } ?>
                    </select>
                    <input type="submit" value="Go">
                </form>
            </div><!-- End Homework Select -->
            <div class="panel-body">
                <p>When you have completed your programming assignment, prepare your assignment 
                for submission exactly as described on the <a href="<?php echo $link_absolute;?>/homework.php">homework submission</a> webpage.</p>
                <p>1) Select the homework assignment to edit and click Go.<br>2) Upload by choosing a file and clicking Send File.<br>3) Update the assignment version to use for grading by selecting the version, clicking Go, and then clicking Use Version X.</p>
            </div>

            <div class="panel-body"><!-- Summary Table -->
                <div class="col-sm-6" style="padding: 0;">
                    <div class="box" style="">
                        <span>Summary:</span>
                        <?php if ($assignment_version >= 1) {?>
                            <?php if ($submitting_version_in_grading_queue) {?>
                                <br><span>You currently are submitting <b>Version <?php echo $submitting_version;?></b>. It is currently being graded.
                            <?php } else {?>
                                <br><span>You currently are submitting <b>Version <?php echo $submitting_version;?></b> with a score of <b><?php echo $submitting_version_score;?></b>
                            <?php } ?>
                            <br><br>
                            <span>Select Version: </span>
                            <form action="index.php">
                                <input type="input" readonly="readonly" name="assignment_id" value="<?php echo $assignment_id;?>" style="display: none">
         
                                <select name="assignment_version">
                                    <?php for ($i = 1; $i <= $highest_version; $i++) {?>
                                        <option value="<?php echo $i;?>" <?php if ($i == $assignment_version) {?> selected <?php }?>>Version <?php echo $i;?></option>
                                    <?php }?>
                                </select>
                                <input type="submit" value="Go">
                            </form>
                            <a href="?page=update&assignment_id=<?php echo $assignment_id;?>&assignment_version=<?php echo $assignment_version?>" style="text-align:center;">
                                <input type="submit" class="btn btn-primary" value="Use Version <?php echo $assignment_version;?>"></input>
                            </a>
                        <?php } else {?>
                            <br><span>You have not submitted anything for this assignment!</span>
                        <?php }?>
                    </div><!-- End Box -->
                </div>
                <div class="col-sm-6" style="padding: 0;">
                    <div class="box" style="">
                        <?php if ($assignment_version_in_grading_queue) {?>
                            <span>Version <?php echo $assignment_version;?> is currently being graded.</span>
                        <?php } else {?>
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
                        <?php }?>
                    </div><!-- End Box -->
                </div>
            </div><!-- End Summary Table -->
            <div class="panel-body" style="text-align: right"><!-- Upload New Homework -->
                <div class="box">
                <p style="text-align: left">By clicking "Send File" you are confirming that you have read, understand, and 
                agree to follow the <a href="<?php echo $link_absolute;?>academic_integrity.php">Homework Collaboration and Academic Integrity Policy</a> for this course.</p>
                <form action="?page=upload&assignment_id=<?php echo $assignment_id?>" method="post" enctype="multipart/form-data" 
                onsubmit=" return check_for_upload('<?php echo $assignment_name;?>', '<?php echo $highest_version;?>', '<?php echo $max_submissions;?>');">
                    <label for="file" style="margin-right: 5%;">Filename:</label>
                    <input type="file" name="file" id="file" style="display: inline" />
                    <span class="group-btn">
                        <input type="submit" name="submit" value="Send File" class="btn btn-primary" style="margin-top: 10px">
                    </span>
                </form>
                </div>
            </div><!-- End Upload New Homework -->
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
                    <?php echo $test["message"]; ?>
                    <span class="<?php echo $class;?>">
                        <?php echo $test["score"]."/".$test["points_possible"];?>
                    </span>
                </div>
                
                <?php if ($test["diff"] != ""){?>
                <div class="col-md-6">
                    <div class="panel panel-default" id="<?php echo $test["title"]; ?>_student">
                        <?php echo $test["diff"]["student"]; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="panel panel-default" id="<?php echo $test["title"]; ?>_instructor">
                        <?php echo $test["diff"]["instructor"]; ?>
                    </div>
                </div>
                <script>
                diff_queue.push("<?php echo $test["title"]; ?>");
                diff_objects["<?php echo $test["title"]; ?>"] = <?php echo $test["diff"]["difference"]; ?>;
                </script>
                <?php }?>
                
            <?php } ?>
        </div>
    </div><!-- End Homework Output Compare And Diff -->
</div><!-- End Col Blog-Main -->




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

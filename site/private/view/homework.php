<!--link href="resources/bootstrap/css/bootstrap.min.css" rel="stylesheet"-->
<link href="resources/bootmin.css" rel="stylesheet"></link>
<link href="resources/main.css" rel="stylesheet"></link>


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
            <h2><?php echo $assignment.' '.$homework_number;?></h2>
            <div class="panel-body" style="text-align: right;"> <!-- Body homework select -->
                <span>Select Homework</span>
                <form action="">
                    <input type="input" readonly="readonly" name="page" value="homework" style="display: none">
                    <select name="arraynumber">
                    <?php for ($i = 0; $i < count($all_assignments); $i++) {?>
                        <option value="<?php echo $i;?>" <?php if ($all_assignments[$i]["assignment"] == $assignment && $all_assignments[$i]["number"] == $homework_number) {?> selected <?php }?>><?php echo $all_assignments[$i]["assignment"].' '.$all_assignments[$i]["number"];?></option>
                    <?php } ?>
                    </select>
                    <input type="submit" value="Go">
                </form>
            </div><!-- End Homework Select -->
            <div class="row"><!-- Summary Table -->
                <div class="col-sm-4">
                    <span>Summary:</span>
                    <?php if ($version_number >= 0) {?>
                    <br><span>You currently are submitting <b>Version 1</b> with a score of <b>11/15</b><br><br>
                    <span>Select Version: </span>
                    <form action="index.php">
                        <input type="input" readonly="readonly" name="page" value="homework" style="display: none">
                        <input type="input" readonly="readonly" name="assignment" value="<?php echo $assignment;?>" style="display: none">
                        <input type="input" readonly="readonly" name="number" value="<?php echo $homework_number;?>" style="display: none">
 
                        <select name="version">
                            <?php for ($i = 1; $i <= $max_version_number; $i++) {?>
                                <option value="<?php echo $i;?>" <?php if ($i == $version_number) {?> selected <?php }?>>Version <?php echo $i;?></option>
                            <?php }?>
                        </select>
                        <input type="submit" value="Go">
                    </form>
                    <br>
                    <br>
                    <br>
                    <form action="" style="text-align:center;">
                    <input type="submit" class="btn btn-primary" value="Submit using Version <?php echo $version_number;?>"></input>
                    </form>
                    <?php }?>
                </div>
                <div class="col-sm-7">
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
                </div>
            </div><!-- End Summary Table -->
            <div class="panel-body" style="text-align: right"><!-- Upload New Homework -->
                <form action="?page=upload&number=<?php echo $homework_number;?>&assignment=<?php echo$assignment;?>" method="post" enctype="multipart/form-data" 
                onsubmit=" return check_for_upload('<?php echo $assignment." ".$homework_number?>', '<?php echo $max_version_number;?>', '<?php echo $max_submissions;?>');">
                    <label for="file" style="margin-right: 5%;">Filename:</label>
                    <input type="file" name="file" id="file" style="display: inline" />
                    <span class="group-btn">
                        <input type="submit" name="submit" value="Submit" class="btn btn-primary" style="margin-top: 10px">
                    </span>
                </form>
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
                <h4 style="margin-left: 10px; text-align: left;"><?php echo $test["title"];?> <span class="<?php echo $class;?>"><?php echo $test["score"]."/".$test["points_possible"];?></span></h4>
                <div class="col-md-6">
                    <div class="panel panel-default">
                        My output
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="panel panel-default">
                        Teacher output
                    </div>
                </div>
                
            <?php } ?>
        </div>
    </div><!-- End Homework Output Compare And Diff -->
</div><!-- End Col Blog-Main -->




</table>
</body>
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
</html>

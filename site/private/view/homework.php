<?php
if ($points_possible == 0) {
    $percent = 0;
} else {
    $percent = (int)($points_received * 100 / $points_possible);
}

?>
<div class="col-md-12 col-sm-12 blog-main">
    <div class="panel panel-default">
        <div class="panel-body"><!-- Panel Body Summary -->
            <h2>Homework <?php echo $homework_number;?></h2>
            <div class="progress" style="margin-top: 20px;"><!-- Progress Bar -->
              <div style="position: absolute; width: 100%; text-align:center;">
                <span id="centered-progress" style="margin-left: auto; text-align: center;"></span>
              </div>
              <div class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo $percent;?>%;">
                <?php echo $percent;?>%
                <span id="bar-progress"></span>
              </div>
            </div><!-- End Progress Bar -->
            <div class="panel-body" style="text-align: right;"> <!-- Body homework select -->
                <span>Select Homework</span>
                <div class="btn-group" style="text-align: left; margin-left: 20px;">
                  <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown"> Homework <?php echo $homework_number;?> <span class="caret"></span>
                  </button>
                  <ul class="dropdown-menu" role="menu">
                    <?php for ($i = 1; $i <= $last_homework; $i++) {?>
                        <li><a href="?page=homework&number=<?php echo $i;?>">Homework <?php echo $i;?></a></li>
                    <?php } ?>
                    <li class="divider"></li>
                    <li><a href="?page=homework&number=<?php echo $last_homework;?>">Current Homework</a></li>
                  </ul>
                </div>
            </div><!-- End Homework Select -->
            <div class="row"><!-- Summary Table -->
                <div class="col-sm-4">
                    Summary:
                </div>
                <div class="col-sm-8">
                    <ul class="list-group">
                        <?php foreach($homework_summary as $item) {?>
                            <?php if (isset($item["score"]) && isset($item["points_possible"]) && $item["points_possible"] != 0) {
                                $percent = $item["score"] / $item["points_possible"];
                                if ($percent == 1) {
                                    $class = "";
                                } else if ($percent >= 0.5) {
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
                <form action="#" method="post" enctype="multipart/form-data">
                    <label for="file" style="margin-right: 5%;">Filename:</label>
                    <input type="file" name="file" id="file" style="display: inline" />
                    <span class="group-btn">
                        <input type="submit" name="submit" value="Submit" class="btn btn-primary" style="margin-top: 10px">
                    </span>
                </form>
            </div><!-- End Upload New Homework -->
        </div><!-- Ends Panel Body Summary -->
    </div><!-- Ends panel-default -->
    <div class="panel panel-default"><!-- Homework Output Compare And Diff -->
        <div class="row" style="margin-left: 10px; margin-right: 10px">
            <?php foreach($homework_tests as $test) {?>
                <?php if (isset($test["score"]) && isset($test["points_possible"]) && $test["points_possible"] != 0) {
                    $percent = $test["score"] / $test["points_possible"];
                    if ($percent == 1) {
                        $class = "badge alert-success";
                    } else if ($percent >= 0.5) {
                        $class = "badge alert-warning";
                    } else {
                        $class = "badge alert-danger";
                    }
                } else {
                    $class = "badge";
                }?>
                <h4 style="margin-left: 10px;"><?php echo $test["title"];?> <span class="<?php echo $class;?>"><?php echo $test["score"]."/".$test["points_possible"];?></span></h4>
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



<script>
load_progress_bar(<?php echo $percent; ?>, "<?php echo $points_received." / ".$points_possible;?>");
</script>

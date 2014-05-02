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


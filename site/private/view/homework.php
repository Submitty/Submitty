<?php
if ($points_possible == 0) {
    $percent = 0;
} else {
    $percent = (int)($points_received * 100 / $points_possible);
}
?>

<div class="col-md-10 col-sm-9 blog-main">
    <div class="blog-header">
        <h1 class="blog-title">Homework</h1>
    </div>
    <div class="panel panel-default">
        <div class="panel-body">
            
            <div class="progress">
              <div style="position: absolute; width: 100%; text-align:center;">
                <span id="centered-progress" style="margin-left: auto; text-align: center;"></span>
              </div>
              <div class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo $percent;?>%;">
                <span id="bar-progress"></span>
              </div>
            </div>
            <div class="pull-right">
                <div class="btn-group">
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
                <span class="group-btn">
                    <button class="btn btn-primary" type="button">Go!</button>
                </span>
            </div>
        </div>
    </div>
<script>
load_progress_bar(<?php echo $percent; ?>, "<?php echo $points_received." / ".$points_possible;?>");
</script>

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
            <div class="panel-body" style="text-align: right">
            <span style="margin-right: 50px;" >Select Homework</span>
                <div class="btn-group" style="text-align: left">
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
            </div>
            <div class="progress">
              <div style="position: absolute; width: 100%; text-align:center;">
                <span id="centered-progress" style="margin-left: auto; text-align: center;"></span>
              </div>
              <div class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo $percent;?>%;">
                <span id="bar-progress"></span>
              </div>
            </div>
            <div class="panel-body">
                            </div>
            <div class="row">
                <div class="col-sm-4">
                    Summary:
                </div>
                <div class="col-sm-8">
                    <ul class="list-group">
                        <?php foreach($homework_summary as $key=>$value) {?> 
                          <li class="list-group-item">
                              <span class="badge"><?php echo $value;?></span>
                                  <?php echo $key;?>
                          </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
            <div class="panel-body" style="text-align: right">
                <form action="#" method="post" enctype="multipart/form-data">
                    <label for="file" style="margin-right: 5%;">Filename:</label>
                    <input type="file" name="file" id="file" style="display: inline" />
                    <span class="group-btn">
                        <input type="submit" name="submit" value="Submit" class="btn btn-primary" style="margin-top: 10px">
                    </span>
                </form>
            </div>
            </div> 
            </div>
        </div>
    </div>
<script>
load_progress_bar(<?php echo $percent; ?>, "<?php echo $points_received." / ".$points_possible;?>");
</script>

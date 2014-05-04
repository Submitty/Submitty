<?php
if (!isset($name) || $name=="") {
    $name = "Login";
}
if (!isset($num_homeworks)) {
    $num_homeworks = 0;
}
?>

<!-- Static navbar -->
      <div class="navbar navbar-default" role="navigation">
        <div class="container-fluid container-navbar">
          <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
              <span class="sr-only">Toggle navigation</span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
            </button>
            <a href="?page=home"><img src="resources/rpi.png" height="50"></img></a>
          </div>
          <div class="navbar-collapse collapse">
            <ul class="nav navbar-nav navbar-right">
                <li><a href="?page=home">Home</a></li>
                <li><a href="?page=announcements">Announcements</a></li>
                <?php if ($num_homeworks > 0) {?>
                <li class="dropdown">
                    <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                        Homework <span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu">
                        <?php for ($i = 1; $i <= $num_homeworks; $i++) {?>
                            <li><a href="?page=homework&number=<?php echo $i;?>">Homework <?php echo $i;?></a></li>
                        <?php }?>
                    </ul>
                </li>
                <?php } else {?>
                    <li><a href="?page=homework">Homework</a></li>
                <?php }?>
                <li><a href="?page=grades">Grades</a></li>
                <?php if ($name == "Login") {?>
                    <li><a href="#">Login</a></li>
                <?php } else {?>
                <li class="dropdown">
                    <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                        <?php echo $name;?> <span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="?page=logout">Logout</a></li>
                    </ul>
                </li>
                <?php }?>
            </ul>
          </div><!--/.nav-collapse -->
        </div><!--/.container-fluid -->
      </div>


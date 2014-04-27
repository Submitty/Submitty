<?php if (!isset($num_homeworks)) {
    $num_homeworks = 5;
    if (!isset($page)) {
        $page = "";
    }
    if (!isset($homework_number)) {
       $homework_number = 0;
    }
}?>



<div class="col-md-2 col-sm-3 hidden-xs sidebar"> 
  <ul class="nav nav-sidebar">
    <li class="active"><a href="?page=home">Home</a></li>
    <li><a href="?page=announcements">Announcements</a></li>
    <li><a href="?page=grades">Grades</a></li>

    <li class="subnav-show"><a href="?page=homework">Homework <b class="caret"></b></a>
        <ul class="subnav subnav-show nav-sidebar" <?php if ($page == "homework") {?> style="display:block" <?php } ?> >
            <?php for ($i = 1; $i <= $num_homeworks; $i++) {?>
                <li <?php if ($homework_number > 0) {?> class="active"<?php }?>><a href="?page=homework&number=<?php echo $i;?>">Homework <?php echo $i;?></a></li>
            <?php }?>
        </ul>
    </li>
     </ul>
     <br>
</div>


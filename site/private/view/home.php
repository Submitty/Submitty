<?php
render("header");
$homework = array("due_date"=>"4/10/14","name"=>"Homework 8","score"=>"25/25");
$a_data = array(
    array("date"=>"Apr 4","text"=>"While the webserver is being updated.... please use this URL for homework submission: https://cgi8.cs.rpi.edu/submit/submit.php?course=csci1200", "link"=>"https://cgi8.cs.rpi.edu/submit/submit.php?course=csci1200"),
    array("date"=>"Apr 4","text"=>"HW8 has been posted on the calendar. It is due Thursday Apr 10th at 11:59pm."),
    array("date"=>"Apr 3", "text"=>"HW6 grades (without contest extra credit) are now available on the homework submission server.
    HW6 avg:31/50, std dev:12 approximate grades: 42&up=A, 30&up=B, 20&up=C, 15&up=D.
    Time spent on HW5: average 22.3 hours, median 20 hours.")
);
?>
<div class="container">
    <?php render("navbar",array("name"=>"Sengs"));?>
    <div class="container-fluid">
        <div class="row">
            <?php render("leftnavbar");?>
            <?php if ($page == "announcements") {
                render("announcements");
            } else if ($page == "homework") {
                render("homework");
            } else if ($page == "grades") {
                render("grades");
            } else {?>

                <div class="col-md-9 blog-main">
                    <div class="col-md-4">
                        <h3>Announcements</h3>
                        <p><?php if (isset($a_data)) {?>
                            <h4><p class="blog-post-meta"><?php echo $a_data[0]["date"];?></p></h4>
                            <p><?php echo $a_data[0]["text"];?></p>
                        <?php } else {
                            echo "No recent announcements.";
                        }?>
                        </p>
                        <p><a class="btn btn-default" href="?page=announcements" role="button">See more</a></p>
                    </div>
                    <div class="col-md-4">
                        <h3>Homework</h3>
                        <p><?php
                            if (isset($homework)) {
                                echo $homework["name"]." is due on ".$homework["due_date"]." at 11:59PM.  ";
                                if (isset($homework["score"])) {
                                    echo "Your last submission score was ".$homework["score"].".";
                                }
                            } else {
                                echo "No current homework";
                            }?>
                        </p>
                        <p><a class="btn btn-default" href="?page=homework" role="button">Homework</a></p>
                    </div>
                    <div class="col-md-4">
                        <h3>Grades</h3>
                        <p>Grade overview</p>
                        <p><a class="btn btn-default" href="?page=grades" role="button">Grades</a></p>
                    </div>
                </div>
                </div>
            <?php }?>
      <!-- Main component for a primary marketing message or call to action -->
        </div>
    </div> <!-- /container -->
</div>

<?php
render("footer");
?>

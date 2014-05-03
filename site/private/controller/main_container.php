<?php
require_once("private/controller/helper.php");

//Make model function calls for navbar data here

$name = "Sam";//For navbar
$num_homeworks = 5;//For navbar

if (!isset($page) || ($page != "homework" && $page != "grades" && $page != "announcements")) {
    $page = "home";
}
if (!isset($last_homework)) {
    $last_homework = 5;
}
if (!isset($homework_number)) {
    $homework_number = $last_homework;
}
if ($page == "homework") {
    if (isset($_GET["number"])) {
        $homework_number = htmlspecialchars($_GET["number"]);
    }
    if (!($homework_number > 0 && $homework_number <= $last_homework)) {
        $homework_number = $last_workwork;
    }
}


render("header");//This is the html tag, body tag, head tag, scripts tags, etc.
render("navbar", array("name"=>$name, "num_homeworks"=>$num_homeworks));//This is the top navbar
?>
<div class="col-sm-12">
    <div class="container container-main">
        <?php if ($page == "announcements") {
            render_controller("announcements");
        } else if ($page == "homework") {
            render_controller("homework", array("homework_number"=>$homework_number, "last_homework"=>$last_homework));
        } else if ($page == "grades") {
            render_controller("grades");
        } else {
            render_controller("home", array());
        }
        ?>
    </div>
</div>
<?php 

render("footer");

<?php
require_once("private/controller/helper.php");

//Make model function calls for navbar and leftnavbar data here


if (!isset($name)) {
    $name = "Sam";
}
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


render("header");

render("navbar", array("name"=>$name));
render("leftnavbar", array("page"=>$page, "homework_number"=>$homework_number));
?>
<?php 

//if ($page != "homework") {//Uncomment for homework diff comparison to take entire width of screen

?>
<div class="col-sm-8">
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

/*} else {
    render_controller("homework", array("homework_number"=>$homework_number, "last_homework"=>$last_homework));//Uncomment for homework diff comparison to take entire width of screen
}*/

render("footer");

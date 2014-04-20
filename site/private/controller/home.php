<?php
require_once("private/controller/helper.php");
if ($page == "homework") {
    render("home",array("page"=>"homework", "num_homeworks"=>10));
    exit();
}
render("home",array("page"=>$page));
?>

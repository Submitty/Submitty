<?php
require_once("../private/model/homework_model_functions.php");
if (isset($_GET["assignment_id"]) && isset($_GET["assignment_version"])) {
    $assignment_id = htmlspecialchars($_GET["assignment_id"]);
    $assignment_version = htmlspecialchars($_GET["assignment_version"]);
    change_assignment_version($_SESSION["id"], $assignment_id, $assignment_version);
    header ("Location: index.php?page=homework&assignment_id=".$assignment_id."&assignment_version=".$assignment_version);
} else {
    header ("Location: index.php");
}

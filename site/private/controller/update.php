<?php
require_once("../private/model/homework_model_functions.php");
if (isset($_GET["assignment_id"]) && isset($_GET["assignment_version"])) {
    $assignment_id = htmlspecialchars($_GET["assignment_id"]);
    $assignment_version = htmlspecialchars($_GET["assignment_version"]);
    if (!can_edit_assignment($_SESSION["id", $assignment_id, $assignment_config)) {
        header ("Location: index.php?page=homework&assignment_id=".$assignment_id."&assignment_version=".$assignment_version."&error=assignment_closed";
    }
    header ("Location: index.php?page=homework&assignment_id=".$assignment_id."&assignment_version=".$assignment_version);
} else {
    header ("Location: index.php");
}

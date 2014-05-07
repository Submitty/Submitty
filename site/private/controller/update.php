<?php
if (isset($_GET["assignment_id"]) && isset($_GET["assignment_version"])) {
    $assignment_id = htmlspecialchars($_GET["assignment_id"]);
    $assignment_version = htmlspecialchars($_GET["assignment_version"]);
    header ("Location: index.php?page=homework&assignment_id=".$assignment_id."&assignment_version=".$assignment_version);
} else {
    header ("Location: index.php");
}

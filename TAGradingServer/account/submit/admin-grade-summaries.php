<?php

require "../../toolbox/functions.php";
require "../../models/GradeSummary.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

$grade_summaries = new GradeSummary();
$grade_summaries->generateAllSummaries();

?>

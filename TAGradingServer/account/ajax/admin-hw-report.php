<?php

include "../../toolbox/functions.php";
require "../../models/HWReport.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

$hw_report = new HWReport();
$hw_report->generateAllReports();

echo "updated";
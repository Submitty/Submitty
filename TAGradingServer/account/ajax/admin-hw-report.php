<?php

include "../../toolbox/functions.php";
require "../../models/HWReport.php";

check_administrator();

if (!isset($_POST['csrf_token'])) {
    die("invalid csrf token");
}

if ($_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("csrf token mismatch");
}

$hw_report = new HWReport();
$hw_report->generateAllReports();

echo "updated";
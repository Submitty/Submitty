<?php

define("__BASE_URL__", "http://localhost/TAGradingServer");

define("__SUBMISSION_GRACE_PERIOD_SECONDS__", 30 * 60);
define("__OUTPUT_MAX_LENGTH__", 100000);

define("__COURSE_CODE__", "test_class");

// Database connection information
define("__DATABASE_HOST__", "localhost");
define("__DATABASE_NAME__", "test_hwgrading");
define("__DATABASE_USER__", "postgres");
define("__DATABASE_PASSWORD__", '');

define("__SUBMISSION_SERVER__", __DIR__."/testData/submission_server");

// Zero out the scores on the rubric when TAs grades

define("__DEBUG__", true);

define("__LOG_PATH__", str_replace("/toolbox/configs", "", __DIR__)."/logs");
define("__LOG_EXCEPTIONS__", true);
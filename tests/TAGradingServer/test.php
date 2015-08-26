<?php

define("__BASE_URL__", "http://localhost:8888/hwgrading");

define("__SUBMISSION_GRACE_PERIOD_SECONDS__", 30 * 60);
define("__OUTPUT_MAX_LENGTH__", 100000);

define("__COURSE_CODE__", "test");
define("__COURSE_NAME__", "Test Course");

define("__USE_AUTOGRADER__", true);

define("__CALCULATE_DIFF__", true);

// Database connection information
define("__DATABASE_HOST__", "localhost");
define("__DATABASE_NAME__", "test_hwgrading");
define("__DATABASE_USER__", "postgres");
define("__DATABASE_PASSWORD__", '');

define("__ALLOWED_FILE_EXTENSIONS__", "py,times,txt");
define("__SUBMISSION_SERVER__", str_replace("/toolbox/configs", "", __DIR__)."/tests/data");

// Zero out the scores on the rubric when TAs grades
define("__ZERO_RUBRIC_GRADES__", false);

define("__DEBUG__", true);

define("__LOG_PATH__", str_replace("/toolbox/configs", "", __DIR__)."/logs");
define("__LOG_EXCEPTIONS__", true);
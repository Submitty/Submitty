<?php

define("__BASE_URL__", "http://localhost:8888/hwgrading");

define("__PASSWORD_MINIMUM_LENGTH__", 1); // Minimum length of a user's password when they signup
define("__PASSWORD_AUTO_LENGTH__", 8); // Automaticly generated password length of a user when an account is made for them
define("__LOGIN_FAILURE_ACCOUNT_LOCK__", 5); // Maximum number of times a failed login attempt before the account is locked and must be unlocked by an administrator
define("__SESSION_CHECK_IP_ADDRESS__", false); // Checks the IP Address between page refreshes to try and catch XSS, not to be used for a mobile app
define("__SESSION_LENGTH_MINUTES__", 60 * 24 * 30); // Length of time (in minutes) a session will last past the last activity observed (page refresh or go to page)
define("__SESSION_CLEAR_HISTORY_DAYS__", 90); // Length of time (in days) the database will keep records of sessions
define("__ALLOW_RPI_LOGIN__", true);

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
?>
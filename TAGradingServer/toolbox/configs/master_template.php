<?php

/* -- To be filled in by CONFIG.sh and INSTALL.sh --------------------------- */
define("__BASE_URL__", "__INSTALL__FILLIN__TAGRADING_URL__");
define("__DATABASE_HOST__", "__INSTALL__FILLIN__DATABASE_HOST__");
define("__DATABASE_USER__", "__INSTALL__FILLIN__DATABASE_USER__");
define("__DATABASE_PASSWORD__", "__INSTALL__FILLIN__DATABASE_PASSWORD__");
define("__SUBMISSION_GRACE_PERIOD_SECONDS__", 5 * 60);
define("__OUTPUT_MAX_LENGTH__", 100000);
define("__DEBUG__", false);
define("__LOG_EXCEPTIONS__", true);
define("__LOG_PATH__", "__INSTALL__FILLIN__SITE_LOG_PATH__");

/* -- static configs for hard coded values ---------------------------------- */
//Used by xslx_to_csv conversion process
define('__TMP_XLSX_PATH__', '/tmp/_SUBMITTY_xlsx');
define('__TMP_CSV_PATH__',  '/tmp/_SUBMITTY_csv');

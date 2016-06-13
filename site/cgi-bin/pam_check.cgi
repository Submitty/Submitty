#!/usr/bin/env php

<?php

session_start();
$_SESSION['test'] = true;

print '<HTML><META HTTP-EQUIV="refresh" CONTENT="0;URL=https://192.168.56.101/index.php"></HTML>';
<?php
require_once("toolbox/functions.php");
header('Location: '.__BASE_URL__.'/account/index.php?course='.$_GET['course']."&".$_GET['semester']);
<?php
require_once("toolbox/functions.php");

header('Location: '.__BASE_URL__.'/account/index.php?course='.$_GET['course'].'&semester='.$_GET['semester']);

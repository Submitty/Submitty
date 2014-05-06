<?php

require_once("../private/model/homework_model_functions.php");


//Upload the stuff

if (isset($_FILES["file"])) {
    if (!isset($_GET["homework"])) {
        //Go to error page?
        echo "here";
        exit();
    }
    $homework = htmlspecialchars($_GET["homework"]);
    if (!($homework >= 0 && $homework < 100)) {
        //Go to error page?
        exit();
    }
    $uploaded_file = $_FILES["file"];//THIS NEEDS TO BE MADE HACKER PROOF
    $result = upload_homework("sengs",$homework,$uploaded_file);
    if (isset($result["error"])) {
        //Go to error page?
    }
}



//Go back to homework page

require_once("../private/controller/homework.php");

?>

<?php

require_once("../private/model/homework_model_functions.php");


//Upload the stuff

if (isset($_FILES["file"])) {
    if (!isset($_GET["number"]) || !isset($_GET["assignment"])) {
        //Go to error page?
        echo "here1";
        exit();
    }
    $assignment = htmlspecialchars($_GET["assignment"]);
    $number = htmlspecialchars($_GET["number"]);
    if (!($number >= 0 && $number < 100)) {
        //Go to error page?
        echo "here2";
        exit();
    }
    if (strpos($assignment," ")) {
        //Go to error page?
        echo "here3";
        exit();
    }
    $uploaded_file = $_FILES["file"];//THIS NEEDS TO BE MADE HACKER PROOF
    $result = upload_homework($_SESSION["id"],$assignment,$number,$uploaded_file);
    if (isset($result["error"])) {
        //Go to error page?
        ?>
        <script>
        alert("Error: Assignment could not be uploaded");
        </script>
        <?php
    }
}
//Go back to homework page
header("Location: index.php?page=homework&number=".$number."&assignment=".$assignment);

?>

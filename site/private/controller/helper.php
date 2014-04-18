<?php
function render($viewpage, $data = array()) {
    $path = './private/view/'.$viewpage.'.php';
    if (file_exists($path)) {
        extract($data);
        require_once($path);
    } else {
        echo "Error, render file path does not exist <br>";
        echo "cwd = ";
        echo getcwd();
        echo "<br>path = ";
        print_r($path);
        //header('Location: index.php');
    }
}
?>

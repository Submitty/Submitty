<?php


namespace lib;


class ErrorPage {
    public static function get_error_page($message = "") {
        $output = <<<HTML
<!DOCTYPE html>
<html>
    <title>Error</title>
    <body>
        An error has occurred: <br /><br />
        <span id="message">{$message}</span>
    </body>
</html>
HTML;

        return $output;
    }
}
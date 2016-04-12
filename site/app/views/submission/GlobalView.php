<?php

namespace app\views\submission;

use app\models\Config;

class GlobalView {
    public function header($title) {
        $base_url = Config::$base_url;
        return <<<HTML
<html>
<head>
    <title>{$title}</title>
    <link type="text/css" href="{$base_url}public/css/homework.css" rel="stylesheet" />
    <script type="text/javascript" href="{$base_url}public/js/jquery.min.js" />
</head>
<body>
HTML;

    }

    public function footer() {
        return <<<HTML
</body>
</html>
HTML;

    }

    public function invalidPage($page) {
        return <<<HTML
<div class="box">
The page {$page} does not exist. Please try again.
</div>
HTML;

    }
}
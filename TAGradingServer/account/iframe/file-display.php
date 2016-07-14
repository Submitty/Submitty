<?php

include __DIR__."/../../toolbox/functions.php";

if (strstr($_GET['filename'], "/../") !== false) {
    die("You cannot have /../ in the filename.");
}
$paths = explode("/", $_GET['filename']);
if (!empty($paths[0]) || !in_array($paths[1], array('checkout', 'submissions', 'results'))) {
    die("Cannot access this file. Path must start with /checkout/, /submissions/, or /results/.");
}

$filename = html_entity_decode($_GET['filename']);
$filename = __SUBMISSION_SERVER__ . $filename;
if (!file_exists($filename)) {
    print "{$filename} does not exist";
	die();
}

$content_type = getContentType($filename);
if(substr($content_type, 0, 4) === "text")
{
$output = <<<HTML
<!doctype html>
<html>
<head>
    <title>{$filename}</title>
    <link rel="stylesheet" href="{$BASE_URL}/toolbox/include/codemirror/lib/codemirror.css" />
    <link rel="stylesheet" href="{$BASE_URL}/toolbox/include/codemirror/theme/eclipse.css" />
    <script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/custom/js/jquery-2.0.3.min.map.js"></script>
    <script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/lib/codemirror.js"></script>
    <script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/clike/clike.js"></script>
    <script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/python/python.js"></script>
    <script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/shell/shell.js"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            $('body').css("height", ($('.CodeMirror').height()) + "px");
        });
    </script>
</head>
<body>
    <textarea id="code0">
HTML;
    $output .= htmlentities(file_get_contents($filename), ENT_SUBSTITUTE);
    $output .= <<<HTML
    </textarea>
HTML;
$output .= sourceSettingsJS($filename, 0);
$output .= <<<HTML
</body>
</html>
HTML;
print $output;
}
else if (substr($content_type, 0, 5) === "image" || $content_type === "application/pdf") {
    header("Content-type: ".$content_type);
    header('Content-Disposition: inline; filename="' .  basename($filename) . '"');
    echo file_get_contents($filename);
}
<?php

include __DIR__."/../../toolbox/functions.php";
$filename = html_entity_decode($_GET['filename']);
if (!file_exists($filename)) {
    print "{$filename} does not exist";
}

$output = <<<HTML
		<link rel="stylesheet" href="{$BASE_URL}/toolbox/include/codemirror/lib/codemirror.css" />
		<link rel="stylesheet" href="{$BASE_URL}/toolbox/include/codemirror/theme/eclipse.css" />
        <script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/custom/js/jquery-2.0.3.min.map.js"></script>
        <script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/codemirror-compressed.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/clike/clike.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/python/python.js"></script>
		<script type="text/javascript" language="javascript" src="{$BASE_URL}/toolbox/include/codemirror/mode/shell/shell.js"></script>
HTML;

$output .= <<<HTML
<textarea id="code0">
HTML;
$output .= htmlentities(file_get_contents($filename));
$output .= <<<HTML
</textarea>
HTML;

$output .= sourceSettingsJS($filename, 0);

print $output;
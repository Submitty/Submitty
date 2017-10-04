<?php

namespace app\views;
use app\libraries\FileUtils;

class MiscView extends AbstractView {
    public function displayFile($file_contents) {
        return <<<HTML
<pre>
{$file_contents}
</pre>
HTML;
    }

    private function sourceSettingsJS($mime_type, $filename, $number) {
        $type = FileUtils::getContentType($filename);
        $number = intval($number);
        $return = <<<HTML
    <script>
        var editor{$number} = CodeMirror.fromTextArea(document.getElementById('code{$number}'), {
            lineNumbers: true,
            readOnly: true,
            cursorHeight: 0.0,
            lineWrapping: true
	    });

	    var lineCount = editor{$number}.lineCount();
	    if (lineCount == 1) {
	        editor{$number}.setSize("100%", (editor{$number}.defaultTextHeight() * 2) + "px");
	    }
	    else {
	        editor{$number}.setSize("100%", "auto");
	    }
	    editor{$number}.setOption("theme", "eclipse");
HTML;
        
            $return .= <<<HTML
        editor{$number}.setOption("mode", "{$type}");
HTML;
        
        
        $return .= <<<HTML
	    $("#myTab").find("a").click(function (e) {
	        e.preventDefault();
	        $(this).tab("show");
	        setTimeout(function() { editor{$number}.refresh(); }, 1);
	    });
    </script>
HTML;
        return $return;
    }

    public function displayCode($mime_type, $filename, $file_contents) {
        $return = <<<HTML
<!doctype html>
<html>
<head>
    <title>{$filename}</title>
    <link rel="stylesheet" href="{$this->core->getConfig()->getBaseUrl()}css/iframe/codemirror.css" />
    <link rel="stylesheet" href="{$this->core->getConfig()->getBaseUrl()}css/iframe/eclipse.css" />
    <script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/jquery-2.0.3.min.map.js"></script>
    <script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/codemirror.js"></script>
    <script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/clike.js"></script>
    <script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/python.js"></script>
    <script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/shell.js"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            $('body').css("height", ($('.CodeMirror').height()) + "px");
        });
    </script>
</head>
<body>
    <textarea id="code0">
HTML;
        $return .= $file_contents;
        $return .= <<<HTML
    </textarea>
HTML;
		$return .= $this->sourceSettingsJS($mime_type, $filename, 0);
        $return .= <<<HTML
</body>
</html>
HTML;
        return $return;
	}
}

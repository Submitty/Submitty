<?php

namespace app\views;

class MiscView extends AbstractView {
    public function displayFile($file_contents) {
        return <<<HTML
<pre>
{$file_contents}
</pre>
HTML;
    }

    private function getContentType($filename){
        switch (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
	        // pdf
	        case 'pdf':
	            $content_type = "application/pdf";
	            break;
	        // images
	        case 'png':
	            $content_type = "image/png";
	            break;
	        case 'jpg':
	        case 'jpeg':
	            $content_type = "image/jpeg";
	            break;
	        case 'gif':
	            $content_type = "image/gif";
	            break;
	        case 'bmp':
	            $content_type = "image/bmp";
	            break;
	        // text
	        case 'c':
	            $content_type = 'text/x-csrc';
	            break;
	        case 'cpp':
	        case 'cxx':
	        case 'h':
	        case 'hpp':
	        case 'hxx':
	            $content_type = 'text/x-c++src';
	            break;
	        case 'java':
	            $content_type = 'text/x-java';
	            break;
	        case 'py':
	            $content_type = 'text/x-python';
	            break;
	        default:
	            $content_type = 'text/x-sh';
	            break;
	    }
	    return $content_type;
	}

    private function sourceSettingsJS($filename, $number) {
        $type = FileUtils::getContentType($filename);
        $number = intval($number);
        return <<<HTML
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
	    editor{$number}.setOption("mode", "{$type}");

	    $("#myTab").find("a").click(function (e) {
	        e.preventDefault();
	        $(this).tab("show");
	        setTimeout(function() { editor{$number}.refresh(); }, 1);
	    });
    </script>
HTML;
    }

    public function displayCode($filename, $file_contents) {
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
		$return .= $this->sourceSettingsJS($filename, 0);
        $return .= <<<HTML
</body>
</html>
HTML;
        return $return;
	}
}

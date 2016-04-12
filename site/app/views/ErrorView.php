<?php

namespace app\views;

class ErrorView {
    public function errorPage($error_message) {
        $top_message = "Oh no! Something irrecoverable has happened...";
        $error_message = nl2br(str_replace(" ", "&nbsp;", $error_message));
        return <<<HTML
<html>
<head>
    <title>HWServer - Error</title>
</head>

<body>
<h1 style="margin-left: 20px; margin-top: 10px;">500: Server Error</h1>
<div style="position: absolute; top: 144px; left: 362px; border: 1px dashed black; padding: 10px; font-family: monospace">
    {$top_message}<br /><br />
    {$error_message}
</div>
<pre>
                  ,--.    ,--.
                 (( O))--(( O))
               ,'_`--'____`--'_`.
              _:  ____________  :_      _____
             | | ||::::::::::|| | |        \ \
             | | ||::::::::::|| | |         \
             | | ||::::::::::|| | |
             |_| |/__________\| |_|
               |________________|
            __..-'            `-..__
         .-| : .----------------. : |-.
       ,\ || | |\______________/| | || /.
      /`.\:| | ||  __  __  __  || | |;/,'\
     :`-._\;.| || '--''--''--' || |,:/_.-':
     |    :  | || .----------. || |  :    |
     |    |  | || '----SSt---' || |  |    |
     |    |  | ||   _   _   _  || |  |    |
     :,--.;  | ||  (_) (_) (_) || |  :,--.;
     (`-'|)  | ||______________|| |  (|`-')
      `--'   | |/______________\| |   `--'
             |____________________|
              `.________________,'
               (_______)(_______)
               (_______)(_______)
               (_______)(_______)
               (_______)(_______)
              |        ||        |
              '--------''--------'
</pre>
</body>
</html>
HTML;

    }

    public function invalidPage($page) {
        return <<<HTML
<div>
    The page you requested {$page} does not exist and cannot be used.
</div>
HTML;

    }
}
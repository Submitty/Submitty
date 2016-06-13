<?php

namespace app\views;

class ErrorView {
    public function exceptionPage($error_message) {
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

    public function errorPage($error_message) {
        $error_message = nl2br(str_replace(" ", "&nbsp;", $error_message));
        return <<<HTML
<html>
<head>
    <title>403: Forbidden</title>
</head>
<body>
<h1 style="margin-left: 20px; margin-top: 10px;">403: Forbidden</h1>
<div style="position: absolute; top: 218px; left: 203px; border: 1px dashed black; padding: 10px; font-family: monospace">
It does not look like you're allowed to access this page.<br /><br />
Reason: {$error_message}<br /><br />
Please contact system administrators if you believe this is a mistake.
</div>

<pre>
   /\/\/\/\/\/\  
  <            >
   |          |
   |          |
   |   _  _   |
  -|_ / \/ \_ |-
 |I|  \_/\_/  |I|
  -|   /  \   |-
   |   \__/   |      ____
   |          |        \ \
   |          |         \
   |__________|
  /___/\__/\___\
 /     | \|     \
   /\  |\ | _@|#_
  / /\ | \| |   |
  \/  / \ / |   |
   \_/___/   \_/ 
</pre>

</body>
</html>
HTML;

    }
}
<?php

namespace app\views;

class ErrorView extends AbstractView {
    public function exceptionPage($error_message) {
        $top_message = "Oh no! Something irrecoverable has happened...";
        $error_message = nl2br(str_replace(" ", "&nbsp;", $error_message));
        return <<<HTML
<html>
<head>
    <title>Submitty - Error</title>
</head>

<body>
<h1 style="margin-left: 20px; margin-top: 10px;">Server Error</h1>
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
	$return = <<<HTML
<html>
<head>
    <title>Submitty - Forbidden</title>
</head>
<body>
<h1 style="margin-left: 20px; margin-top: 10px;">Forbidden</h1>
<div style="position: absolute; top: 218px; left: 203px; border: 1px dashed black; padding: 10px; font-family: monospace">
It does not look like you're allowed to access this page.<br /><br />
Reason: {$error_message}<br /><br />
Please contact system administrators if you believe this is a mistake.
HTML;


	if (file_exists("/usr/local/submitty/site/app/views/current_courses.php")) {
	   $courselist = file_get_contents("/usr/local/submitty/site/app/views/current_courses.php");
           $return .= <<<HTML
<br /><br />
Perhaps you are looking for one of these courses:<br /><br />
$courselist
HTML;
	}


        $return .= <<<HTML
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
return $return;
    }

    public function noGradeable($gradeable_id = null) {
        if ($gradeable_id === null) {
            return <<<HTML
<div class="content">
    No gradeable id specified. Contact your instructor if you think this is an error.
</div>
HTML;
        }
        else {
            $gradeable_id = htmlentities($gradeable_id, ENT_QUOTES);
            return <<<HTML
<div class="content">
    {$gradeable_id} is not a valid electronic submission gradeable. Contact your instructor if you think this
    is an error.
</div>
HTML;
        }
    }
}

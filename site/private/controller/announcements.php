<?php

//Make model function calls for announcements here
$a_data = array(
    array("date"=>"Apr 4","text"=>"While the webserver is being updated.... please use this URL for homework submission: https://cgi8.cs.rpi.edu/submit/submit.php?course=csci1200", "link"=>"https://cgi8.cs.rpi.edu/submit/submit.php?course=csci1200"),
    array("date"=>"Apr 4","text"=>"HW8 has been posted on the calendar. It is due Thursday Apr 10th at 11:59pm."),
    array("date"=>"Apr 3", "text"=>"HW6 grades (without contest extra credit) are now available on the homework submission server.
    HW6 avg:31/50, std dev:12 approximate grades: 42&up=A, 30&up=B, 20&up=C, 15&up=D.
    Time spent on HW5: average 22.3 hours, median 20 hours.")
);

render("announcements", array());
?>

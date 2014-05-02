<?php
$data = array(
    array("date"=>"Apr 4","text"=>"While the webserver is being updated.... please use this URL for homework submission:", "link"=>"https://cgi8.cs.rpi.edu/submit/submit.php?course=csci1200"),
    array("date"=>"Apr 4","text"=>"HW8 has been posted on the calendar. It is due Thursday Apr 10th at 11:59pm."),
    array("date"=>"Apr 3", "text"=>"HW6 grades (without contest extra credit) are now available on the homework submission server.
    HW6 avg:31/50, std dev:12 approximate grades: 42&up=A, 30&up=B, 20&up=C, 15&up=D.
    Time spent on HW5: average 22.3 hours, median 20 hours.")
);
if (!isset($announcements_url)) {
    $announcements_url = "http://www.cs.rpi.edu/academics/courses/spring14/ds/announcements.php";
}
?>
<div class="col-sm-10 blog-main" id="blog-main">
    <div class="panel panel-default">
        <div class="panel-body">
            <h2>Announcements</h2>
            <?php foreach ($data as $post) {
                ?>
                <div class="blog-post">
                    <h3><p class="blog-post-meta"><?php echo $post["date"];?></p></h3>
                    
                    <p><?php echo $post["text"];?></p>
                    <?php if (isset($post["code"])) {
                        ?><pre><code><?php echo $post["code"];?></code></pre>
                    <?php } 
                    if (isset($post["link"])) { ?>
                        <pre><code><a href='<?php echo $post["link"];?>'><?php echo $post["link"];?></a></code></pre>
                    <?php }?>
                    
                </div>
            <?php } ?>
        </div>
    </div>
</div>

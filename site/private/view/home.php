<div class="col-sm-10 blog-main">
    <div class="row">
            <div class="col-md-4 panel-col"> 
                <div class="panel panel-default">
                    <div class="panel-body word-wrap">
                        <h4>Announcements</h4>
                        <p><?php if (isset($a_data)) {?>
                            <h4><p class="blog-post-meta"><?php echo $a_data[0]["date"];?></p></h4>
                            <p><?php echo $a_data[0]["text"];?></p>
                        <?php } else {
                            echo "No recent announcements.";
                        }?>
                        </p>
                        <p><a class="btn btn-default" href="?page=announcements" role="button">See more</a></p>                        
                    </div>
                </div>
            </div>
            <div class="col-md-4 panel-col">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <h4>Homework</h4>
                        <p><?php
                            if (isset($homework)) {
                                echo $homework["name"]." is due on ".$homework["due_date"]." at 11:59PM.  ";
                                if (isset($homework["score"])) {
                                    echo "Your last submission score was ".$homework["score"].".";
                                }
                            } else {
                            echo "No current homework";
                            }?>
                        </p>
                        <p><a class="btn btn-default" href="?page=homework" role="button">Homework</a></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 panel-col">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <h4>Grades</h4>
                        <p>Grade overview</p>
                        <p><a class="btn btn-default" href="?page=grades" role="button">Grades</a></p>
                    </div>
                </div>
            </div>
    </div><!--Ends row-->
</div><!--Ends col-xs-10-->
      <!-- Main component for a primary marketing message or call to action -->

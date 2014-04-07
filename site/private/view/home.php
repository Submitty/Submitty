<?php
render("header");
?>
<div class="container">
    <?php render("navbar",array("name"=>"Sengs"));?>
    <div class="container-fluid">
        <div class="row">
            <?php render("leftnavbar");?>
            <?php if ($page == "announcements") {
                render("announcements");
            } else if ($page == "homework") {
                render("homework");
            } else if ($page == "grades") {
                render("grades");
            } else {?>
            <div class="col-sm-8 blog-main">
                <div class="blog-header">
                    <h1 class="blog-title">Home</h1>
                </div>
            </div>
            <?php } ?>
      <!-- Main component for a primary marketing message or call to action -->
        </div>
    </div> <!-- /container -->
</div>
<?php
render("footer");
?>

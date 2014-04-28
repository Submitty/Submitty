<?php
render("header");
?>
    <?php render("navbar",array("name"=>""));?>
        <div class="container">
            <div class="jumbotron">
            <div class="row">
                <div class="panel panel-default col-xs-8 col-xs-offset-2" style="padding: 0">
                    <div class="panel-heading">
                        <h3 class="panel-title">Homework Submission Server Login</h3>
                    </div>
                    <div class="panel-body">
                        <form class="form-signin" action="index.php?page=home&temp=no" method="get" role="form">
                            <input type="email" class="form-control" placeholder="Email Address" autofocus>
                            <input type="password" class="form-control" placeholder="Password">
                            <input name="page" value="home" readonly="readonly" hidden="hidden"></input>
                            <button class="btn btn-lg btn-primary btn-block" type="submit"> Sign In</button>
                        </form>

                    </div>
                </div>
            </div>
            </div>
        </div>
<?php
render("footer");
?>
<script>

$("#

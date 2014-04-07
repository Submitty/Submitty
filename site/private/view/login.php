<?php
render("header");
?>
<div class="container">
      <?php render("navbar",array("name"=>""));?>
        <!-- Main component for a primary marketing message or call to action -->
      <div class="jumbotron">
        <div class="login-title"><h2>Homework Submission Login</h2></div>
        <div class="container">
            <form class="form-signin" action="index.php?page=home&temp=no" method="get" role="form">
                <input type="email" class="form-control" placeholder="Email Address" autofocus>
                <input type="password" class="form-control" placeholder="Password">
                <input name="page" value="home" readonly="readonly" hidden="hidden"></input>
                <button class="btn btn-lg btn-primary btn-block" type="submit"> Sign In</button>
            </form>
        </div>
      </div>

    </div> <!-- /container -->

<?php
render("footer");
?>
<script>

$("#

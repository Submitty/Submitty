<?php umask (0027);
session_start(); //You must start session or declare this variable at the very top and first line of your website page
ob_start();

$QUERY_STRINg = $_SERVER[REQUEST_URI];

//if a user logs into the system and leave his or her account for 30 minutes of inactive, his or her session will expire and require new login
$inactive = 1800; // 30 minutes
if(isset($_SESSION['timeout'])) 
{
	$session_life = time() - $_SESSION['timeout'];
	if($session_life > $inactive)
	{
		session_unset();
		session_destroy(); 
	}
}
$_SESSION['timeout'] = time();


if(isset($_SESSION['validfullname']) && isset($_SESSION['validusername']) && isset($_SESSION['validemail']) && isset($_SESSION['validpassword']) && !empty($_SESSION['validfullname']) && !empty($_SESSION['validusername']) && !empty($_SESSION['validemail']) && !empty($_SESSION['validpassword'])) {


//===============================================================
//Remove error reporting and ini set for production code
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user = $_SESSION['validusername'];
$_SESSION["id"] = $user;

if (isset($_GET["page"])) {
    $page = htmlspecialchars($_GET["page"]);
} else {
    $page = "homework";
}


//This needs to be wrapped around session Ids and logins
if ($page == "upload") {
    require_once("controller/upload.php");
} else if ($page == "update") {
    require_once("controller/update.php");
} else if ($page == "checkrefresh") {
    require_once("controller/check_refresh.php");
} else if ($page == "viewfile") {
    require_once("controller/viewfile.php");
} else {
    require_once("controller/homework.php");
}
//===============================================================
?>


<head>

<!-- Required header files -->
<script type="text/javascript" src="login/js/jquery_1.5.2.js"></script>
<script type="text/javascript" src="login/js/vpb_save_details.js"></script>
<link href="login/css/style.css" rel="stylesheet" type="text/css">

</head>
<body>


<!-- Profile Page Code Begins -->

<div style=" width:800px; padding:10px;-webkit-border-radius: 5px;-moz-border-radius: 5px;border-radius: 5px; font-size:13px;" id="vasplus_programming_blog_wrapper">

<div class="" align="left" style="width:730px; float:left;font-family:Verdana, Geneva, sans-serif; font-size:16px; font-weight:bold;">Welcome <?php echo $_SESSION['validfullname']; ?>
</div>

<div style="width:70px;float:left;" align="right"><span class="ccc"><a href="login/logout.php"><font color="#3300FF">Logout</font></a></span>
</div>

<br clear="all"><br clear="all"><br clear="all">

<div align="left"><b>Below are all your information</b></div>
<br clear="all">

<div class="vpb_lebels" style="padding:0px;" align="left">Your Fullname:</div>
<div class="vpb_lebels_fields" align="left"><?php echo $_SESSION['validfullname']; ?></div>
<br clear="all"><br clear="all">

<div class="vpb_lebels" style="padding:0px;" align="left">Your Username:</div>
<div class="vpb_lebels_fields" align="left"><?php echo $_SESSION['validusername']; ?></div>
<br clear="all"><br clear="all">

<div class="vpb_lebels" style="padding:0px;" align="left">Email Address:</div>
<div class="vpb_lebels_fields" align="left"><?php echo $_SESSION['validemail']; ?></div>
<br clear="all"><br clear="all">

</div>

<!-- Profile Page Code Ends -->


<p style="margin-bottom:200px;">&nbsp;</p>
</center>
</body>
</html>

<?php
}
else
{
	header("location: login/login.php"."?".$QUERY_STRINg);
}

?>
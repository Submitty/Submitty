<?php
session_start(); //You must start session or declare this variable at the very top and first line of your website page

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
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Homework Server Login Beta Ver</title>





<!-- Required header files -->
<script type="text/javascript" src="js/jquery_1.5.2.js"></script>
<script type="text/javascript" src="js/vpb_save_details.js"></script>
<link href="css/style.css" rel="stylesheet" type="text/css">






</head>
<body>
<br clear="all"><center><div style="font-family:Verdana, Geneva, sans-serif; font-size:24px;">Homework Server Login Beta Ver</div><br clear="all" /><br clear="all" /><br clear="all">











<!-- Password Request and Reset Page Code Begins -->
<?php
if(array_key_exists("uid", $_GET) && array_key_exists("ufn", $_GET) && !empty($_GET["uid"]) && !empty($_GET["ufn"]))
{
	$decrypted_fullname = base64_decode(trim(strip_tags($_GET['ufn'])));
	$decrypted_username = base64_decode(trim(strip_tags($_GET['uid'])));
		
	if(isset($_SESSION['vpb_fullname']) && isset($_SESSION['vpb_username']) && isset($_SESSION['vpb_email']))
	{
		if($_SESSION['vpb_fullname'] == $decrypted_fullname && $_SESSION['vpb_username'] == $decrypted_username)
		{
?>

<input type="hidden" id="hidden_username" value="<?php echo $decrypted_username; ?>" />
<div id="vasplus_programming_blog_wrapper" style="width:450px; padding-left:10px;">
<div class="" align="left" style="width:160px;font-family:Verdana, Geneva, sans-serif; font-size:16px; font-weight:bold;">Reset Password</div><br />
<div class="vpb_lebels_fields" align="left" style="padding-top:3px;">Welcome back <b><?php echo $decrypted_fullname; ?></b>, please complete this form to reset your password.</div><br clear="all"><br clear="all"><br />

<div class="vpb_lebels" align="left" style="width:150px;padding-top:8px;">Desired New Password:</div>
<div class="vpb_lebels_fields" align="left"><input type="password" id="new_password" class="vasplus_blog_form_opt" /></div><br clear="all"><br clear="all">

<div class="vpb_lebels" align="left" style="width:150px;padding-top:8px;">Verify New Password:</div>
<div class="vpb_lebels_fields" align="left"><input type="password" id="verify_new_password" class="vasplus_blog_form_opt" /></div><br clear="all"><br clear="all"><br clear="all">

<div class="vpb_lebels" align="left" style="width:150px;">&nbsp;</div>
<div style="width:300px;float:left;" align="left">
<a href="javascript:void(0);" onclick="vpb_reset_password();" class="vpb_general_button">Save Changes</a>
<a href="login.php" class="vpb_general_button">Back to Login</a></div><br clear="all">
<div class="vpb_lebels" align="left" style="width:150px;">&nbsp;</div>
<div class="vpb_lebels_fields" align="left" id="reset_password_status"></div><br clear="all">

</div>

	<?php
		}
		else
		{
			?>
        <div style="width:450px; padding:10px; padding-top:0px;" id="vasplus_programming_blog_wrapper">
        <div class="info" align="left" style="line-height:20px;">Hello <b><?php echo $decrypted_fullname; ?></b><br /><br />It seems the available session does not match with the link you have just clicked or provided therefore, you have to request for a new link to enable you reset your password. <br /><br />Please <a href="forgot_password.php" style="text-decoration:none;"><font style="color:#33C">Click Here</font></a> to reset for another link<br /><br />Thank You!</div>
        </div>
		<?php
		}
	}
	else
	{
		?>
        <div style="width:450px; padding:10px; padding-top:0px;" id="vasplus_programming_blog_wrapper">
        <div class="info" align="left" style="line-height:20px;">Hello <b><?php echo $decrypted_fullname; ?></b><br /><br />It appears the link you have just clicked or provided has exceeded the required time allocated for your password reset which means, you did not follow the link sent to your email address to reset your forgotten or lost password within 30 minutes starting from the time you requested for a link to reset your lost password therefore, it has expired. <br /><br />Please <a href="forgot_password.php" style="text-decoration:none;"><font style="color:#33C">Click Here</font></a> to reset for another link<br /><br />Thank You!</div>
        </div>
		<?php
	}
}
else
{
	?>

<div style="width:450px;" id="vasplus_programming_blog_wrapper">
<div class="" align="left" style="width:160px; float:left;font-family:Verdana, Geneva, sans-serif; font-size:16px; font-weight:bold;">Forgot Password</div>
<div class="vpb_lebels_fields" style="padding-top:2px;" align="left">Please enter your account username below.</div><br clear="all"><br clear="all">

<div class="vpb_lebels" style="width:160px;" align="left">Your Account Username:</div>
<div class="vpb_lebels_fields" align="left"><input type="text" id="account_username" class="vasplus_blog_form_opt" /></div><br clear="all"><br clear="all">
<div class="vpb_lebels" style="width:160px;" align="left">&nbsp;</div>
<div style="width:250px;float:left;" align="left">
<a href="javascript:void(0);" onclick="vpb_forgot_password();" class="vpb_general_button">Submit</a>
<a href="login.php" class="vpb_general_button">Back to Login</a></div><br clear="all">
<div class="vpb_lebels_fields" align="left" id="forgot_password_status"></div><br clear="all"><br />
</div>

<?php
}
?>
<!-- Password Request and Reset Page Code Ends -->















<p style="margin-bottom:400px;">&nbsp;</p>
</center>
</body>
</html>
<?php
/********************************************************************************************************************
* This script is brought to you by Vasplus Programming Blog by whom all copyrights are reserved.
* Website: www.vasplus.info
* Email: info@vasplus.info
* Please, do not remove this information from the top of this page.
*********************************************************************************************************************/
session_start();//You must start session or declare this variable at the very top and first line of your website page

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
ini_set('error_reporting', E_NONE);
$server_or_host = $_SERVER['HTTP_HOST'];


//The below two fields are used in the email sent during password request process
$company_email_address = "info@$server_or_host"; //Replace this field with your personal or company email address
$company_name = "Sign-up and Login System without SQL Database"; //Replace this field with your company or website name

$vpb_error = ''; //Please leave this field alone. Do not touch it otherwise you will have problems with Error Message Displays on your system
			  

include "vpb_database.php"; //This is the file that contains the database filename for this system
$vpb_server_path = getcwd();

//Check to see that the file name to save and retrieve user details is set and that the file exist otherwise, set and dreate the file to avoid errors
if(name_of_file_to_save_user_details == "")
{
	if(file_exists($vpb_server_path."/vpb_database.docx"))
	{
		$vpb_array_line = array();
		array_unshift( $vpb_array_line, "<?php \n/*Defined below is your database file, the file where all your users details are saved*/\n/*if you wish to see the content of your database file then change the file vpb_database.docx to vpb_database.txt below*/\ndefine('name_of_file_to_save_user_details', 'vpb_database.docx');/*File to save and retrieve user details*/\n?>" );
		$vpb_file_ip = implode("\n",$vpb_array_line);

		$vpb_create_write_file = @fopen("vpb_database.php", "w");
		if ($vpb_create_write_file) 
		{
		  fwrite($vpb_create_write_file, $vpb_file_ip);
		  @fclose($vpb_create_write_file);
		}
	}
	else
	{
		$vpb_array_line = array();
		array_unshift( $vpb_array_line, "<?php \n/*Defined below is your database file, the file where all your users details are saved*/\n/*if you wish to see the content of your database file then change the file vpb_database.docx to vpb_database.txt below*/\ndefine('name_of_file_to_save_user_details', 'vpb_database.docx');/*File to save and retrieve user details*/\n?>" );
		$vpb_file_ip = implode("\n",$vpb_array_line);

		@fopen("vpb_database.docx", "w");
		$vpb_create_write_file = @fopen("vpb_database.php", "w");
		if ($vpb_create_write_file) 
		{
		  fwrite($vpb_create_write_file, $vpb_file_ip);
		  @fclose($vpb_create_write_file);
		}
	}
}
elseif(!file_exists($vpb_server_path."/".name_of_file_to_save_user_details))
{	
	@fopen(name_of_file_to_save_user_details, "w");
}

//This function converts the fullnames of users to Uppercase every first letter of their names
function vpb_format_fullnames($name=NULL) 
{
	if (empty($name))
		return false;
	$name = strtolower($name);
	$names_array = explode('-',$name);
	for ($i = 0; $i < count($names_array); $i++) {	
		if (strncmp($names_array[$i],'mc',2) == 0 || ereg('^[oO]\'[a-zA-Z]',$names_array[$i])) 
		{
			$names_array[$i][2] = strtoupper($names_array[$i][2]);
		}
		$names_array[$i] = ucfirst($names_array[$i]);
	}
	$name = implode('-',$names_array);
	return ucwords($name);
}

if(isset($_POST['page']) && !empty($_POST['page']))
{
	//*************************************************The sign up process starts from here**********************************************************
	if($_POST['page'] == "signup")
	{
		if(isset($_POST['vpb_fullname']) && isset($_POST['vpb_username']) && isset($_POST['vpb_email']) && isset($_POST['vpb_passwd']) && !empty($_POST['vpb_fullname']) && !empty($_POST['vpb_username']) && !empty($_POST['vpb_email']) && !empty($_POST['vpb_passwd']))
		{
			$fullname = vpb_format_fullnames(trim(strip_tags($_POST['vpb_fullname'])));
			$username = trim(strip_tags($_POST['vpb_username']));
			$email = trim(strip_tags($_POST['vpb_email']));
			$passwd = trim(strip_tags($_POST['vpb_passwd']));
			$encrypted_user_password = md5($passwd);
			
			if($fullname == "")
			{
				echo '<div class="info">Please enter your fullname in the required field to proceed.</div>';
			}
			elseif($username == "")
			{
				echo '<div class="info">Please enter your desired username to proceed.</div>';
			}
			elseif($email == "")
			{
				echo '<div class="info">Please enter your email address to proceed.</div>';
			}
			elseif($passwd == "")
			{
				echo '<div class="info">Please enter your desired password to proceed.</div>';
			}
			elseif (strlen($passwd) < 4)
			{
				echo '<div class="info">Sorry, password must not be less than 4 characters in length.</div>';
			}
			elseif(!@fopen(name_of_file_to_save_user_details,"a+"))
			{
				echo '<div class="info">Sorry, we could not open the required database file to create your account. Please try again or contact this website admin to report this error message if the problem persist. Thanks.</div>';
			}
			else
			{
				$vpb_database = fopen(name_of_file_to_save_user_details,"a+");
				rewind($vpb_database);
			
				while (!feof($vpb_database)) 
				{
					$vpb_get_db_lines = fgets($vpb_database);
					$vpb_fetch_detail = explode('::::::::::', $vpb_get_db_lines);
					
					/*User Information are shown below:*/
					//$vpb_fetch_detail[0] = Fullname
					//$vpb_fetch_detail[1] = Username
					//$vpb_fetch_detail[2] = Email Address
					//$vpb_fetch_detail[3] = Password
					
					$vpb_account_username = base64_decode(trim(strip_tags($vpb_fetch_detail[1])));
					$vpb_account_email = base64_decode(trim(strip_tags($vpb_fetch_detail[2])));
					
					// Username validation to avoid duplicates
					if ($vpb_account_username == $username) 
					{
						$vpb_error = '<div class="info">Sorry, the username <b>'.$username.'</b> has already been taken by someone else. <br>Please enter a different username to proceed. Thanks.</div>';
						break;
					}
					// Email Address validation to avoid duplicates
					elseif ($vpb_account_email == $email) 
					{
						$vpb_error = '<div class="info">Sorry, <b>'.$email.'</b> already exist on this system and duplicate email addresses are not allowed for security reasons. <br>Please enter a different email address to proceed. Thanks.</div>';
						break;
					}
				}
				
				//If the user is validated and every thing is alright, then save the user details and display success message to the user 
				//otherwise, display error message to the user
				if ($vpb_error == '')
				{
					//Encrypt user information with Base64 before saving them to a file
					if(fwrite($vpb_database, "\r\n".base64_encode($fullname)."::::::::::".base64_encode($username)."::::::::::".base64_encode($email)."::::::::::".base64_encode($encrypted_user_password).""))
					{
						echo '<font style="font-size:0px;">completed</font>';
						echo '<div class="info">Congrats <b>'.$fullname.'</b>, you have registered successful. <br>You may now click on the login button to log into your account.<br>Thanks.</div>';
					}
					else
					{
						echo '<div class="info">Sorry, your account creation was unsuccessful, please try again (1).</div>';
					}
				}
				else
				{
					echo $vpb_error;
				}
				fclose($vpb_database);
			}
		}
		else
		{
			echo '<div class="info">Sorry, your account creation was unsuccessful, please try again (2).</div>';
		}
	}
	//***************************************************The sign up process ends here**********************************************************
	
	


	
	//*************************************************The login process starts from here**********************************************************
	elseif($_POST['page'] == "login")
	{
		if(isset($_POST['vpb_username']) && isset($_POST['vpb_passwd']) && !empty($_POST['vpb_username']) && !empty($_POST['vpb_passwd']))
		{
			$username = trim(strip_tags($_POST['vpb_username']));
			$passwd = trim(strip_tags($_POST['vpb_passwd']));
			$encrypted_user_password = md5($passwd);
			
			if($username == "")
			{
				echo '<div class="info">Please enter your account username to proceed.</div>';
			}
			elseif($passwd == "")
			{
				echo '<div class="info">Please enter your account password to proceed.</div>';
			}
			elseif(!@fopen(name_of_file_to_save_user_details,"r"))
			{
				echo '<div class="info">Sorry, we could not open the required database file to log you into your account. Please try again or contact this website admin to report this error message if the problem persist. Thanks.</div>';
			}
			else
			{
				$vpb_databases = fopen(name_of_file_to_save_user_details,"r");
				rewind($vpb_databases);
			
				while (!feof($vpb_databases)) 
				{
					$vpb_get_db_line = fgets($vpb_databases);
					$vpb_fetch_details = explode('::::::::::', $vpb_get_db_line);
					
					//User Information are shown below:
					//$vpb_fetch_detail[0] = Fullname
					//$vpb_fetch_detail[1] = Username
					//$vpb_fetch_detail[2] = Email Address
					//$vpb_fetch_detail[3] = Password
					
					$vpb_account_username = base64_decode(trim($vpb_fetch_details[1]));
					$vpb_account_password = base64_decode(trim($vpb_fetch_details[3]));
					
					// Validate Username
					if (!empty($vpb_account_username) && !empty($vpb_account_password) && $vpb_account_username == $username) 
					{
						// Username is valid therefore, validate user password
						if ($vpb_account_password == $encrypted_user_password)
						{
							// User details are validated therefore, create required sessions for the logged in user
							//Don't forget that the user information were encrypted before they were saved to a file hence the decoding below
							$_SESSION['validfullname'] = base64_decode(trim(strip_tags($vpb_fetch_details[0])));
							$_SESSION['validusername'] = base64_decode(trim(strip_tags($vpb_fetch_details[1])));
							$_SESSION['validemail'] = base64_decode(trim(strip_tags($vpb_fetch_details[2])));
							$_SESSION['validpassword'] = base64_decode(trim(strip_tags($vpb_fetch_details[3])));
						}
						else
						{
							$vpb_error = '<div class="info">Sorry, the information you have provided are incorrect. Please enter your valid information to access this system. Thanks.</div>';
							break;
						}
					}
				}
				
				//If the user is validated and every thing is alright, then grant the user access to the secured account page 
					//otherwise, display error message to the user
				if ($vpb_error == '')
				{
					if(isset($_SESSION['validfullname']) && isset($_SESSION['validusername']) && isset($_SESSION['validemail']) && isset($_SESSION['validpassword']))
					{
						echo '<font style="font-size:0px;">completed</font>';
					}
					else
					{
						echo '<div class="info">Sorry, it seems your information are incorrect and as a result, we are unable to create the required sessions to log you into your account. Please enter your valid account information to proceed. Thanks.</div>';
					}
					
				}
				else
				{
					echo $vpb_error;
				}
				fclose($vpb_databases);
			}
		}
		else
		{
			echo '<div class="info">Sorry, we could not get your valid username and password to process your login. Please try again or contact this website admin to report this error message if the problem persist. Thanks.</div>';
		}
	}
	//*********************************************************The login process ends here**********************************************************
	
	
	
	//*************************************************The forgot password process starts from here**************************************************
	elseif($_POST['page'] == "forgot_password")
	{
		if(isset($_POST['vpb_username']) && !empty($_POST['vpb_username']))
		{
			$username = trim(strip_tags($_POST['vpb_username']));
			
			if($username == "")
			{
				echo '<div class="info">Please enter your account username in the specified field above to reset your forgotten password. Thanks.</div>';
			}
			elseif(!@fopen(name_of_file_to_save_user_details,"r"))
			{
				echo '<div class="info">Sorry, we could not open the required database file to verify your account. Please try again or contact this website admin to report this error message if the problem persist. Thanks.</div>';
			}
			else
			{
				$vpb_databases = fopen(name_of_file_to_save_user_details,"r");
				rewind($vpb_databases);
			
				while (!feof($vpb_databases)) 
				{
					$vpb_get_db_line = fgets($vpb_databases);
					$vpb_fetch_details = explode('::::::::::', $vpb_get_db_line);
					
					//User Information are shown below:
					//$vpb_fetch_detail[0] = Fullname
					//$vpb_fetch_detail[1] = Username
					//$vpb_fetch_detail[2] = Email Address
					//$vpb_fetch_detail[3] = Password
					
					$vpb_account_username = base64_decode(strip_tags($vpb_fetch_details[1]));
					
					// Validate Username
					if(!empty($vpb_account_username))
					{
						if($vpb_account_username == $username) 
						{
							// Username is validated
							
							//Don't forget that the user information were encrypted before they were saved to a file hence the decoding below
							$_SESSION['vpb_fullname'] = base64_decode(trim(strip_tags($vpb_fetch_details[0])));
							$_SESSION['vpb_username'] = base64_decode(trim(strip_tags($vpb_fetch_details[1])));
							$_SESSION['vpb_email'] = base64_decode(trim(strip_tags($vpb_fetch_details[2])));
						}
					}
				}
				
				//If the user is validated and his or her username is correct then process the reset password link 
				//and send to the email address associated with this user account
				//otherwise, display error message to the user
				if ($vpb_error == '')
				{
					if(isset($_SESSION['vpb_fullname']) && isset($_SESSION['vpb_username']) && isset($_SESSION['vpb_email']))
					{
						$the_date = date("F jS Y");
						$the_time = date('g:i A');
						$ForgotPasswordYear = date("Y");
						$fullname = $_SESSION['vpb_fullname'];
						$username = $_SESSION['vpb_username'];
						$email = $_SESSION['vpb_email'];
						
						$encrypted_fullname = base64_encode($_SESSION['vpb_fullname']);
						$encrypted_username = base64_encode($_SESSION['vpb_username']);
						$encrypted_email = base64_encode($_SESSION['vpb_email']);
						
						//Send a HTML email to enable user change or reset forgotten password
$message = <<<EOF

  <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
   <html xmlns="http://www.w3.org/1999/xhtml">
   <head>
  <title>System</title>
   </head>
      <body>
	 <table bgcolor="#F9F9F9" align="left" cellpadding="6" cellspacing="6" width="100%" border="0">
     <tr>
    <td valign="top" colspan="2">
            <p><font style='font-family:Verdana, Geneva, sans-serif; font-size:12px; color:black;'><br>Dear $fullname<br><br>
              This email has been sent to you as a result of the request you made to send you a new link to enable you reset or change your lost or forgotten password dated $the_date and at $the_time</font>.</p>
            <br>
			<p><font style='font-family:Verdana, Geneva, sans-serif; font-size:12px; color:black;'>Please click on the below link to reset or change your password.<br /><br />If you did not request for any link then ignore this email otherwise the link will expire in 30 minutes starting from the time this email was sent to you.<br /><br />
            ------------------------------------------------------------------------------------------------<br /><br />
            <a href="http://$server_or_host/demos/signup_and_login_without_db_ajax_php/forgot_password.php?uid=$encrypted_username&ufn=$encrypted_fullname"><font style="font-family:Verdana, Geneva, sans-serif; font-size:12px; color:blue;">$encrypted_fullname@$encrypted_username.$encrypted_email</font></a></span><br /><br />------------------------------------------------------------------------------------------------<br /><br />
            </font></p>
			<p><font style="font-family:Verdana, Geneva, sans-serif; font-size:12px; color:black;">
            Regards,<br /><br />
			<a href="http://$server_or_host" style="text-decoration:none;"><font style="font-family:Verdana, Geneva, sans-serif; font-size:12px; color:blue;">$company_name</font></a></span>
			</font>
            </p><br /><br />
       </td>
  </tr>
  <tr>
  <td colspan="2" align="center">
  <table height="40" width="100%" border="0" align="center" cellpadding="0" cellspacing="0" bgcolor="#F6F6F6" style="height:30;padding:0px;border:1px solid #EAEAEA;">
  <tr>
    <td><p align='center'><font style="font-family:Verdana, Geneva, sans-serif; font-size:10px;color:black;">Copyright &copy; $ForgotPasswordYear | All Rights Reserved.</font></p></td>
  </tr>
</table>
</td>
</tr>
</table>
      </body>
   </html>
EOF;
/* END OF MESSAGE */


   // THIS EMAIL IS THE SENDER EMAIL ADDRESS
   $from = $company_email_address;
   
   // SET A SUBJECT OF YOUR CHOICE
   $subject = 'Reset Password Link From '.$company_name;
            
    // SET UP THE EMAIL HEADERS
    $headers      = "From: $company_name <$from>\r\n";
    $headers   .= "Content-type: text/html; charset=iso-8859-1\r\n";
   
          
   $headers   .= "Message-ID: <".time().rand(1,1000)."@".$_SERVER['SERVER_NAME'].">". "\r\n";   
   
   
   //   LETS SEND THE EMAIL
   if(mail($email, $subject, $message, $headers))
   {
	   echo '<font style="font-size:0px;">completed</font>';
	   echo '<div class="info" align="left">Dear <b>'.$fullname.'</b>,<br><br>For security reasons, a new link to enable you reset your forgotten or lost password has been sent to<font style="color:blue;"> '.$email.'</font><br><br>Please log into the specified email address to reset your password if you are truly the owner of the account that is associated with the detail you have just submitted.<br><br>Thank You!</div>';
   }
   else
   {
	   echo '<div class="info" align="left">Hello <b>'.$fullname.'</b>,<br><br>Sorry, a link to enable you change or reset your forgotten password could not be sent to you at the moment due to connection problems. <br><br>Please try again or contact this website admin to report this error message if the problem persist.<br><br>Thank You!</div>';
   }
					}
					else
					{
						echo '<div class="info">Sorry, it seems your username is incorrect and as a result, we are unable to process your forgotten password request. Please enter your valid account username to proceed. Thanks.</div>';
					}
					
				}
				else
				{
					echo $vpb_error;
				}
				fclose($vpb_databases);
			}
		}
		else
		{
			echo '<div class="info">Sorry, we could not get your valid account username to process your forgotten password request. Please try again or contact this website admin to report this error message if the problem persist. Thanks.</div>';
		}
	}
	//************************************************The forgot password process ends here*****************************************************
	
	
	//*************************************************The reset password process starts from here**************************************************
	elseif($_POST['page'] == "reset_password")
	{
		if(isset($_POST['hidden_username']) && isset($_POST['new_password']) && !empty($_POST['hidden_username']) && !empty($_POST['new_password']))
		{
			$username = trim(strip_tags($_POST['hidden_username']));
			$new_passwd = trim(strip_tags($_POST['new_password']));
			$encrypted_user_new_password = md5($new_passwd);
			
			if($username == "")
			{
				echo '<div class="info">Sorry, the hidden username field is missing from this page. Please place input type="hidden" id="hidden_username" value="Hidden username must be declared here" on that page to proceed. Thanks.</div>';
			}
			elseif($new_passwd == "")
			{
				echo '<div class="info">Please enter your desired new password in the specified field above to reset your password. Thanks.</div>';
			}
			elseif(strlen($new_passwd) < 5)
			{
				echo '<div class="info">Sorry, your new password must not be less than 5 characters in length for security reasons please. Thanks.</div>';
			}
			elseif(!@fopen(name_of_file_to_save_user_details,"r"))
			{
				echo '<div class="info">Sorry, we could not open the required database file to verify your account. Please try again or contact this website admin to report this error message if the problem persist. Thanks.</div>';
			}
			else
			{
				$vpb_databases = fopen(name_of_file_to_save_user_details,"r"); //"a+"
				rewind($vpb_databases);
				
			
				while (!feof($vpb_databases)) 
				{
					$vpb_get_db_line = fgets($vpb_databases);
					$vpb_fetch_details = explode('::::::::::', $vpb_get_db_line);
					
					//User Information are shown below:
					//$vpb_fetch_detail[0] = Fullname
					//$vpb_fetch_detail[1] = Username
					//$vpb_fetch_detail[2] = Email Address
					//$vpb_fetch_detail[3] = Password
					
					$vpb_account_username = base64_decode(trim($vpb_fetch_details[1]));
					
					// Validate Username
					if ($vpb_account_username == $username) 
					{
						$vpb_old_account_password = trim(strip_tags($vpb_fetch_details[3]));
						
						$vpb_databases_file_name = name_of_file_to_save_user_details;
						$vpb_file_handler = fopen($vpb_databases_file_name,"r");
						$vpb_content_reader = fread($vpb_file_handler,filesize($vpb_databases_file_name));
						
						$vpb_content_reader = str_replace($vpb_old_account_password, base64_encode($encrypted_user_new_password), $vpb_content_reader);
						
						$vpb_file_handler = fopen($vpb_databases_file_name,"w");
						
						if(fwrite($vpb_file_handler,$vpb_content_reader))
						{
							$_SESSION['vpb_reset_fullname'] = base64_decode(trim(strip_tags($vpb_fetch_details[0])));
							$_SESSION['vpb_reset_username'] = base64_decode(trim(strip_tags($vpb_fetch_details[1])));
							$_SESSION['vpb_reset_email'] = base64_decode(trim(strip_tags($vpb_fetch_details[2])));
							fclose($vpb_file_handler);
						}
						else
						{
							$vpb_error = '<div class="info">Sorry, your account password could not be changed at this time. Please try again later or contact this website admin to report the error message if this problem persist (1). Thanks.</div>';
							break;
						}
					}
				}
				
				//If there is no error then password changed successfully therefore, display success message
				if ($vpb_error == '')
				{
					if(isset($_SESSION['vpb_reset_fullname']) && isset($_SESSION['vpb_reset_username']) && isset($_SESSION['vpb_reset_email']))
					{
						echo '<font style="font-size:0px;">completed</font>';
						echo '<div class="info" align="left">Congrats <b>'.$_SESSION['vpb_reset_fullname'].'</b>,<br><br>Your account password has been changed successfully. <br><br>If you did not see the Back to Login button to click on it so as to login with your new password, please refresh this page to re-login with your new account password.<br><br>Thank You!</div>';
						session_unset();
						session_destroy();
					}
					else
					{
						echo '<div class="info">Sorry, your account password could not be changed at this time. Please try again later or contact this website admin to report the error message if this problem persist (2). Thanks.</div>';
					}
				}
				else
				{
					echo $vpb_error;
				}
				fclose($vpb_databases);
			}
		}
		else
		{
			echo '<div class="info">Sorry, your account password could not be changed at this time. Please try again later or contact this website admin to report the error message if this problem persist (3). Thanks.</div>';
		}
	}
	//*************************************************The reset password process ends from here**************************************************
	
	else
	{
		echo '<div class="info">Sorry, we could not identify the page you were trying to access. Please try again or contact this website admin to report this error message if the problem persist. Thanks.</div>';
	}
}
?>
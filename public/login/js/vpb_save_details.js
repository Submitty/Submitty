/********************************************************************************************************************
* This script is brought to you by Vasplus Programming Blog by whom all copyrights are reserved.
* Website: www.vasplus.info
* Email: info@vasplus.info
* Please, do not remove this information from the top of this page.
*********************************************************************************************************************/


//Users Registration Function
function vpb_users_registration() 
{
	var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
	var vpb_fullname = $("#fullname").val();
	var vpb_username = $("#username").val();
	var vpb_email = $("#email").val();
	var vpb_passwd = $("#passwd").val();
	
	if(vpb_fullname == "")
	{
		$("#signup_status").html('<div class="info">Please enter your fullname in the required field to proceed.</div>');
		$("#fullname").focus();
	}
	else if(vpb_username == "")
	{
		$("#signup_status").html('<div class="info">Please enter your desired username to proceed.</div>');
		$("#username").focus();
	}
	else if(vpb_email == "")
	{
		$("#signup_status").html('<div class="info">Please enter your email address to proceed.</div>');
		$("#email").focus();
	}
	else if(reg.test(vpb_email) == false)
	{
		$("#signup_status").html('<div class="info">Please enter a valid email address to proceed.</div>');
		$("#email").focus();
	}
	else if(vpb_passwd == "")
	{
		$("#signup_status").html('<div class="info">Please enter your desired password to go.</div>');
		$("#passwd").focus();
	}
	else
	{
		var dataString = 'vpb_fullname='+ vpb_fullname + '&vpb_username=' + vpb_username + '&vpb_email=' + vpb_email + '&vpb_passwd=' + vpb_passwd + '&page=signup';
		$.ajax({
			type: "POST",
			url: "vpb_save_details.php",
			data: dataString,
			cache: false,
			beforeSend: function() 
			{
				$("#signup_status").html('<br clear="all"><br clear="all"><div align="left"><font style="font-family:Verdana, Geneva, sans-serif; font-size:12px; color:black;">Please wait</font> <img src="images/loadings.gif" alt="Loading...." align="absmiddle" title="Loading...."/></div><br clear="all">');
			},
			success: function(response)
			{
				var vpb_result_broght = response.indexOf('completed');
				if (vpb_result_broght != -1 ) 
				{
					$("#fullname").val('');
					$("#username").val('');
					$("#email").val('');
					$("#passwd").val('');
					$("#signup_status").hide().fadeIn('slow').html(response);
				}
				else
				{
					$("#signup_status").hide().fadeIn('slow').html(response);
				}
				
			}
		});
	}
}

//Users Login Function
function vpb_users_login() 
{
	var vpb_username = $("#username").val();
	var vpb_passwd = $("#passwd").val();
	
	if(vpb_username == "")
	{
		$("#login_status").html('<div class="info">Please enter your account username to proceed.</div>');
		$("#username").focus();
	}
	else if(vpb_passwd == "")
	{
		$("#login_status").html('<div class="info">Please enter your account password to go.</div>');
		$("#passwd").focus();
	}
	else
	{
		var dataString = 'vpb_username=' + vpb_username + '&vpb_passwd=' + vpb_passwd + '&page=login';
		$.ajax({
			type: "POST",
			url: "vpb_save_details.php",
			data: dataString,
			cache: false,
			beforeSend: function() 
			{
				$("#login_status").html('<br clear="all"><br clear="all"><div align="left"><font style="font-family:Verdana, Geneva, sans-serif; font-size:12px; color:black;">Please wait</font> <img src="images/loadings.gif" alt="Loading...." align="absmiddle" title="Loading...."/></div><br clear="all">');
			},
			success: function(response)
			{
				var vpb_result_broght = response.indexOf('completed');
				if (vpb_result_broght != -1 ) 
				{
					$("#login_status").html('');
					$("#username").val('');
					$("#passwd").val('');
					window.location.replace("../index.php?course=csci1200");
					//window.location.replace(QueryURL);
					
				}
				else
				{
					$("#login_status").hide().fadeIn('slow').html(response);
				}
				
			}
		});
	}
}

//Forgot Password Function
function vpb_forgot_password() 
{
	var vpb_username = $("#account_username").val();
	
	if(vpb_username == "")
	{
		$("#forgot_password_status").html('<div class="info">Please enter your account username in the specified field above to reset your forgotten password. Thanks.</div>');
		$("#account_username").focus();
	}
	else
	{
		var dataString = 'vpb_username=' + vpb_username + '&page=forgot_password';
		$.ajax({
			type: "POST",
			url: "vpb_save_details.php",
			data: dataString,
			cache: false,
			beforeSend: function() 
			{
				$("#forgot_password_status").html('<br clear="all"><br clear="all"><div align="left"><font style="font-family:Verdana, Geneva, sans-serif; font-size:12px; color:black;">Please wait</font> <img src="images/loadings.gif" alt="Loading...." align="absmiddle" title="Loading...."/></div><br clear="all">');
			},
			success: function(response)
			{
				var vpb_result_broght = response.indexOf('completed');
				if (vpb_result_broght != -1 ) 
				{
					$("#account_username").val('');
					$("#forgot_password_status").hide().fadeIn('slow').html(response);
				}
				else
				{
					$("#forgot_password_status").hide().fadeIn('slow').html(response);
				}
			}
		});
	}
}



//Reset Password Function
function vpb_reset_password() 
{
	var new_password = $("#new_password").val();
	var verify_new_password = $("#verify_new_password").val();
	var hidden_username = $("#hidden_username").val();
	
	if(!hidden_username || hidden_username == "")
	{
		$("#reset_password_status").html('<div class="info">Sorry, the hidden username field is missing from this page. Please place input type="hidden" id="hidden_username" value="Hidden username must be declared here" on that page to proceed. Thanks.</div>');
	}
	else if(new_password == "")
	{
		$("#reset_password_status").html('<div class="info">Please enter your desired new password in the specified field above to reset your password. Thanks.</div>');
		$("#new_password").focus();
	}
	else if(verify_new_password == "")
	{
		$("#reset_password_status").html('<div class="info">Please verify your new password to proceed. Thanks.</div>');
		$("#verify_new_password").focus();
	}
	else if(new_password != verify_new_password)
	{
		$("#reset_password_status").html('<div class="info">Passwords did not match. Both New password and Verify password fields must be the same to proceed please. Thanks.</div>');
		$("#verify_new_password").focus();
	}
	else
	{
		var dataString = 'hidden_username=' + hidden_username + '&new_password=' + new_password + '&page=reset_password';
		$.ajax({
			type: "POST",
			url: "vpb_save_details.php",
			data: dataString,
			cache: false,
			beforeSend: function() 
			{
				$("#reset_password_status").html('<br clear="all"><br clear="all"><div align="left"><font style="font-family:Verdana, Geneva, sans-serif; font-size:12px; color:black;">Please wait</font> <img src="images/loadings.gif" alt="Loading...." align="absmiddle" title="Loading...."/></div><br clear="all">');
			},
			success: function(response)
			{
				var vpb_result_broght = response.indexOf('completed');
				if (vpb_result_broght != -1 ) 
				{
					$("#new_password").val('');
	                $("#verify_new_password").val('');
					$("#reset_password_status").hide().fadeIn('slow').html(response);
				}
				else
				{
					$("#reset_password_status").hide().fadeIn('slow').html(response);
				}
			}
		});
	}
}
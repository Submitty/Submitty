<?php
	$dbconn = mysqli_connect("localhost", "root", "ipreferstrawberry", "hwserver");
	$getsubsquery = "SELECT * FROM subcounts WHERE username=". "'" . $_SERVER['REMOTE_USER'] . "'";
	$queryresult = mysqli_query($dbconn, $getsubsquery);
	$queryrow = mysqli_fetch_array($queryresult);
?>
<html>
<body>
<h1>PAY NO ATTENTION TO HOW UGLY THIS PAGE IS</h1>
<h2>Instead, submit some homework in a ZIP file below.  You are logged in as user: <b><?php echo $_SERVER['REMOTE_USER']; ?></b></h2>
<p>You have made <?php echo $queryrow[totalsubs]; ?> submissions.</p>
<form action="uploader.php" method="post"
enctype="multipart/form-data">
<label for="file">Filename:</label>
<input type="file" name="file" id="file"><br>
<input type="submit" name="submit" value="Submit">
</form>
<p>Connection info:<br /><?php echo $dbconn->host_info; ?></p>
</body>
</html>
<?php mysqli_close($dbconn); ?>
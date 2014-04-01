<html>
<body>
<h1>So You Want To Submit Some CS Homework?</h1>
<p>You are logged in as user <?php echo $_SERVER['REMOTE_USER']; ?></p>

<form action="uploader.php" method="post"
enctype="multipart/form-data">
<label for="file">Filename:</label>
<input type="file" name="file" id="file"><br>
<input type="submit" name="submit" value="Submit">
</form>

</body>
</html>
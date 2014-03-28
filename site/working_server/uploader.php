<?php
$username = $_SERVER['REMOTE_USER'];
$temp = explode(".", $_FILES["file"]["name"]);
$extension = end($temp);
if ($_FILES["file"]["size"] < 2000000 && $extension=="cpp")
	{
	if ($_FILES["file"]["error"] > 0)
    {
		echo "Return Code: " . $_FILES["file"]["error"] . "<br>";
    }
else
	{
	echo "Upload: " . $_FILES["file"]["name"] . "<br>";
	echo "Type: " . $_FILES["file"]["type"] . "<br>";
	echo "Size: " . ($_FILES["file"]["size"] / 1024) . " kB<br>";
	echo "Temp file: " . $_FILES["file"]["tmp_name"] . "<br>";
	if (file_exists("upload/" . $_FILES["file"]["name"]))
		{
			echo $_FILES["file"]["name"] . " already exists.";
		}
		else
		{
			move_uploaded_file($_FILES["file"]["tmp_name"],
			"upload/" . $_FILES["file"]["name"]);
			echo "Stored in: " . "upload/" . $_FILES["file"]["name"];
		}
	}
	exec('g++ -g /var/www/upload/' . $_FILES["file"]["name"] . ' -o /var/www/compiled/main.out > /var/www/compiled/gppout.txt');
	exec('/var/www/compiled/main.out > /var/www/compiled/output.txt');
	}
else
	{
	echo "Invalid file";
	}
$compfile = "/var/www/compiled/gppout.txt";
$compcontent = implode(file($compfile));
echo $compcontent;

$outfile = "/var/www/compiled/output.txt";
$outcontent = implode(file($outfile)); ?>
<br /><br />
<?php
echo $outcontent;
echo $compcontent;
?>
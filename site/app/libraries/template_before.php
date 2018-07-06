
<?php 

$link_other_page = "http://www.cs.rpi.edu/academics/courses/fall17/csci1200/";
$link_this_page = "http://www.cs.rpi.edu/academics/courses/fall17/csci1200/";
$link_https = "https://www.cs.rpi.edu/academics/courses/fall17/csci1200/";

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
   $link_this_page = "https://www.cs.rpi.edu/academics/courses/fall17/csci1200/";
   // SSL connection
}

?>

<?php $which_course = "CSCI 1200 Data Structures"; ?>
<?php $which_semester = "Fall 2017";?>
<?php $is_test_site = FALSE; ?>

<html>
<link href="<?php echo $link_this_page;?>f17_csci1200_main.css" rel="stylesheet"></link>


<title><?php echo $which_course." - ".$which_semester;?></title>
<body height=100% leftmargin=0 rightmargin=0 topmargin=0 bottommargin=0 bgcolor=ffffff>
<!--
<table cellpadding=10 border=0 cellspacing=0 width=100% height=100% background="<?php echo $link_this_page;?>images/sunflowers_background_smaller.jpg">
-->
<table cellpadding=10 border=0 cellspacing=0 width=100% height=100% background="https://www.cs.rpi.edu/academics/courses/fall17/csci1200/images/buttons_smaller.png">



<tr>
<td class=upper_left_corner align=center>
<a href="http://www.cs.rpi.edu">
<img border = 0 src="<?php echo $link_this_page;?>images/rpi_cs_transparent_black.png" width=200 height=40>
</a>
</td>
<td class=top_and_left_bars width=100% align=right>



<table width=100% border=0>
<tr>
<td align=right width=100%>
<b>
<?php echo $which_course."<br>".$which_semester?>
</b>
</td>
</tr>
</table>

</td>
</tr>

<tr valign=top><td class=top_and_left_bars align=top height=100%>

<p>
<a href="<?php echo $link_other_page;?>index.php">Home</a><br> 
&nbsp;&nbsp;Contact Information<br>
&nbsp;&nbsp;<a href="<?php echo $link_other_page;?>announcements.php">Announcements</a><br>
&nbsp;&nbsp;<a href="https://lms.rpi.edu/">Discussion Forum (LMS)</a>
</p>

<p>
<a href="<?php echo $link_other_page;?>syllabus.php">Syllabus</a><br>
&nbsp;&nbsp;Learning Outcomes<br>
&nbsp;&nbsp;Prerequisites<br>
&nbsp;&nbsp;<a href="<?php echo $link_other_page;?>iclicker.php">iClickers in Lecture</a><br>
&nbsp;&nbsp;<a href="<?php echo $link_other_page;?>grading.php">Course Grades</a>
</p>

<p>
<a href="<?php echo $link_other_page;?>calendar.php">Calendar</a><br>
&nbsp;&nbsp;Lecture notes<br>
&nbsp;&nbsp;Lab materials<br>
&nbsp;&nbsp;Homework<br>
&nbsp;&nbsp;Test reviews 
</p>

<p>
<a href="<?php echo $link_other_page;?>schedule.php">Weekly Schedule</a><br>
&nbsp;&nbsp;Office Hours</a><br>
&nbsp;&nbsp;Lab Times
</p>

<p>
<a href="<?php echo $link_other_page;?>getting_help.php">Getting Help</a><br>
&nbsp;&nbsp;Tutoring<br>
&nbsp;&nbsp;<a href="<?php echo $link_other_page;?>advice_TAs.php">Advice from TAs</a><br>
&nbsp;&nbsp;<a href="<?php echo $link_other_page;?>advice_S14.php">Advice from Students</a>
</p>

<p>
<a href="<?php echo $link_other_page;?>homework.php">Homework</a><br>
&nbsp;&nbsp;Due Date and Time<br>
&nbsp;&nbsp;Late Day Policy<br>
&nbsp;&nbsp;Compilers<br>
&nbsp;&nbsp;<a href="<?php echo $link_https;?>submitty.php">Submitty</a><br>
&nbsp;&nbsp;<a href="<?php echo $link_other_page;?>hw_grading_criteria.php">HW Grading Criteria</a>
</p>

<a href="<?php echo $link_other_page;?>academic_integrity.php">Collaboration Policy & <br> 
Academic Integrity</a><br>


<p>
<a href="<?php echo $link_other_page;?>development_environment.php">C++ Development</a><br>
&nbsp;&nbsp;Code Editors & IDEs<br>
&nbsp;&nbsp;<a href="<?php echo $link_other_page;?>OS_choices.php">OS Choices</a><br>
&nbsp;&nbsp;<a href="<?php echo $link_other_page;?>wsl.php">Install WSL</a><br>
&nbsp;&nbsp;<a href="<?php echo $link_other_page;?>cygwin.php">Install Cygwin</a><br>
<!--&nbsp;&nbsp;<a href="<?php echo $link_other_page;?>mingw.php">Install MinGW</a><br>-->
&nbsp;&nbsp;<a href="<?php echo $link_other_page;?>memory_debugging.php">Memory Debugging</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;Dr. Memory<br>
&nbsp;&nbsp;&nbsp;&nbsp;Valgrind<br>
&nbsp;&nbsp;<a href="<?php echo $link_other_page;?>installation_test.php">Test Your Installation</a>

<p>
<a href="<?php echo $link_other_page;?>references.php">References</a><br>
&nbsp;&nbsp;Optional Textbooks<br>
&nbsp;&nbsp;Web Resources<br>
&nbsp;&nbsp;<a href="<?php echo $link_other_page;?>other_information.php">Misc. C++ Programming</a><br>
&nbsp;&nbsp;&nbsp;&nbsp;Command Line Args<br>
&nbsp;&nbsp;&nbsp;&nbsp;File I/O<br>
&nbsp;&nbsp;&nbsp;&nbsp;string &rarr; int/float<br>

</p>

</td>


<td class=main_panel valign=top height=100%>

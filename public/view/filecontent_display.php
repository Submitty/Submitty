<!-- DETAILS ON SUBMITTED FILES -->
<div class="row sub-text">


 <?php 
    $class_config = get_class_config($semester,$course);
    $svn_checkout = is_svn_checkout($class_config, $assignment_id); 
?>

<?php if ($svn_checkout != true) { echo "<!--"; } ?>

         <b class="sub2">Files from SVN checkout:</b>
         <pre class="complation_mess"><?php 
	      $path_front = get_path_front_course($semester,$course);
	      $student_path = "$path_front/results/$assignment_id/$username/$assignment_version/";
              $svn_file = $student_path.".submit_svn_checkout.txt";
              $svn_file_contents = get_student_file($svn_file);


	      if (file_exists($student_path) == false) {
		echo "<b> GRADING IN PROGRESS</b>";
	      }	else {
		if ($svn_file_contents == "") {
		  echo "<b> ERROR WITH SVN CHECKOUT </b>";
		} else {	  
		  echo htmlentities($svn_file_contents);
		}
	      }
            ?></pre>

<?php if ($svn_checkout != true) { echo "-->"; } ?>


<?php if ($svn_checkout == true) { echo "<!--"; } ?>

    <h4>Submitted Files:
        <?php
            if (isset($download_files) && $download_files == true){
                echo '<a class = "view_file"  href="?page=viewfile&semester='.$semester.'&course='.$course.'&assignment_id='.$assignment_id.'&assignment_version='.$assignment_version.'&file_name=all">Download All (as zip)</a>';
            }
        ?>
    </h4>
    <?php
    echo '<div class="box">';

          //keep track of number of file-display blocks 
          $counter = 0;

          foreach($submitted_files as $file) {
              if ($file === end($submitted_files)){
                  echo '<div>';
              }
              else{
                  echo '<div style="border-bottom: 1px solid #dddddd;">';
              }
            
                //name and size of the file ( ex. "code1.cpp (3kb)" )
                $file_desc = $file["name"].' ('.$file["size"].'kb)';
      
                echo '<p class="file-header"'; 

                    //does files_to_view exist in class.json and can this file be displayed?
                    if (isset($files_to_view) && in_array($file["name"], $files_to_view)){
                        //extend the file-header class to listen to user clicks so that it expands
                        //and display the file contents

                        echo 'href="#" onclick="return toggleDiv('."'".'filedisplay_'.$counter."'".');" style="cursor:pointer;" >'.$file_desc.'<a class = "view_file" href="#">View</a>';
                        
                        //is this file also downloadable and does download_file exist in class.json
                        if ( (isset($download_files) && $download_files == true) ){
                            //create link to download page
                            echo '<a class = "view_file" href="?page=viewfile&semester='.$semester.'&course='.$course.'&assignment_id='.$assignment_id.'&assignment_version='.$assignment_version.'&file_name='.$file["name"].'">Download</a>';
                        }

                        ?>

                        <!-- create the outermost div that will appear if display is set to "block" -->
                        <div id="filedisplay_<?php echo $counter ?>" class="diff-block" style="display:block">

                            <div class="file-display">

                                <?php 
                                $frontpath = get_path_front_course($semester,$course);;
                                $file_path = $frontpath.'/submissions/'.$assignment_id.'/'.$username.'/'.$assignment_version.'/'.$file["name"];
                                $file_open = fopen($file_path,"r") or die("Unable to open file!");

                                //is there content in this file?
                                if (filesize($file_path) == 0){
                                    //display warning message
                                    echo "<font color='red'>".$file["name"]." is empty!</font>";
                                }
                                else {
                                    //read the file line-by-line
				    //use <pre> tag to support multi-space and tabbed sentences
                                    echo "<pre>";
                                    while (!feof($file_open)){
                                        echo htmlentities(fgets($file_open));
                                    }
                                    echo "</pre>";
                                }

                                fclose($file_open);
                                ?> 

                            </div>

                        </div>

                        <script> 
                            //close all the filedisplay blocks
                            toggleDiv('filedisplay_'+<?php echo $counter ?>);
                        </script>



                        <?php

                        $counter = $counter + 1;

                    }

                    //does download_files exist in class.json and can files be downloaded
                    else if (isset($download_files) && $download_files == true){
                        //close off file-header tag immediately and create a link to download page
                        echo '>'.$file_desc.'<a class = "view_file" href="?page=viewfile&semester='.$semester.'&course='.$course.'&assignment_id='.$assignment_id.'&assignment_version='.$assignment_version.'&file_name='.$file["name"].'">Download</a>';
                    }
                    else{
                        //close off file-header tag
                        echo '>'.$file_desc; 
                    }

                echo '</p>';
                echo '</div>';
            }
        echo '</div>';

    ?>

<?php if ($svn_checkout == true) { echo "-->"; } ?>
</div>


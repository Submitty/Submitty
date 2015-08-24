<?php if($account_subpages_unlock) { 

    $calculate_diff = __CALCULATE_DIFF__;
    if ($calculate_diff) {
        echo <<<HTML
    <script src="{$BASE_URL}/toolbox/include/diff-viewer/underscore.js"></script>
    <script src="{$BASE_URL}/toolbox/include/diff-viewer/highlight.js"></script>
    <script src="{$BASE_URL}/toolbox/include/diff-viewer/diff.js"></script>
    <script src="{$BASE_URL}/toolbox/include/diff-viewer/diff_queue.js"></script>
    <link href="{$BASE_URL}/toolbox/include/diff-viewer/diff.css" rel="stylesheet" />
HTML;
    
    }
    $code_number = 0;
    
    ?>
    <div id="content">
        <div id="inner-container">
            <?php
                $rubric_lates = array();
                $total_tests = 0;
                $testcases = array();
                $status = 0;
                $params = array($student_rcs, $rubric_id);
                $autograder = 0;
                $autograder_ec = 0;
                $submitted = 0;
            /*
                $db->query("SELECT SUM(grade_days_late) as used_late_days FROM grades WHERE student_rcs=? AND grade_days_late < 3 AND rubric_id <> ?", $params);
                $late_row = $db->row();
                //$used_late_days = (isset($late_row["used_late_days"]) && $late_row["used_late_days"] >= 0) ? $late_row["used_late_days"] : 0;
                $used_late_days = $late_row['used_late_days'];
            */
                $submission_file_extensions = explode(",", __ALLOWED_FILE_EXTENSIONS__);

                $part_number = 1;

                $homework_dir = str_pad($homework_number,2,"0",STR_PAD_LEFT);
                if($rubric_sep == true)
                {
                    $homework = "hw" . $homework_dir . "_part" . $part_number;
                }
                else
                {
                    $homework = "hw" . $homework_dir;
                }

                $homework_directory_part = implode("/", array(__SUBMISSION_SERVER__, "submissions", $homework));
                $homework_directory_part_results = implode("/", array(__SUBMISSION_SERVER__, "results", $homework));
                $expected_directory_part = implode("/",array(__SUBMISSION_SERVER__,"test_output",$homework));

                $homework_directory_results = "";
                $homework_directory_submission = "";

                $late_days_max_surpassed = False;
                $first = true;
                while(is_dir($homework_directory_part) && $first)
                {
                    $hwconfig = json_decode(removeTrailingCommas(file_get_contents(implode("/",array(__SUBMISSION_SERVER__,"config",$homework."_assignment_config.json")))), true);
                    $matched = 0;
                    $tests = 0;
                    $diff_matches = array();
                    $test_errors = array();

                    $all_outputs = array();

                    if(is_dir(implode("/", array($homework_directory_part, $student_rcs))))
                    {
                        $submitted = 1;
                        // get the active assignment to grade
                        $json = file_get_contents(implode("/", array($homework_directory_part, $student_rcs, "user_assignment_settings.json")));
                        $json = json_decode($json, true);
                        $submission_number = intval($json['active_assignment']);

                        $submission_number2 = 1;
                        while(is_dir(implode("/", array($homework_directory_part, $student_rcs, $submission_number2)))) {
                            $submission_number2++;
                        }
                        $submission_number2--;
                        if($submission_number < 1 || $submission_number2 < $submission_number) {
                            $submission_number = $submission_number2;
                        }

                        $homework_directory_submission = implode("/", array($homework_directory_part, $student_rcs, $submission_number));
                        $homework_directory_results = implode("/", array($homework_directory_part_results, $student_rcs, $submission_number));

                        echo "<h5>Active Submissions [ ".$submission_number." / ".$submission_number2." ]</h5>";
                        // Locate all submitted files
                        $sources = array();

                        if($handle = opendir($homework_directory_submission))
                        {
                            while(($temp_filename = readdir($handle)) !== false) 
                            {   
                                if(is_file(implode("/", array($homework_directory_submission, $temp_filename))))
                                {
                                    // TODO: make this less dumb by using in_array and stuff
                                    // TODO: in_array(pathinfo($temp_filename, PATHINFO_EXTENSION),submission_file_extensions)
                                    foreach($submission_file_extensions as $submission_file_extension)
                                    {                               
                                        if(strpos($temp_filename, "." . $submission_file_extension) !== false || $submission_file_extension == "*")
                                        {
                                            $source_filename = implode("/", array($homework_directory_submission, $temp_filename));
                                            $source_file_size = filesize($source_filename);
                                            
                                            if($source_file_size < 1024 * 1024 * 5) // Limit file sizes to 5 MB
                                            {
                                                $source = file_get_contents($source_filename);
                                                if($source == "") { $source = "[NO SOURCE]"; }
                                            }
                                            else
                                            {
                                                $source = "[SOURCE TOO LONG, GREATER THAN 5MB]";
                                            }

                                            $sources[$temp_filename] = $source;
                                        }   
                                    }
                                }
                            }
                            closedir($handle);
                        }

                        $result_files = array(".submit.grade", ".submit_compile_output.txt", ".submit_runner_output.txt",
                            ".submit_validator_output.txt");
                        foreach ($result_files as $file) {
                            $filepath = implode("/", array($homework_directory_results, $file));
                            if (file_exists($filepath)) {
                                $source = file_get_contents($filepath);
                                $sources[$file] = $source;
                            }
                        }

                        
                        // Determine if the part is late
                        $date_submission = strtotime(file_get_contents(implode("/", array($homework_directory_submission, ".submit.timestamp"))));

                        $params = array($rubric_id);
                        $db->query("SELECT rubric_due_date FROM rubrics WHERE rubric_id=?", $params);
                        $row = $db->row();
                        $date_due = strtotime($row["rubric_due_date"]) + 1 + __SUBMISSION_GRACE_PERIOD_SECONDS__;
                
                        $late_days = ($date_submission - $date_due) / (60 * 60 * 24);
                        //print "Late: ".$late_days;
                        $late_days = round($late_days+.5, 0);
                        //print "Late: ".$late_days;
                        /*
                        if($late_days <= 0) { $late_days = 0; }
                        elseif(0 < $late_days && $late_days <= 1) { $late_days = 1; }
                        elseif(1 < $late_days && $late_days <= 2) { $late_days = 2; }
                        elseif(2 < $late_days) { $late_days = 3; }
                        */
                        
                        $rubric_lates[$part_number] = ($late_days <= 0) ? 0 : $late_days;
                        
                        /*
                        if($used_late_days + $late_days > $student_allowed_lates) {
                            $rubric_lates[$part_number] = 3;
                            $late_days_max_surpassed = True;
                        }
                        else {
                            $rubric_lates[$part_number] = $late_days;   
                        }
                        */

                        $submission_details = file_get_contents($homework_directory_results."/submission.json");
                        $submission_details = json_decode(removeTrailingCommas($submission_details), true);

                        // Autograder details
                        if (__USE_AUTOGRADER__) {
                            $autograder += intval($submission_details['non_extra_credit_points_awarded']);
                            $autograder_ec += intval($submission_details['extra_credit_points_awarded']);
                        }

                        $total_tests = count($submission_details['testcases']);
                        $testcases = $submission_details['testcases'];

                        /*
                        // Get part output, expected, error, and diff

                        for($test = 0; $test < $total_tests; $test++)
                        {
                            $test_details = $submission_details['testcases'][$test];
                            if (!isset($test_details['diffs']) || !is_array($test_details['diffs'])) {
                                // if it doesn't have a diff, then it's not a real test
                                //continue;
                            }
                            $diff_details = $test_details['diffs'][0];
                            $output_filename = implode("/", array($homework_directory_results, $diff_details['student_file']));
                            $output = str_replace("\r","",file_get_contents($output_filename));
                            if($output == "") { $source = "[NO OUTPUT]"; }
                            
                            // $expected_filename = implode("/", array(__HOMEWORK_DIRECTORY__, "scripts", "hw" . $homework_number, "part" . $part_number . "_correct.txt"));
                            // $expected = file_get_contents($expected_filename);
                            
                            //$expected_submission_number = 1;
                            //while(is_dir(implode("/", array($homework_directory_part, "parhaj", $expected_submission_number)))) { $expected_submission_number++; }
                            //$expected_submission_number--;
                            
                            //$expected_directory_submission = implode("/", array($expected_directory_part, $expected_submission_number));
                            if (isset($diff_details['instructor_file'])) {
                                $expected_filename = implode("/", array(__SUBMISSION_SERVER__, $diff_details['instructor_file']));
                                $expected = str_replace("\r", "", file_get_contents($expected_filename));
                            }
                            else {
                                $expected = "";
                            }

                            $error = file_get_contents(implode("/", array($homework_directory_results, $test_details['diffs'][2]['student_file'])));
                            $status = ($error != "") ? 1 : 0;

                            $diff_output = "";
                            $diff_expected = "";
                            $new_diff_output = "";
                            $diff_array = array();

                            if(strlen($output) > __OUTPUT_MAX_LENGTH__)
                            {           
                                $diff_output = $diff_expected = "[DIFF ERROR: OUTPUT TOO LONG]";
                            }
                            elseif($output == "[NO OUTPUT]")
                            {
                                $diff_output = $diff_expected = "[DIFF ERROR: NO OUTPUT]";
                            }
                            elseif($output == "[OUTPUT TOO LONG, GREATER THAN 5MB]")
                            {
                                $diff_output = $diff_expected = "[DIFF ERROR: OUTPUT TOO LONG]";
                            }
                            else
                            {
                                if (isset($diff_details['difference']) && $diff_details['difference'] != "") {
                                    $diff_output = file_get_contents(implode("/", array($homework_directory_results, $diff_details['difference'])));
                                }
                                else {
                                    $diff_output = "";
                                }

                            }

                            array_push($all_outputs, array($output, $expected, $diff_output));
                        }
                        */
                    }
                    else
                    {
                        $rubric_lates[$part_number] = 3;
                        $sources = array();
                        $output = "[NOT SUBMITTED]";
                        $expected = "[NOT SUBMITTED]";
                        $error = "";
                        $diff_output = "[NOT SUBMITTED]";
                        $diff_expected = "[NOT SUBMITTED]";
                        $new_diff_output = "[NOT SUBMITTED]";
                        $status = 2;
                        //array_push($all_outputs, array($output, $expected, $error, $diff_output, $diff_expected,$new_diff_output,$status));
                    }

                ?>

                    <div id="inner-container-spacer"></div>
                    
                    <div class="tabbable">
                        <ul id="myTab" class="nav nav-tabs">
                            <?php
                                if($rubric_lates[$part_number] == 0)
                                {
                                    $late_color = "green";
                                    $late_icon = '<i class="icon-ok icon-white"></i>';
                                }
                                elseif($rubric_lates[$part_number] == 1)
                                {                                                       
                                    $late_color = "#FAA732";
                                    $late_icon = '<i class="icon-exclamation-sign icon-white"></i><br/>';
                                }
                                elseif($rubric_lates[$part_number] == 2)
                                {
                                    $late_color = "#FAA732";
                                    $late_icon = '<i class="icon-exclamation-sign icon-white"></i><br/><i class="icon-exclamation-sign icon-white"></i>';
                                }
                                else
                                {
                                    $late_color = "#DA4F49";
                                    $late_icon = '<i class="icon-remove icon-white"></i>';
                                    $grade = 0;
                                }
                            ?>
                            
                                
                            <li style="margin-right:2px; height:34px; width:20px; text-align:center; line-height:16px; padding-top:3px; -webkit-border-radius: 4px 4px 0 0; -moz-border-radius: 4px 4px 0 0; border-radius: 4px 4px 0 0; background-color:<?php echo $late_color; ?>">
                                <?php echo $late_icon; ?>
                            </li>
                            <?php
                                $i = -1;
                                $c = 1;
                                $active = false;
                                foreach ($testcases as $testcase)
                                {
                                    $i += 1;
                                    //print_r($testcase);
                                    if (!isset($testcase['diffs'])) {

                                        continue;
                                    }
                                    $no_diffs = true;
                                    foreach ($testcase['diffs'] as $diff) {
                                        if (isset($diff['difference'])) {
                                            if (file_exists(implode("/", array($homework_directory_results, $diff['difference'])))) {
                                                $difference = json_decode(removeTrailingCommas(file_get_contents(implode("/", array($homework_directory_results, $diff['difference'])))),true);
                                                if (count($difference['differences']) > 0) {
                                                    $no_diffs = false;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    $css = ($no_diffs == true) ? 'check-full' : 'cross';
                                    $text = ($no_diffs == true) ? "Y" : "N";

                                    $pa = $testcase['points_awarded'];
                                    $pt = $hwconfig['testcases'][$i]['points'];
                                ?>
                                    <li class="<?php echo ($active == false ? 'active' : ''); $active = true; ?>" ><span class="diff <?= $css; ?>"><?= $text; ?></span>
                                        <a href="#output-<?php echo $part_number; ?>-<?php echo $i; ?>" data-toggle="tab">Output<?php echo " (Test " . ($i+1) . " [{$pa}/{$pt}])"; ?></a></li>
                                    <?php
                                    if ($status == 2) {
                                        break;
                                    }
                                    ?>
                                <?php 
                                }
                                
                                if($total_tests == 0)
                                {    
                                ?>
                                    <li <?php echo ($c == 1 ? ' class="active"' : '');?>><a href="#output-<?php echo $part_number; ?>-<?php echo $c++; ?>" data-toggle="tab"><b style="color:#DA4F49;">No Submission</b></a></li>
                                <?php 
                                }

                                $c = 1;
                                foreach($sources as $filename => $source)
                                { 
                                    if($filename == ".submit.times")
                                    {
                                        $filename = "Submit Time";
                                    }
                                    ?>
                                    <li><a href="#source-<?php echo $part_number; ?>-<?php echo $c++; ?>" data-toggle="tab" style="background-color:#F6F6F6;"><?php echo $filename; ?></a></li>
                                <?php 
                                }
                            ?>
                        </ul>
                        <div class="tab-content" style="width: 100%; overflow-x: hidden;">
                                <?php
                                $active = false;
                                for ($i = 0; $i < $total_tests; $i++) {
                                    if (!isset($testcases[$i]['diffs'])) {
                                        continue;
                                    }
                                    ?>
                                    <div class="tab-pane <?php echo ($active == false ? 'active' : ''); $active = true; ?>" id="output-<?php echo $part_number; ?>-<?php echo $i; ?>">

                                        <div style="width:95%; margin: auto auto auto auto; overflow-y:auto; overflow-x:hidden; padding-top:20px;">
                                            <?php
                                                $j = 0;
                                                foreach($testcases[$i]['diffs'] as $diff) {
                                                    $output = $expected = $difference = null;
                                                    if (isset($diff['student_file'])) {
                                                        if (file_exists(implode("/", array($homework_directory_results,$diff['student_file'])))) {
                                                            $output = file_get_contents(implode("/", array($homework_directory_results,$diff['student_file'])));
                                                        }
                                                        else {
                                                            $output = "";
                                                        }
                                                    }
                                                    if (isset($diff['instructor_file'])) {
                                                        if (file_exists(implode("/", array(__SUBMISSION_SERVER__, $diff['instructor_file'])))) {
                                                            $expected = file_get_contents(implode("/", array(__SUBMISSION_SERVER__, $diff['instructor_file'])));
                                                        }
                                                        else {
                                                            $expected = "";
                                                        }
                                                    }
                                                    if (isset($diff['difference'])) {
                                                        if (file_exists(implode("/", array($homework_directory_results, $diff['difference'])))) {
                                                            $difference = file_get_contents(implode("/", array($homework_directory_results, $diff['difference'])));
                                                        }
                                                        else {
                                                            $difference = "";
                                                        }
                                                    }
                                                    $tag = "div";
                                                    if ($output == "" && $expected == "" && $difference == "") {
                                                        continue;
                                                    }
                                                    else if ($difference === null) {
                                                        $tag = "textarea";
                                                    }

                                                    echo $diff['description'].(trim($diff['message']) != "" ? " - ".$diff['message'] : "");
                                                    if ($output !== null) { 
                                                        $output_code = $code_number++; 
                                                    ?>
                                                <div>
                                                    <h5>Student</h5>
                                                    <form>
                                                        <<?php echo $tag; ?>
                                                            id="code<?php echo $output_code; ?>"><?php echo $output; ?></<?php echo $tag; ?>>
                                                        <!-- <textarea id="code{$sourceSettings}">{$source}</textarea> -->
                                                    </form>
                                                </div>
                                                <?php } ?>
                                                <br />
                                                <?php if ($expected !== null) { 
                                                    $expected_code = $code_number++; ?>
                                                <div>
                                                    <h5>Expected</h5>
                                                    <form>
                                                        <<?php echo $tag; ?>
                                                            id="code<?php echo $expected_code; ?>"><?php echo $expected; ?></<?php echo $tag; ?>>
                                                    </form>
                                                </div>
                                                <?php } ?>

                                                    <?php
                                                    if ($expected !== null && $output !== null && $difference !== null) {
                                                        ?>
                                                        <script type="text/javascript">
                                                            $(document).ready(function() {
                                                                var student = <?php echo json_encode($output); ?>;
                                                                var instructor = <?php echo json_encode($expected); ?>;
                                                                var difference = <?php echo json_encode($difference); ?>;
                                                                diff.load(student, instructor);
                                                                diff.evalDifferences(JSON.parse(difference)["differences"]);
                                                                diff.display("code<?php echo $output_code; ?>", "code<?php echo $expected_code; ?>");
                                                            });
                                                        </script>
                                                        <?php
                                                    }
                                                    else {
                                                        if ($output !== null) {
                                                            echo sourceSettingsJS($diff['student_file'], $output_code);
                                                        }
                                                        if ($expected !== null) {
                                                            echo sourceSettingsJS($diff['instructor_file'], $expected_code);
                                                        }
                                                    }
                                                    ?>
                                                <br /><br />
                                            <?php
                                                    $j++;
                                                }
                                                $log_file = implode("/", array($homework_directory_results, $testcases[$i]['execute_logfile']));
                                                if (file_exists($log_file)) {
                                                    $source = file_get_contents($log_file);
                                                    $sourceSettings = $code_number++;
                                                    echo <<<HTML
                                                <div>
                                                    <h5>Test Logfile</h5>
                                                </div>
                                                <form style="float: left; width: 99%">
                                                    <textarea id="code{$sourceSettings}">{$source}</textarea>
                                                </form>
HTML;
                                                    echo sourceSettingsJS($testcases[$i]['execute_logfile'], $sourceSettings);

                                                }
                                            ?>
                                        </div>
                                    </div>
                                    <?php
                                }
                                
                                $c = 1;
                                foreach($sources as $filename => $source)
                                { 
                                    $source_number = $code_number++;
                                    ?>
                                    <div class="tab-pane" id="source-<?php echo $part_number; ?>-<?php echo $c; ?>">
                                        <div style="width:95%; margin: auto auto auto auto; overflow-y:auto; overflow-x:hidden; padding-top:20px;">
                                            <div class="source-info">
                                                <span style="text-decoration: underline"><?php echo $filename; ?></span>
                                            </div>
                
                                            <form style="float: left; width: 99%">
                                                <textarea id="code<?php echo $source_number; ?>"><?php echo $source; ?></textarea>
                                            </form> 
                                        </div>

                                        <?php
                                            echo sourceSettingsJS($filename, $source_number);
                                        ?>
                                    </div>
                                <?php
                                    $c++;
                                }
                            ?>
                        </div>
                    </div>  
            
                <?php 
                    $part_number++; 
                    if($rubric_sep == true)
                    {
                        $homework = "hw" . $homework_dir . "_part" . $part_number;
                    }
                    else
                    {
                        break;
                    }

                    $homework_directory_part = implode("/", array(__SUBMISSION_SERVER__, "submissions", $homework));
                    $homework_directory_part_results = implode("/", array(__SUBMISSION_SERVER__, "results", $homework));
                    $expected_directory_part = implode("/",array(__SUBMISSION_SERVER__,"test_output",$homework));
                } 
            ?>
            
            <div id="inner-container-spacer"></div>
        </div>
    </div>
        
<?php } ?>

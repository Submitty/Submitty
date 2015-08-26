<?php 
use \lib\Database;

	include "../header.php";
	
	if($user_is_administrator)
	{
        $have_old = false;
        $old_rubric = array(
            'rubric_id' => -1, 
            'rubric_number' => "",
            'rubric_due_date' => "",
            'rubric_code' => "",
            'rubric_parts_sep' => false,
            'rubric_late_days' => __DEFAULT_LATE_DAYS__
        );
        $old_questions = array();
        if (isset($_GET['action']) && $_GET['action'] == 'edit') {
            $rubric_id = intval($_GET['id']);
            Database::query("SELECT * FROM rubrics WHERE rubric_id=?",array($rubric_id));
            if (count(Database::rows()) == 0) {
                die("No rubric found");
            }
            $old_rubric = Database::row();
            Database::query("SELECT * FROM questions WHERE rubric_id=? ORDER BY question_part_number, question_number", array($old_rubric['rubric_id']));
            $questions = Database::rows();
            foreach ($questions as $question) {
                $question['question_total'] = floatval($question['question_total']);
                $old_questions[$question['question_part_number']][$question['question_number']] = $question;
            }
            $have_old = true;
        }
        
		$useAutograder = (__USE_AUTOGRADER__) ? "true" : "false";
		$account_subpages_unlock = true;
		
		function selectBox($part, $question, $grade = 0)
		{
			$retVal = "<select name='point-{$part}-{$question}' class='points' onchange='calculatePercentageTotal();'>";
			for($i = 0; $i <= 50; $i += 0.5)
			{
                $selected = ($grade == $i) ? "selected" : ""; 
				$retVal .= "<option {$selected}>{$i}</option>";
			}
			$retVal .= "</select>";
			
			return $retVal;
		}

        $rubrics = array();
        $db->query("SELECT rubric_id, rubric_number from rubrics ORDER BY rubric_number", array());
        foreach ($db->rows() as $row) {
            $rubrics[$row['rubric_id']] = intval($row['rubric_number']);
        }

        if (!$have_old) {
            $rubricNumberQuery = (count($rubrics) > 0) ? end($rubrics) + 1 : 1;
            $string = "Add";
            $action = strtolower($string);
        }
        else {
            $rubricNumberQuery = $old_rubric['rubric_number'];
            $string = "Edit";
            $action = strtolower($string);
        }

        $rubric_sep_checked = ($old_rubric['rubric_parts_sep'] == 1) ? "checked" : "";
        
		print <<<HTML
	
	<style type="text/css">
		body {
			overflow: scroll;
		}
		
		select {
			margin-top:7px;
			width: 60px;
			min-width: 60px;
		}
		
		#container-rubric
		{
			width:1200px; 
			margin:100px auto; 
			margin-top: 130px; 
			background-color: #fff; 
			border: 1px solid #999; 
			border: 1px solid rgba(0,0,0,0.3); 
			-webkit-border-radius: 6px; 
			-moz-border-radius: 6px; 
			border-radius: 6px;outline: 0;
			-webkit-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
			-moz-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
			box-shadow: 0 3px 7px rgba(0,0,0,0.3);
			-webkit-background-clip: padding-box;
			-moz-background-clip: padding-box;
			background-clip: padding-box;
		}
	</style>
	
	<div id="container-rubric">
		<form class="form-signin" action="{$BASE_URL}/account/submit/admin-rubric.php?action={$action}&id={$old_rubric['rubric_id']}" method="post" enctype="multipart/form-data">
HTML;
        
		print <<<HTML
			<div class="modal-header">
				<h3 id="myModalLabel">{$string} Rubric (Homework $rubricNumberQuery)</h3>
			</div>
		
			<div class="modal-body" style="/*padding-bottom:80px;*/ overflow:hidden;">
				<select name="rubric" style="width: 420px; display:none;">	
					<option>
						$rubricNumberQuery
					</option>						
				</select>
				
				<br/>
				
				Due Date:
				<!--<fieldset>-->
					<input name="date" class="datepicker" type="text" 
					style="cursor: auto; background-color: #FFF; width: 250px;">
				<!--</fieldset>-->
				&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;
				Separate Parts:
				<input type="checkbox" name="rubric_parts_sep" value="1" {$rubric_sep_checked} />
				&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;
				Late Days:
				<input name="rubric_late_days" type="text" value="{$old_rubric['rubric_late_days']}"/>
				<br/>
				
				<table class="table table-bordered" id="rubricTable" style=" border: 1px solid #AAA;">
					<thead style="background: #E1E1E1;">
						<tr>
							<th style="width:61px;">Part</th>
							<th>Question</th>
							<th style="width:100px;">Points</th>
						</tr>
					</thead>
					
					<tbody style="background: #f9f9f9;">
HTML;
        if (count($old_questions) == 0) {
            if (__USE_AUTOGRADER__) {
                $old_questions[0][1] = array('question_message'      => "AUTO-GRADING",
                                             'question_grading_note' => "",
                                             'question_total'        => 0,
                                             'question_extra_credit' => 0);
                $old_questions[0][2] = array('question_message'      => "AUTO-GRADING EXTRA CREDIT",
                                             'question_grading_note' => "",
                                             'question_total'        => 0,
                                             'question_extra_credit' => 1);
            }
            $old_questions[1][1] = array('question_message'      => "",
                                         'question_grading_note' => "",
                                         'question_total'        => 0,
                                         'question_extra_credit' => 0);
        }

        foreach ($old_questions as $k => $v) {
            $count = count($old_questions[$k]) + (($k > 0) ? 1 : 0);
            
            $disabled = ($k == 0) ? "disabled" : "";
            $readonly = ($k == 0) ? "readonly" : "";
            
            $first = true;
            foreach ($v as $num => $question) {
                print <<<HTML
                    <tr>
HTML;
                if ($first) {
                    print <<<HTML
                        <td rowspan="{$count}" id="spanPart{$k}" style="position:relative">{$k}</td>
HTML;
                    $first = false;
                }
                
                $display_ta = ($question['question_grading_note'] != "") ? 'block' : 'none';
                
                print <<<HTML
                        <td style="overflow: hidden;">
                            <textarea name="comment-{$k}-{$num}" rows="1" style="width: 885px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;" {$readonly}>{$question['question_message']}</textarea>
                            <div class="btn btn-mini btn-default" onclick="toggleTA({$k},{$num})" style="margin-top:-5px;">TA Note</div>
                            <textarea name="ta-{$k}-{$num}" id="individual-{$k}-{$num}" rows="1" placeholder=" Message to TA" style="width: 940px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; display: {$display_ta};">{$question['question_grading_note']}</textarea>
                        </td>
                        
                        <td style="background-color:#EEE;">
HTML;
                $old_grade = (isset($question['question_total'])) ? $question['question_total'] : 0;
                print selectBox($k, $num, $old_grade);
                $checked = ($question['question_extra_credit'] == 1) ? "checked" : "";
                print <<<HTML
                            <input onclick='calculatePercentageTotal();' name="ec-{$k}-{$num}" type="checkbox" {$checked} {$disabled} />
                        </td>
HTML;
                print <<<HTML
                    </tr>
HTML;
            }
            if ($k > 0) {
                print <<<HTML
                    <tr>     
                        <td style="overflow: hidden;">
                            <div class="btn btn-small btn-success"  onclick="addQuestion({$k})"><i class="icon-plus icon-white"></i> Question</div>
                        </td>
                        
                        <td style="border-left: 1px solid #F9F9F9;"></td>
                    </tr>
HTML;
            }
        }
        print <<<HTML
                    <tr>
                        <td>
                            <div class="btn btn-small btn-success" onclick="addPart()"><i class="icon-plus icon-white"></i> Part</div>
                        </td>
                        <td style="border-left: 1px solid #F9F9F9;"></td>
                        <td style="border-left: 1px solid #F9F9F9;"></td>
                    </tr>
HTML;
        print <<<HTML
                        <tr>	  
                            <td style="background-color: #EEE; border-top: 2px solid #CCC;"></td>
                            <td style="background-color: #EEE; border-top: 2px solid #CCC; border-left: 1px solid #EEE;"><strong>TOTAL POINTS</strong></td>	    
                            <td style="background-color: #EEE; border-top: 2px solid #CCC;"><strong id="totalCalculation"></strong></td>
                        </tr>
                    </tbody>
                </table>
HTML;

        $db->query("SELECT s.user_id, u.user_email, s.rubric_id, s.grading_section_id 
        FROM homework_grading_sections as s, users as u WHERE u.user_id = s.user_id 
        ORDER BY rubric_id, grading_section_id", array());
        $sections = array();
        foreach ($db->rows() as $row) {
            if (!isset($sections[$row['rubric_id']][$row['user_email']])) {
                $sections[$row['rubric_id']][$row['user_email']] = array();
            }
            $sections[$row['rubric_id']][$row['user_email']][] = $row['grading_section_id'];
        }
        asort($sections);
        
        $i = 0;
		$db->query("SELECT * FROM users ORDER BY user_email ASC", array());
        $users = $db->rows();
		foreach($users as $user) {
            $value =  isset($sections[$old_rubric['rubric_id']][$user['user_email']]) ? implode(",", $sections[$old_rubric['rubric_id']][$user['user_email']]) : -1;
			print <<<HTML
                <span style='display:inline-block; width:300px; padding-right: 5px'>{$user['user_lastname']}, 
                        {$user['user_firstname']}:</span> 
                <input style='width: 30px; text-align: center' type='text' name='{$user['user_id']}-section' 
                        value='{$value}' />
                <br />	
HTML;
            $i++;
        }
        
        // TODO: Style this less dumb
        $margintop = ($i*-40) . "px";
        $marginright =  650-(count($rubrics)*25) . "px";
		print <<<HTML
		    <table border="1" style="float:right; margin-top:{$margintop}; margin-right: {$marginright}">
		        <tr>
		            <td>User</td>
HTML;
        foreach ($rubrics as $id => $number) {
            print <<<HTML
                    <td style="width: 20px; text-align: center">
                        {$number}
                    </td>
HTML;
        }
 
        print <<<HTML
                </tr>
HTML;

        foreach ($users as $user) {      
            print <<<HTML
                <tr>
                    <td>{$user['user_email']}</td>
HTML;

            foreach ($rubrics as $id => $rubric) {
                $number = (isset($sections[$id][$user['user_email']])) ? implode(",",$sections[$id][$user['user_email']]) : "";
                print <<<HTML
                    <td style="text-align: center">
                        {$number}
                    </td>
HTML;
            } 
            print <<<HTML
                </tr>
HTML;
        }

        
        print <<<HTML
            </table>
			</div>

			<div class="modal-footer">
			        <button class="btn btn-primary" type="submit" style="margin-top: 10px;">{$string} Rubric</button>
			</div>     	            
		</form>
	</div>	
HTML;
        if ($old_rubric['rubric_due_date'] != "") {
            $date = explode(" ", $old_rubric['rubric_due_date']);
            $date = explode("-", $date[0]);
            $year = $date[0];
            $month = intval($date[1]);
            $day = intval($date[2]);
            $date = "{year: {$year}, month: {$month}, day: {$day}}";
        }
        else {
            $date = "null";
        }
        print <<<HTML
    <script type="text/javascript">
		$('.datepicker').pickadate({
			format: 'dddd, mmm d, yyyy at 23:59:59',
			formatSubmit: 'yyyy-mm-dd 23:59:59'
		}, {$date});
		
		function toggleTA(part, question)
		{	
			if(document.getElementById("individual-" + part + "-" + question ).style.display == "block")
			{
		  		$("#individual-" + part + "-" + question ).animate({marginBottom:"-80px"});
		  		//document.getElementById("individual-" + part + "-" + question ).innerHTML = "";
		  		setTimeout(function(){document.getElementById("individual-" + part + "-" + question ).style.display = "none";}, 175);
				
		  	}
		  	else
		  	{
			  	$("#individual-" + part + "-" + question ).animate({marginBottom:"5px"});
		  		setTimeout(function(){document.getElementById("individual-" + part + "-" + question ).style.display = "block";}, 175);
		  	}
		  	
			calculatePercentageTotal();
		}
HTML;
        
        $parts = "[";
        for($i = 0; $i <= max(array_keys($old_questions)); $i++) {
            $parts .= (isset($old_questions[$i]) ? (count($old_questions[$i]) + (($i > 0) ? 1 : 0)) : 0).",";
        }
        $parts = rtrim($parts, ",");
        $parts .= "]";
        
        print <<<JS
        
		var parts = {$parts};
	    function addPart()
		{	   	
			parts.push(2);
			var partName = parts.length - 1;
			var table = document.getElementById("rubricTable");
			var row = table.insertRow(table.rows.length - 2);
			var cell1 = row.insertCell(0);
			cell1.rowSpan = "2";
			cell1.setAttribute("id", "spanPart"+partName);
			var cell2 = row.insertCell(1);
			cell2.style.overflow = "hidden";
			var cell3 = row.insertCell(2);
			cell3.style.backgroundColor = "#EEE";
			cell1.innerHTML = "" + partName;
			cell2.innerHTML='<textarea name="comment-' + partName + '-1" rows="1" style="width: 896px; padding: 0px; resize: none; margin-top: 5px; margin-right: 5px;"></textarea></span>'+
	                  		'<div class="btn btn-mini btn-default" onclick="toggleTA(' + partName + ',1)" style="margin-top:-5px;">TA Note</div>'+
	                  		'<textarea name="ta-' + partName + '-1" id="individual-' + partName + '-1" rows="1" placeholder=" Message to TA" style="width: 954px; padding: 0px; resize: none; margin-top: 5px; margin-bottom: -80px; display: none;"></textarea>';
			cell3.innerHTML=selectBox(partName, "1") + ' <input name="ec-' + partName + '-1" type="checkbox" />';
			
			row = table.insertRow(table.rows.length - 2);
			cell1 = row.insertCell(0);
			cell2 = row.insertCell(1);
			cell2.style.borderLeft = '1px solid #F9F9F9';
			cell1.innerHTML='<div class="btn btn-small btn-success"  onclick="addQuestion('+partName+')"><i class="icon-plus icon-white"></i> Question</div>';
		}
		
		function addQuestion(partName)
		{	 
			var number = 0;
			for (var i = 0; i < parts.length && i <= Number(partName); i++)
			{
				number += parts[i];
			}
				
			document.getElementById("spanPart"+partName).rowSpan = '' + (Number(document.getElementById("spanPart"+partName).rowSpan) + 1);
			var table = document.getElementById("rubricTable");
			var row = table.insertRow(number);
			var cell1 = row.insertCell(0);
			cell1.style.overflow = "hidden";
			var cell2 = row.insertCell(1);
			cell2.style.backgroundColor = "#EEE";
			cell1.innerHTML = '<textarea name="comment-' + partName + "-" + parts[Number(partName)] + '" rows="1" style="width:896px; padding: 0px; resize: none; margin-top: 5px; margin-right: 5px;"></textarea></span>'+
	                  		'<div class="btn btn-mini btn-default" onclick="toggleTA(' + partName + "," + parts[Number(partName)] + ')" style="margin-top:-5px;">TA Note</div>'+
	                  		'<textarea name="ta-' + partName + "-" + parts[Number(partName)] + '" id="individual-' + partName + "-" + parts[Number(partName)] + '" rows="1" placeholder=" Message to TA" style="width: 954px; padding: 0px; resize: none; margin-top: 5px; margin-bottom: -80px; display: none;"></textarea>';
			cell2.innerHTML = selectBox(partName, parts[Number(partName)]) + ' <input name="ec-'+partName+'-'+parts[Number(partName)]+'" type="checkbox" />';
			
			parts[Number(partName)] += 1;
			
		}
		
		function selectBox(part, question)
		{
			var retVal = '<select name="point-' + part + "-" + question + '" class="points" onchange="calculatePercentageTotal()">';
			for(var i = 0; i <= 100; i++)
			{
				retVal = retVal + '<option>' + (i * 0.5) + '</option>';
			}
			retVal = retVal + '</select>';
			
			return retVal;
		}
		
		function calculatePercentageTotal() {
		  var total=0;
		  var ec = 0;
		  $('select.points').each(function(){
		    var elem = $(this).attr('name').replace('point','ec');
		    if (!$('[name="'+elem+'"]').is(':checked')) {
    		    total += +($(this).val());
            }
            else {
                ec += +($(this).val());
            }
		  });
		  
		  document.getElementById("totalCalculation").innerHTML = total + " (" + ec + ")";
		}
		
		calculatePercentageTotal();
JS;
        print <<<HTML
	</script>
HTML;
	}

	include "../footer.php";
?>
<?php

namespace app\views\grading;

use app\models\Gradeable;
use app\models\GradeableComponent;
use app\models\User;
use app\models\LateDaysCalculation;
use app\views\AbstractView;
use app\libraries\FileUtils;

class ElectronicGraderView extends AbstractView {
    /**
     * @param Gradeable $gradeable
     * @param array     $sections
     * @return string
     */
    public function statusPage(
        $gradeable,
        $sections,
        $component_averages,
        $autograded_average,
        $overall_average,
        $total_students,
        $registered_but_not_rotating,
        $rotating_but_not_registered,
        $section_type) {

        $peer = false;
        if($gradeable->getPeerGrading() && $this->core->getUser()->getGroup() == 4) {
            $peer = true;
        }
        $course = $this->core->getConfig()->getCourse();
        $semester = $this->core->getConfig()->getSemester();
        $graded = 0;
        $total = 0;
        $no_team_total = 0;
        foreach ($sections as $key => $section) {
            if ($key === "NULL") {
                continue;
            }
            $graded += $section['graded_components'];
            $total += $section['total_components'];
            if ($gradeable->isTeamAssignment()) {
                $no_team_total += $section['no_team'];
            }
        }
        if ($total === 0 && $no_team_total === 0){
            $percentage = -1;
        }
        else if ($total === 0 && $no_team_total > 0){
            $percentage = 0;
        }
        else{
            $percentage = number_format(($graded / $total) * 100, 1);
        }
        $return = <<<HTML
<div class="content">
    <h2>Status of {$gradeable->getName()}</h2>
HTML;
        if($percentage === -1){
            $view = 'all';
            $return .= <<<HTML
    <div class="sub">
        No Grading To Be Done! :)
    </div>
HTML;
        }
        else{
            $view = null;
            $change_value = $gradeable->getNumTAComponents();
            $show_total = $total/$change_value;
            $show_graded = round($graded/$change_value, 2);
            if($peer) {
                $change_value = $gradeable->getNumPeerComponents() * $gradeable->getPeerGradeSet();
                $show_graded = $graded/$change_value;
                $show_total = $total/$change_value;
            }
            $submitted_percentage = round(($show_total / $total_students) * 100, 1);

            //Add warnings to the warnings array to display them to the instructor.
            $warnings = array();
            if($section_type === "rotating_section" && $this->core->getUser()->accessFullGrading()){
                if ($registered_but_not_rotating > 0){
                    array_push($warnings, "There are ".$registered_but_not_rotating." registered students without a rotating section.");
                }
                if($rotating_but_not_registered > 0){
                    array_push($warnings, "There are ".$rotating_but_not_registered." unregistered students with a rotating section.");
                }
            }

            $return .= <<<HTML
    <div class="sub">
        <div class="box half">
HTML;
            if(count($warnings) > 0){
                $return .= <<<HTML
                <ul>
HTML;
                foreach ($warnings as $warning){
                    $return .= <<<HTML
                    <li style="color:red; margin-left:1em">{$warning}</li>
HTML;
                }
                $return .= <<<HTML
                </ul>
                <br/>
HTML;
            }
            $return .= <<<HTML
            Students who have submitted: {$show_total} / {$total_students} ({$submitted_percentage}%)
            <br />
            <br />
            Current percentage of grading done: {$show_graded}/{$show_total} ({$percentage}%)
HTML;
            if ($gradeable->isTeamAssignment() && $no_team_total > 0) {
                $return .= <<<HTML
             - {$no_team_total} students with no team
HTML;
            }
            $return .= <<<HTML
            <br />
            <br />
HTML;
            if ($peer) {
                $show_total = floor($sections['stu_grad']['total_components']/$gradeable->getNumPeerComponents());
                $show_graded = floor($sections['stu_grad']['graded_components']/$gradeable->getNumPeerComponents());
                $percentage = number_format(($sections['stu_grad']['graded_components']/$sections['stu_grad']['total_components']) * 100, 1);
                $return .= <<<HTML
            Current percentage of students grading done: {$percentage}% ({$show_graded}/{$show_total})
        </div>
            <br />
HTML;
            }
            else {
                $return .= <<<HTML
            By Grading Sections:
            <div style="margin-left: 20px">
HTML;
                foreach ($sections as $key => $section) {
                    if($section['total_components'] == 0) {
                        $percentage = 0;
                    }
                    else {
                        $percentage = number_format(($section['graded_components'] / $section['total_components']) * 100, 1);
                    }
                    $show_graded = round($section['graded_components']/$change_value, 1);
                    $show_total = $section['total_components']/$change_value;
                    $return .= <<<HTML
                Section {$key}: {$show_graded} / {$show_total} ({$percentage}%)<br />
HTML;
                    if ($gradeable->isTeamAssignment() && $section['no_team'] > 0) {
                        $return .= <<<HTML
                - {$section['no_team']} students with no team
HTML;
                    }
                }
                $return .= <<<HTML
            </div>
            <br />
            Graders:
            <div style="margin-left: 20px">
HTML;
                foreach ($sections as $key => $section) {
                    if ($key === "NULL") {
                        continue;
                    }
                    $valid_graders = array();
                    foreach($section['graders'] as $valid_grader){
                        if($valid_grader->getGroup() <= $gradeable->getMinimumGradingGroup()){
                            $valid_graders[] = $valid_grader->getDisplayedFirstName();
                        }
                    }
                    $graders = (count($valid_graders) > 0) ? implode(', ', $valid_graders) : 'Nobody';

                    $return .= <<<HTML
                Section {$key}: {$graders}<br />
HTML;
                }
                $return .= <<<HTML
            </div>
        </div>
HTML;
            }
            if(!$peer) {
                if(!$gradeable->isTeamAssignment()) {
                    $return .= <<<HTML
        <div class="box half">
            <b>Statistics for Completely Graded Assignments: </b><br/>
            <div style="margin-left: 20px">
HTML;
                    if($overall_average == null) {
                        $return .= <<<HTML
                There are no students completely graded yet.
            </div>
HTML;
                    }
                    else {
                        if($gradeable->getTotalAutograderNonExtraCreditPoints() == null) {
                            $total = $overall_average->getMaxValue();
                        }
                        else {
                            $total = $overall_average->getMaxValue() + $gradeable->getTotalAutograderNonExtraCreditPoints();
                        }
                        $percentage = 0;
                        if ($total != 0) {
                            $percentage = round($overall_average->getAverageScore()/$total*100);
                        }
                        $return .= <<< HTML
                Average: {$overall_average->getAverageScore()} / {$total} ({$percentage}%)<br/>
                Standard Deviation: {$overall_average->getStandardDeviation()} <br/>
                Count: {$overall_average->getCount()} <br/>
            </div>
HTML;
                    }
                    if($gradeable->getTotalAutograderNonExtraCreditPoints() == 0) {
                        // Don't display any autograding statistics since this gradeable has none
                    } else {
                        $return .= <<<HTML
            <br/><b>Statistics for Auto-Grading: </b><br/>
            <div style="margin-left: 20px">
HTML;
                        if($autograded_average->getCount() == 0) {
                            $return .= <<<HTML
                There are no submitted assignments yet.
            </div>
HTML;
                        }
                        else {
			    $percentage = 0;
                            if($gradeable->getTotalAutograderNonExtraCreditPoints() != 0) {
                                $percentage = round($autograded_average->getAverageScore()/$gradeable->getTotalAutograderNonExtraCreditPoints()*100);
			    }
                            $return .= <<<HTML
                Average: {$autograded_average->getAverageScore()} / {$gradeable->getTotalAutograderNonExtraCreditPoints()} ({$percentage}%)<br/>
                Standard Deviation: {$autograded_average->getStandardDeviation()} <br/>
                Count: {$autograded_average->getCount()} <br/>
            </div>
HTML;
                        }
                    }
                    $return .= <<<HTML
            <br/><b>Statistics for Manually Graded Components: </b><br/>
            <div style="margin-left: 20px">
HTML;
                    if(count($component_averages) == 0) {
                        $return .= <<<HTML
            No components have been graded yet.
HTML;
                    }
                    else {
                        $overall_score = 0;
                        $overall_max = 0;
                        foreach($component_averages as $comp) {
                            $overall_score += $comp->getAverageScore();
                            $overall_max += $comp->getMaxValue();
                            $percentage = 0;
			                if ($comp->getMaxValue() != 0) {
			                    $percentage = round($comp->getAverageScore() / $comp->getMaxValue() * 100);
                            }
                            $average_string = ($comp->getMaxValue() > 0 ? "{$comp->getAverageScore()} / {$comp->getMaxValue()} ({$percentage}%)" : "{$comp->getAverageScore()}");
                            $return .= <<<HTML
                {$comp->getTitle()}:<br/>
                <div style="margin-left: 40px">
                    Average: {$average_string}<br/>
                    Standard Deviation: {$comp->getStandardDeviation()} <br/>
                    Count: {$comp->getCount()} <br/>
                </div>
HTML;
                        }
                        if($overall_max !=0){
                            $percentage = round($overall_score / $overall_max *100);
                            $return .= <<<HTML
                <br/>Overall Average:  {$overall_score} / {$overall_max} ({$percentage}%)
HTML;
                        }
                    }
                }
                $return .= <<<HTML
            </div>
        </div>
HTML;
            }
            $return .= <<<HTML
    </div>
HTML;
        }
        $return .= <<<HTML
    <div style="margin-top: 20px; vertical-align:bottom;">
HTML;
        if($percentage !== -1 || $this->core->getUser()->accessFullGrading() || $peer){
            $return .= <<<HTML
        <a class="btn btn-primary"
            href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action' => 'details', 'gradeable_id' => $gradeable->getId(), 'view' => $view))}"">
            Grading Details
        </a>
HTML;
            if(count($this->core->getUser()->getGradingRegistrationSections()) !== 0){
                $return .= <<<HTML
        <a class="btn btn-primary"
            href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'individual'=>'0'))}">
            Grade Next Student
        </a>
        <a class="btn btn-primary"
            href="{$this->core->buildUrl(array('component'=>'misc', 'page'=>'download_all_assigned', 'dir'=>'submissions', 'gradeable_id'=>$gradeable->getId()))}">
            Download Zip of All Assigned Students
        </a>
HTML;
            }
            if($this->core->getUser()->accessFullGrading()) {
                $return .= <<<HTML
        <a class="btn btn-primary"
            href="{$this->core->buildUrl(array('component'=>'misc', 'page'=>'download_all_assigned', 'dir'=>'submissions', 'gradeable_id'=>$gradeable->getId(), 'type'=>'All'))}">
            Download Zip of All Students
        </a>
HTML;
            }
        }
        $return .= <<<HTML
    </div>
</div>
HTML;
        return $return;
    }

    /**
     * @param Gradeable   $gradeable
     * @param Gradeable[] $rows
     * @param array       $graders
     * @return string
     */
    public function detailsPage($gradeable, $rows, $graders, $empty_teams) {
        $return = <<<HTML
<div class="content">

HTML;
        // Default is viewing your sections
        // Limited grader does not have "View All" option
        // If nothing to grade, Instructor will see all sections
        if (!isset($_GET['view']) || $_GET['view'] !== 'all') {
            $text = 'View All';
            $view = 'all';
        }
        else{
            $text = 'View Your Sections';
            $view = null;
        }
        $peer = false;
        if($gradeable->getPeerGrading() && $this->core->getUser()->getGroup()==4) {
            $peer = true;
        }
        if($peer) {
            $grading_count = $gradeable->getPeerGradeSet();
        }
        else if($gradeable->isGradeByRegistration()){
            $grading_count = count($this->core->getUser()->getGradingRegistrationSections());
        }
        else{
            $grading_count = count($this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable->getId(),$this->core->getUser()->getId()));
        }

        if($this->core->getUser()->accessFullGrading() && (!$this->core->getUser()->accessAdmin() || $grading_count !== 0)){
            $return .= <<<HTML
    <div style="float: right; margin-bottom: 10px">
        <a class="btn btn-default"
            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'action' => 'details', 'gradeable_id' => $gradeable->getId(), 'view' => $view))}">
            $text
        </a>
    </div>
HTML;
        }
        $show_auto_grading_points = true;
        $return .= <<<HTML
    <h2>Grade Details for {$gradeable->getName()}</h2>
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
HTML;
        $cols = 0;
        if($peer) {
            $return .= <<<HTML
                <td width="5%"></td>
                <td width="30%">Student</td>
HTML;
            if($gradeable->getTotalNonHiddenNonExtraCreditPoints() !== 0) {
                $cols = 6;
                $return .= <<<HTML
                <td width="15%">Autograding</td>
                <td width="20%">Grading</td>
                <td width="15%">Total</td>
                <td width="15%">Active Version</td>
            </tr>
        </thead>
HTML;
            }
            else {
                $cols = 5;
                $show_auto_grading_points = false;
                $return .= <<<HTML
                <td width="30%">Grading</td>
                <td width="20%">Total</td>
                <td width="15%">Active Version</td>
            </tr>
        </thead>
HTML;
            }
        }
        else {
            if ($gradeable->isTeamAssignment()) {
                if($this->core->getUser()->accessAdmin()) {
                    $cols = 5;
                    $return .= <<<HTML
                <td width="3%"></td>
                <td width="5%">Section</td>
                <td width="6%">Edit Teams</td>
                <td width="12%">Team Id</td>
                <td width="32%">Team Members</td>
HTML;
                }
                else {
                    $cols = 3;
                    $return .= <<<HTML
                <td width="3%"></td>
                <td width="5%">Section</td>
                <td width="50%">Team Members</td>
HTML;
                }
            }
            else {
                $cols = 5;
                $return .= <<<HTML
                <td width="3%"></td>
                <td width="5%">Section</td>
                <td width="20%">User ID</td>
                <td width="15%">First Name</td>
                <td width="15%">Last Name</td>
HTML;
            }
            if($gradeable->getTotalAutograderNonExtraCreditPoints() !== 0) {
                $cols += 6;
                $return .= <<<HTML
                <td width="9%">Autograding</td>
                <td width="8%">Graded Questions</td>
                <td width="8%">TA Grading</td>
                <td width="7%">Total</td>
                <td width="10%">Active Version</td>
                <td width="8%">Viewed Grade</td>
            </tr>
        </thead>
HTML;
            }
            else {
                $show_auto_grading_points = false;
                $cols += 5;
                $return .= <<<HTML
                <td width="8%">Graded Questions</td>
                <td width="12%">TA Grading</td>
                <td width="12%">Total</td>
                <td width="10%">Active Version</td>
                <td width="8%">Viewed Grade</td>
            </tr>
        </thead>
HTML;
            }
        }
        $count = 1;
        $last_section = false;
        $tbody_open = false;
        foreach ($rows as $row) {
            $active_version = $row->getActiveVersion();
            $highest_version = $row->getHighestVersion();
            if ($peer) {
                $autograding_score = $row->getGradedNonHiddenPoints();
                $graded = $autograding_score;
                $total_possible = $autograding_score + $gradeable->getTotalPeerGradingNonExtraCredit();
            }
            else {
                $autograding_score = $row->getGradedAutograderPoints();
                if ($row->beenTAgraded()){
                    if ($row->getUserViewedDate() === null || $row->getUserViewedDate() === "") {
                        $viewed_grade = "&#10008;";
                        $grade_viewed = "";
                        $grade_viewed_color = "color: red; font-size: 1.5em;";
                    }
                    else {
                        $viewed_grade = "&#x2714;";
                        $grade_viewed = "Last Viewed: " . date("F j, Y, g:i a", strtotime($row->getUserViewedDate()));
                        $grade_viewed_color = "color: #5cb85c; font-size: 1.5em;";
                    }
                }
                else{
                    $viewed_grade = "";
                    $grade_viewed = "";
                    $grade_viewed_color = "";
                }
                $total_possible = $row->getTotalAutograderNonExtraCreditPoints() + $row->getTotalTANonExtraCreditPoints();
                $graded = $autograding_score;
            }

            if ($graded < 0) $graded = 0;
            if($peer) {
                $section = "PEER STUDENT GRADER";
            }
            else if ($gradeable->isGradeByRegistration()) {
                $section = $row->getTeam() === null ? $row->getUser()->getRegistrationSection() : $row->getTeam()->getRegistrationSection();
            }
            else {
                $section = $row->getTeam() === null ? $row->getUser()->getRotatingSection() : $row->getTeam()->getRotatingSection();
            }
            $display_section = ($section === null) ? "NULL" : $section;
            if ($section !== $last_section) {
                $last_section = $section;
                $count = 1;
                if (isset($graders[$display_section]) && count($graders[$display_section]) > 0) {
                    $section_graders = implode(", ", array_map(function(User $user) { return $user->getId(); }, $graders[$display_section]));
                }
                else {
                    $section_graders = "Nobody";
                }
                if ($peer) {
                    $section_graders = $this->core->getUser()->getId();
                }
                if ($tbody_open) {
                    $return .= <<<HTML
        </tbody>
HTML;
                }
                $tbody_open = true;
                $return .= <<<HTML
        <tr class="info persist-header">
HTML;
            if ($gradeable->isGradeByRegistration()) {
                $return .= <<<HTML
            <td colspan="{$cols}" style="text-align: center">Students Enrolled in Registration Section {$display_section}</td>
HTML;
            } else {
                $return .= <<<HTML
            <td colspan="{$cols}" style="text-align: center">Students Assigned to Rotating Section {$display_section}</td>
HTML;
            }
                $return .= <<<HTML
        </tr>
        <tr class="info">
            <td colspan="{$cols}" style="text-align: center">Graders: {$section_graders}</td>
        </tr>
        <tbody>
HTML;
            }
            $style = "";
            if ($row->getUser()->accessGrading()) {
                $style = "style='background: #7bd0f7;'";
            }

            if($peer) {
                $return .= <<<HTML

            <tr id="user-row-{$row->getUser()->getAnonId()}" {$style}>
                <td>{$count}</td>
                <td>{$row->getUser()->getAnonId()}</td>
HTML;
            }
            else {
                $return .= <<<HTML

            <tr id="user-row-{$row->getUser()->getId()}" {$style}>
                <td>{$count}</td>
                <td>{$display_section}</td>
HTML;
                if($gradeable->isTeamAssignment()) {
                    if ($this->core->getUser()->accessAdmin()) {
                        $return .= <<<HTML

HTML;
                        if($row->getTeam()=== null) {
                            $return .= <<<HTML
                <td><a onclick='adminTeamForm(true, "{$row->getUser()->getId()}", "{$display_section}", [], [], {$gradeable->getMaxTeamSize()});'>
                    <i class="fa fa-pencil" aria-hidden="true"></i></a></td>
                <td></td>
HTML;
                        }
                        else {
                            $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable->getId(), $row->getTeam()->getId(), "user_assignment_settings.json");
                            $user_assignment_setting = FileUtils::readJsonFile($settings_file);
                            $user_assignment_setting_json = json_encode($user_assignment_setting);
                            $members = json_encode($row->getTeam()->getMembers());
                            $return .= <<<HTML
                <td><a onclick='adminTeamForm(false, "{$row->getTeam()->getId()}", "{$display_section}", {$user_assignment_setting_json}, {$members}, {$gradeable->getMaxTeamSize()});'>
                    <i class="fa fa-pencil" aria-hidden="true"></i></a></td>
                <td>{$row->getTeam()->getId()}</td>
HTML;
                        }
                    }
                    if($row->getTeam() === null) {
                        $return .=<<<HTML

                <td>{$row->getUser()->getId()}</td>
HTML;
                    }
                    // Construct a string containing the names of all team members
                     else {
                        $member_list = "";
                        foreach($row->getTeam()->getMembers() as $team_member) {
                            if ($member_list !== "") {
                                $member_list = $member_list . ", ";
                            }

                            $first_name = $this->core->getQueries()->getUserById($team_member)->getDisplayedFirstName();
                            $last_name = $this->core->getQueries()->getUserById($team_member)->getLastName();

                            $member_list = $member_list . $first_name . " " . $last_name;
                        }
                        $return .= <<<HTML
                <td>{$member_list}</td>

HTML;
                    }
                }
                else {
                    $return .= <<<HTML

                <td>{$row->getUser()->getId()}</td>
                <td>{$row->getUser()->getDisplayedFirstName()}</td>
                <td>{$row->getUser()->getLastName()}</td>
HTML;
                }
            }
            if($show_auto_grading_points) {
                if ($highest_version != 0) {
                    if($peer) {
                        $return .= <<<HTML

                <td>{$autograding_score}&nbsp;/&nbsp;{$row->getTotalNonHiddenNonExtraCreditPoints()}</td>
HTML;
                    }
                    else {
                        $return .= <<<HTML

                <td>{$autograding_score}&nbsp;/&nbsp;{$row->getTotalAutograderNonExtraCreditPoints()}</td>
HTML;
                    }
                }
                else {
                    $return .= <<<HTML

                <td></td>
HTML;
                    $btn_class = "btn-primary";
                    $contents = "Grade";
                }
            }
            if($peer) {
                $box_background = "";
                $peer_cmpts = $row->getComponentsGradedBy($this->core->getUser()->getId());
                if(count($peer_cmpts) == 0) {
                    $contents = "Grade";
                    $btn_class = "btn-primary";
                }
                else {
                    $score =0;
                    foreach($peer_cmpts as $cmpts) {
                        //getScore is only the custom "mark" need to write a getTotalComponentScore and also make it clear or change name of Score
                        $score += $cmpts->getScore();
                    }
                    $graded = $autograding_score + $score;
                    // instead of autograding_score it should be total autograding possible
                    // I don't think total_peer_grading_non_extra_credit ever gets set...it should be set in the gradeable constructor
                    $total_possible = $autograding_score + $row->getTotalPeerGradingNonExtraCredit();
                    $contents = "{$score}&nbsp;/&nbsp;{$row->getTotalPeerGradingNonExtraCredit()}";
                    $btn_class = "btn-default";
                }
            }
            else {
                $box_background = "";
                if ($row->getActiveDaysLate() > $row->getAllowedLateDays()) {
                    $box_background = "late-box";
                }
                if (!($row->hasSubmitted())) {
                    $btn_class = "btn-default";
                    $contents = "No Submission";
                }
                else if ($active_version === 0) {
                    $btn_class = "btn-default";
                    $contents = "Cancelled Submission";
                }
                else if ($row->beenTAgraded()) {
                    if($row->validateVersions()) {
                        $btn_class = "btn-default";
                        $contents = "{$row->getGradedTAPoints()}&nbsp;/&nbsp;{$row->getTotalTANonExtraCreditPoints()}";
			            $graded += $row->getGradedTAPoints();
                    }
                    else{
                        $btn_class = "btn-primary";
                        if(!$row->isFullyGraded()){
                            $contents = "Grading Incomplete";
                        }
                        else{
                            $contents = "Version Conflict";
                        }
                    }
                }
                else {
                    $btn_class = "btn-primary";
                    $contents = "Grade";
                }
            }
            if($row->isTeamAssignment() && $row->getTeam()===null) {
                $return .= <<<HTML
                <td><b><i>No Team</i></b></td>
HTML;
            }
            else {
                $return .= <<<HTML
                <td>
HTML;
                $temp_counter = 1;

                //prints the graded questions
                foreach ($row->getComponents() as $component) {
                	$first = true;
                    if(is_array($component)) {
                        foreach($component as $cmpt) {
                            if($cmpt->getGrader() == null) {
                                $question = $cmpt;
                                break;
                            }
                            if($cmpt->getGrader()->getId() == $this->core->getUser()->getId()) {
                                $question = $cmpt;
                                break;
                            }
                        }
                        if($question === null) {
                            $question = $component[0];
                        }
                    }
                    else {
                        $question = $component;
                    }
                    if($question->getGrader() === null || $question === null) {
                    } else {
                    	if ($first == true) {
                    		$first = false;
                    		$return .= <<<HTML
                            {$temp_counter}
HTML;
                    	} else {
                    		$return .= <<<HTML
                           , {$temp_counter}
HTML;
                    	}
                    }
                    $temp_counter++;
                }


                $return .= <<<HTML
                </td>
                <td>
                    <a class="btn {$btn_class}" href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'who_id'=>$row->getUser()->getId(), 'individual'=>'1'))}">
                        {$contents}
                    </a>
                </td>
HTML;
            }

            if($row->validateVersions()) {
                $return .= <<<HTML

                <td><div class="{$box_background}">{$graded}&nbsp;/&nbsp;{$total_possible}</div></td>
HTML;
            }
            else{
                $return .= <<<HTML

                <td></td>
HTML;
            }
            if($highest_version == 0) {
                $return .= <<<HTML

                <td></td>
HTML;
            }
            else if($active_version == $highest_version) {
                $return .= <<<HTML

                <td>{$active_version}</td>
HTML;
            }
            else {
                $return .= <<<HTML

                <td>{$active_version}&nbsp;/&nbsp;{$highest_version}</td>
HTML;
            }
            if(!$peer) {
            $return .= <<<HTML
                <td title="{$grade_viewed}" style="{$grade_viewed_color}">{$viewed_grade}</td>
HTML;
            }
            $return .= <<<HTML
            </tr>
HTML;
            $count++;
        }
        $return .= <<<HTML
        </tbody>
HTML;
        if ($gradeable->isTeamAssignment() && count($empty_teams) > 0) {
            $return .= <<<HTML
            <tr class="info persist-header">
                <td colspan="{$cols}" style="text-align: center">Empty Teams</td>
            </tr>
        <tbody>
HTML;
            $count = 1;
            foreach($empty_teams as $team) {
                $display_section = $gradeable->isGradeByRegistration() ? $team->getRegistrationSection() : $team->getRotatingSection();
                if ($display_section == null) $display_section = "NULL";
                $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable->getId(), $team->getId(), "user_assignment_settings.json");
                $user_assignment_setting = FileUtils::readJsonFile($settings_file);
                $user_assignment_setting_json=json_encode($user_assignment_setting);
                $return .= <<<HTML
            <tr id="{empty-team-row-{$team->getId()}}" {$style}>
                <td>{$count}</td>
                <td>{$display_section}</td>
                <td><a onclick='adminTeamForm(false, "{$team->getId()}", "{$display_section}", {$user_assignment_setting_json}, [], {$gradeable->getMaxTeamSize()});'>
                    <i class="fa fa-pencil" aria-hidden="true"></i></a></td>
                <td>{$team->getId()}</td>
HTML;
                for ($i = 4; $i < $cols; $i++) {
                    $return .= <<<HTML
                <td></td>
HTML;
                }
                $return .= <<<HTML
            </tr>
HTML;
                $count++;
            }
            $return .= <<<HTML
        </tbody>
HTML;
        }
        $return .= <<<HTML
    </table>
</div>
HTML;
        return $return;
    }

    public function adminTeamForm($gradeable, $sections) {
        $reg_or_rot = $gradeable->isGradeByRegistration() ? "Registration" : "Rotating";
        $students = $this->core->getQueries()->getAllUsers();
        $student_full = array();
        foreach ($students as $student) {
            $student_full[] = array('value' => $student->getId(),
                                    'label' => str_replace("'","&#039;",$student->getDisplayedFirstName()).' '.str_replace("'","&#039;",$student->getLastName()).' <'.$student->getId().'>');
        }
        $student_full = json_encode($student_full);
        $return = <<<HTML
<div class="popup-form" id="admin-team-form" style="width:550px; margin-left:-250px;">
    <form method="post" action="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'submit_team_form', 'gradeable_id'=>$gradeable->getId()))}">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <input type="hidden" name="new_team" />
    <input type="hidden" name="new_team_user_id" />
    <input type="hidden" name="edit_team_team_id" />
    <input type="hidden" name="num_users" />
    <input type="hidden" id="student_full_id" value='{$student_full}'/>
HTML;
    if (isset($_REQUEST['view'])) {
        $return .= <<<HTML
    <input type="hidden" name="view" value="{$_REQUEST['view']}" />
HTML;
    }
    $return .= <<<HTML
    <h2 id="admin-team-title"></h2>
    <br />
    <div id="admin-team-members" style="width:50%;"></div>
    <div>
        {$reg_or_rot} Section:<br />
        <select name="section">
HTML;
        foreach ($sections as $section) {
            $return .= <<<HTML
            <option value="{$section}">Section {$section}</option>
HTML;
        }
        $return .= <<<HTML
            <option value="NULL">Section NULL</option>
        </select>
    </div>
    <br />
    <br />
    <h4 id="admin-team-history-title"></h4>
    <div id="admin-team-history-left" style="width:28%;"></div>
    <div id="admin-team-history-right" style="width:62%;"></div>
    <div style="float: right; width: auto; margin-top: 10px">
        <a onclick="$('#admin-team-form').css('display', 'none');" class="btn btn-danger">Cancel</a>
        <input class="btn btn-primary" type="submit" value="Submit" />
    </div>
    </form>
</div>
HTML;
        return $return;
    }

    //The student not in section variable indicates that an full access grader is viewing a student that is not in their
    //assigned section. canViewWholeGradeable determines whether hidden testcases can be viewed.
    public function hwGradingPage(Gradeable $gradeable, float $progress, string $prev_id, string $next_id, string $individual, $studentNotInSection=false, $canViewWholeGradeable=false) {
        $peer = false;
        if($this->core->getUser()->getGroup()==4 && $gradeable->getPeerGrading()) {
            $peer = true;
        }
        $user = $gradeable->getUser();
        $your_user_id = $this->core->getUser()->getId();
        $prev_href = $this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'who_id'=>$prev_id, 'individual'=>$individual));
        $next_href = $this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'who_id'=>$next_id, 'individual'=>$individual));
        $return = <<<HTML
<div id="bar_wrapper" class="draggable">
<div class="grading_toolbar">
HTML;
    //If the student is in our section, add a clickable previous arrow, else add a grayed out one.
    if(!$studentNotInSection){
    $return .= <<< HTML
        <a href="javascript:void(0);" onclick="gotoPrevStudent();" data-href="{$prev_href}" id="prev-student"><i title="Go to the previous student" class="fa fa-chevron-left icon-header"></i></a>
HTML;
    }
    else{
        $return .= <<< HTML
        <i title="Go to the previous student" class="fa fa-chevron-left icon-header" style="color:grey"></i>
HTML;
    }
    $return .= <<< HTML
    <a href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'details', 'gradeable_id'=>$gradeable->getId()))}"><i title="Go to the main page" class="fa fa-home icon-header" ></i></a>
HTML;
    //If the student is in our section, add a clickable next arrow, else add a grayed out one.
    if(!$studentNotInSection){
    $return .= <<<HTML
    <a href="javascript:void(0);" onclick="gotoNextStudent();" data-href="{$next_href}" id="next-student"><i title="Go to the next student" class="fa fa-chevron-right icon-header"></i></a>
HTML;
    }
    else{
        $return .= <<< HTML
        <i title="Go to the next student" class="fa fa-chevron-right icon-header" style="color:grey"></i>
HTML;
    }
    $return .= <<< HTML

    <i title="Reset Rubric Panel Positions (Press R)" class="fa fa-refresh icon-header" onclick="toggleAutograding(); updateCookies();"></i>
    <i title="Show/Hide Auto-Grading Testcases (Press A)" class="fa fa-list-alt icon-header" onclick="toggleRubric(); updateCookies();"></i>
HTML;
    if ($gradeable->useTAGrading()) {
            $return .= <<<HTML
    <i title="Show/Hide Grading Rubric (Press G)" class="fa fa fa-pencil-square-o icon-header" onclick="toggleSubmissions(); updateCookies();"></i>
HTML;
        }
        $return .= <<<HTML
    <i title="Show/Hide Submission and Results Browser (Press O)" class="fa fa-folder-open icon-header" onclick="toggleInfo(); updateCookies();"></i>
HTML;
        if(!$peer) {
            $return .= <<<HTML
    <i title="Show/Hide Student Information (Press S)" class="fa fa-user icon-header" onclick="resetModules(); updateCookies();"></i>
HTML;
        }
        $return .= <<<HTML
</div>

<div class="progress_bar">
    <progress class="progressbar" max="100" value="{$progress}" style="width:70%; height: 100%;"></progress>
    <div class="progress-value" style="display:inline;"></div>
</div>
</div>


<div id="autograding_results" class="draggable rubric_panel" style="left:15px; top:170px; width:48%; height:36%;">
    <div class="draggable_content">
    <span class="grading_label">Auto-Grading Testcases</span>
    <button class="btn btn-default" onclick="openAllAutoGrading()">Expand All</button>
    <button class="btn btn-default" onclick="closeAllAutoGrading()">Close All</button>
    <div class="inner-container">
HTML;
        if ($gradeable->getActiveVersion() === 0){
            $return .= <<<HTML
        <h4>No Submission</h4>
HTML;
        }
        else if (count($gradeable->getTestcases()) === 0) {
            $return .= <<<HTML
        <h4>No Autograding For This Assignment</h4>
HTML;
        }
        else{
            $return .= $this->core->getOutput()->renderTemplate('AutoGrading', 'showResults', $gradeable, $canViewWholeGradeable);
        }
        $return .= <<<HTML
    </div>
    </div>
</div>

<div id="submission_browser" class="draggable rubric_panel" style="left:15px; bottom:40px; width:48%; height:30%">
    <div class="draggable_content">
    <span class="grading_label">Submissions and Results Browser</span>
    <button class="btn btn-default expand-button" data-linked-type="submissions" data-clicked-state="wasntClicked" id="toggleSubmissionButton">Open/Close Submissions</button>
HTML;

    if(count($gradeable->getVcsFiles()) != 0) { //check if there are vcs files, if yes display the toggle button, else don't display it
        $return .= <<<HTML
        <button class="btn btn-default expand-button" data-linked-type="checkout" data-clicked-state="wasntClicked"  id="togglCheckoutButton">Open/Close Checkout</button>
HTML;
    }

$return .= <<<HTML
    <button class="btn btn-default expand-button" data-linked-type="results" data-clicked-state="wasntClicked"  id="toggleResultButton">Open/Close Results</button>
    <script type="text/javascript">
        $(document).ready(function(){
            //note the commented out code here along with the code where files are displayed that is commented out
            //is intended to allow open and close to change dynamically on click
            //the problem is currently if you click the submissions folder then the text won't change b/c it's being double clicked effectively.
            $(".expand-button").on('click', function(){
                // $(this).attr('clicked-state', "clicked");
                // updateValue($(this), "Open", "Close");
                openAll( 'openable-element-', $(this).data('linked-type'))
                // $.when(openAll( 'openable-element-', $(this).data('linked-type'))).then(function(){
                //     console.log('HELLLO');
                // });
            })
        });
    </script>
HTML;
        if(!$peer) {
        $return .= <<<HTML
    <button class="btn btn-default" onclick="downloadZip('{$gradeable->getId()}','{$gradeable->getUser()->getId()}')">Download Zip File</button>
HTML;
        }
        $return .= <<<HTML
    <br />
    <div class="inner-container" id="file-container">
HTML;
        function add_files(&$files, $new_files, $start_dir_name) {
            $files[$start_dir_name] = array();
            foreach($new_files as $file) {
                $path = explode('/', $file['relative_name']);
                array_pop($path);
                $working_dir = &$files[$start_dir_name];
                foreach($path as $dir) {
                    if (!isset($working_dir[$dir])) {
                        $working_dir[$dir] = array();
                    }
                    $working_dir = &$working_dir[$dir];
                }
                $working_dir[$file['name']] = $file['path'];
            }
        }
        function display_files($files, &$count, $indent, &$return, $filename) {
            $name = "a" . $filename;
            foreach ($files as $dir => $path) {
                if (!is_array($path)) {
                    $name = htmlentities($dir);
                    $dir = rawurlencode(htmlspecialchars($dir));
                    $path = rawurlencode(htmlspecialchars($path));
                    $indent_offset = $indent * -15;
                    $return .= <<<HTML
                <div>
                    <div class="file-viewer">
                        <a class='openAllFile{$filename} openable-element-{$filename}' onclick='openFrame("{$dir}", "{$path}", {$count}); updateCookies();'>
                            <span class="fa fa-plus-circle" style='vertical-align:text-bottom;'></span>
                        {$name}</a> &nbsp;
                        <a onclick='openFile("{$dir}", "{$path}")'><i class="fa fa-window-restore" aria-hidden="true" title="Pop up the file in a new window"></i></a>
                        <a onclick='downloadFile("{$dir}", "{$path}")'><i class="fa fa-download" aria-hidden="true" title="Download the file"></i></a>
                    </div><br/>
                    <div id="file_viewer_{$count}" style="margin-left:{$indent_offset}px" data-file_name="{$dir}" data-file_url="{$path}"></div>
                </div>
HTML;
                    $count++;
                }
            }
            foreach ($files as $dir => $contents) {
                if (is_array($contents)) {
                    $dir = htmlentities($dir);
                    $url = reset($contents);
                    $return .= <<<HTML
            <div>
                <div class="div-viewer">
                    <a class='openAllDiv openAllDiv{$filename} openable-element-{$filename}' id={$dir} onclick='openDiv({$count}); updateCookies();'>
                        <span class="fa fa-folder open-all-folder" style='vertical-align:text-top;'></span>
                    {$dir}</a>
                </div><br/>
                <div id='div_viewer_{$count}' style='margin-left:15px; display: none' data-file_name="{$dir}">
HTML;
                    $count++;
                    display_files($contents, $count, $indent+1, $return, $filename);
                    $return .= <<<HTML
                </div>
            </div>
HTML;
                }
            }
        }
        $files = array();
        $submissions = array();
        $results = array();
        $checkout = array();

        // NOTE TO FUTURE DEVS: There is code around line 830 (ctrl-f openAll) which depends on these names,
        // if you change here, then change there as well
        // order of these statements matter I believe

        add_files($submissions, array_merge($gradeable->getMetaFiles(), $gradeable->getSubmittedFiles()), 'submissions');

        $vcsFiles = $gradeable->getVcsFiles();
        if( count( $vcsFiles ) != 0 ) { //if there are checkout files, then display folder, otherwise don't
            add_files($checkout,  $vcsFiles, 'checkout');
        }

        add_files($results, $gradeable->getResultsFiles(), 'results');

        $count = 1;
        display_files($submissions,$count,1,$return, "submissions"); //modifies the count var here within display_files

        if( count( $vcsFiles ) != 0 ) { //if there are checkout files, then display folder, otherwise don't
            display_files($checkout,$count,1,$return, "checkout");
        }

        display_files($results,$count,1,$return, "results"); //uses the modified count variable b/c old code did this not sure if needed
        $files = array_merge($submissions, $checkout, $results );

        $return .= <<<HTML
        <script type="text/javascript">
            // $(document).ready(function(){
            //     $(".openAllDiv").on('click', function(){
            //         if($(this).attr('id') == 'results' || $(this).attr('id') == 'submissions' || $(this).attr('id') =='checkout'){
            //             var elem = $('[data-linked-type="' + $(this).attr('id') + '"]');
            //             if(elem.data('clicked-state') == "wasntClicked"){
            //                 updateValue(elem, "Open", "Close");
            //             }
            //         }
            //     });
            // });
        </script>
    </div>
    </div>
</div>
HTML;

        $user = $gradeable->getUser();
        if(!$peer) {
            $return .= <<<HTML

<div id="student_info" class="draggable rubric_panel" style="right:15px; bottom:40px; width:48%; height:30%;">
    <div class="draggable_content">
    <span class="grading_label">Student Information</span>
    <div class="inner-container">
        <h5 class='label' style="float:right; padding-right:15px;">Browse Student Submissions:</h5>
        <div class="rubric-title">
HTML;
            $who = $gradeable->getUser()->getId();
            $onChange = "versionChange('{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'action' => 'grade', 'gradeable_id' => $gradeable->getId(), 'who_id'=>$who, 'individual'=>$individual,
                                                      'gradeable_version' => ""))}', this)";
            $formatting = "font-size: 13px;";
            $return .= <<<HTML
            <div style="float:right;">
HTML;
            $return .= $this->core->getOutput()->renderTemplate('AutoGrading', 'showVersionChoice', $gradeable, $onChange, $formatting);

            // If viewing the active version, show cancel button, otherwise show button to switch active
            if ($gradeable->getCurrentVersionNumber() > 0) {
                if ($gradeable->getCurrentVersionNumber() == $gradeable->getActiveVersion()) {
                    $version = 0;
                    $button = '<input type="submit" class="btn btn-default btn-xs" style="float:right; margin: 0 10px;" value="Cancel Student Submission">';
                }
                else {
                    $version = $gradeable->getCurrentVersionNumber();
                    $button = '<input type="submit" class="btn btn-default btn-xs" style="float:right; margin: 0 10px;" value="Grade This Version">';
                }
                $return .= <<<HTML
                <br/><br/>
                <form style="display: inline;" method="post" onsubmit='return checkTaVersionChange();'
                        action="{$this->core->buildUrl(array('component' => 'student',
                                                             'action' => 'update',
                                                             'gradeable_id' => $gradeable->getId(),
                                                             'new_version' => $version, 'ta' => true, 'who' => $who, 'individual' => $individual))}">
                    <input type='hidden' name="csrf_token" value="{$this->core->getCsrfToken()}" />
                    {$button}
                </form>
HTML;
            }
            $return .= <<<HTML
            </div>
            <div>
HTML;

            if ($gradeable->isTeamAssignment() && $gradeable->getTeam() !== null) {
            $return .= <<<HTML
                <b>Team:<br/>
HTML;
                foreach ($gradeable->getTeam()->getMembers() as $team_member) {
                    $team_member = $this->core->getQueries()->getUserById($team_member);
                    $return .= <<<HTML
                &emsp;{$team_member->getDisplayedFirstName()} {$team_member->getLastName()} ({$team_member->getId()})<br/>
HTML;
                }
            }
            else {
                $return .= <<<HTML
                <b>{$user->getDisplayedFirstName()} {$user->getLastName()} ({$user->getId()})<br/>
HTML;
            }

            $return .= <<<HTML
                Submission Number: {$gradeable->getActiveVersion()} / {$gradeable->getHighestVersion()}<br/>
                Submitted: {$gradeable->getSubmissionTime()->format("m/d/Y H:i:s")}<br/></b>
            </div>
HTML;
            $return .= <<<HTML
            <form id="rubric_form">
                <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
                <input type="hidden" name="g_id" value="{$gradeable->getId()}" />
                <input type="hidden" name="u_id" value="{$user->getId()}" />
                <input type="hidden" name="individual" value="{$individual}" />
                <input type="hidden" name="graded_version" value="{$gradeable->getActiveVersion()}" />
HTML;

            //Late day calculation
            $ldu = new LateDaysCalculation($this->core, $gradeable->getUser()->getId());
            $return .= $ldu->generateTableForUserDate($gradeable->getName(), $user->getId(), $gradeable->getDueDate());
            $late_days_data = $ldu->getGradeable($user->getId(), $gradeable->getId());
            $status = $late_days_data['status'];

            $color = "green";
            if($status != "Good" && $status != "Late") {
                $color = "red";
                $my_color="'#F62817'"; // fire engine red
                $my_message="Late Submission";
                $return .= <<<HTML
            <script>
                $('body').css('background', $my_color);
                $('#bar_wrapper').append("<div id='bar_banner' class='banner'>$my_message</div>");
                $('#bar_banner').css('background-color', $my_color);
                $('#bar_banner').css('color', 'black');
            </script>
HTML;
            }
            $return .= <<<HTML
            <b>Status:</b> <span style="color:{$color};">{$status}</span><br />
        </div>
    </div>
    </div>
</div>
HTML;
        }
        if($peer) {
            $span_style = 'style="display:none;"';
            $checked = 'disabled';
        }
        else {
            $span_style = '';
            $checked = 'checked';
        }
        $empty = "";
        if(!$gradeable->useTAGrading()) {
            $empty = "empty";
        }
        $display_verify_all = false;
        //check if verify all button should be shown or not
        foreach ($gradeable->getComponents() as $component) {
            if(!$component->getGrader()){
              continue;
            }
            if($component->getGrader()->getId() !== $this->core->getUser()->getId() && $this->core->getUser()->accessFullGrading()){
                $display_verify_all = true;
                break;
            }
        }
        $return .= <<<HTML
<div id="grading_rubric" class="draggable rubric_panel {$empty}" style="right:15px; top:140px; width:48%; height:42%;">
    <div class="draggable_content">
    <span class="grading_label">Grading Rubric</span>
HTML;
        if($gradeable->useTAGrading()) {
          $return .= <<<HTML
    <div style="float: right; float: right; position: relative; top: 10px; right: 1%;">
HTML;
          if($display_verify_all){
            $return .= <<<HTML
        <input id='verifyAllButton' type='button' style="display: inline;" class="btn btn-default" value='Verify All' onclick='verifyMark("{$gradeable->getId()}",-1,"{$user->getAnonId()}",true);'/>
HTML;
          }
          $return .= <<<HTML
        <span style="padding-right: 10px"> <input type="checkbox" id="autoscroll_id" onclick="updateCookies();"> Auto scroll / Auto open </span>
        <span {$span_style}> <input type='checkbox' id="overwrite-id" name='overwrite' value='1' onclick="updateCookies();" {$checked}/> Overwrite Grader </span>
    </div>
HTML;
        $break_onclick = "";
        $disabled = '';
        if($gradeable->getActiveVersion() == 0){
            $disabled='disabled';
            $break_onclick = "return false; ";
            $my_color="'#FF8040'"; // mango orange
            $my_message="Cancelled Submission";
            if($gradeable->hasSubmitted()){
                $return .= <<<HTML
                <script>
                    $('body').css('background', $my_color);
                    $('#bar_wrapper').append("<div id='bar_banner' class='banner'>$my_message</div>");
                    $('#bar_banner').css('background-color', $my_color);
                    $('#bar_banner').css('color', 'black');
                </script>
                <div class="red-message" style="text-align: center">$my_message</div>
HTML;
            } else {
                $my_color="'#C38189'";  // lipstick pink (purple)
                $my_message="No Submission";
                $return .= <<<HTML
                <script>
                    $('body').css('background', $my_color);
                    $('#bar_wrapper').append("<div id='bar_banner' class='banner'>$my_message</div>");
                    $('#bar_banner').css('background-color', $my_color);
                    $('#bar_banner').css('color', 'black');
                </script>
                <div class="red-message" style="text-align: center">$my_message</div>
HTML;
            }
        } else if($gradeable->getCurrentVersionNumber() != $gradeable->getActiveVersion()){
            $disabled='disabled';
            $break_onclick = "return false; ";
            $return .= <<<HTML
            <div class="red-message" style="text-align: center">Select the correct submission version to grade</div>
HTML;
        }

        $num_questions = count($gradeable->getComponents());

        // if use student components, get the values for pages from the student's submissions
        $files = $gradeable->getSubmittedFiles();
        $student_pages = array();
        foreach ($files as $filename => $content) {
            if ($filename == "student_pages.json") {
                $path = $content["path"];
                $student_pages = FileUtils::readJsonFile($content["path"]);
            }
        }

        $return .= <<<HTML
    <div style="margin:3px;">
        <table class="ta-rubric-table ta-rubric-table-background" id="rubric-table" data-gradeable_id="{$gradeable->getId()}" data-user_id="{$user->getAnonId()}" data-active_version="{$gradeable->getActiveVersion()}" data-num_questions="{$num_questions}" data-your_user_id="{$this->core->getUser()->getId()}">
            <tbody>
HTML;

        $c = 1;
        $precision = floatval($gradeable->getPointPrecision());
        $num_questions = count($gradeable->getComponents());
        $your_user_id = $this->core->getUser()->getId();

        foreach ($gradeable->getComponents() as $component) {
            if($peer && !is_array($component)) continue;
            $question = null;
            /* @var GradeableComponent $question */
            $show_graded_info = true;
            $num_peer_components = 0;
            if(is_array($component)) {
                $num_peer_components = count($component);
                foreach($component as $cmpt) {
                    if($cmpt->getGrader() == null) {
                        $question = $cmpt;
                        break;
                    }
                    if($cmpt->getGrader()->getId() == $this->core->getUser()->getId()) {
                        $question = $cmpt;
                        break;
                    }
                }
                if($question === null) {
                    $show_graded_info = false;
                    $question = $component[0];
                }
            }
            else {
                $question = $component;
            }
            if($question->getOrder() == -1) continue;
            $lower_clamp = $question->getLowerClamp();
            $default = $question->getDefault();
            $upper_clamp = $question->getUpperClamp();
            $max = 10000;
            $min = -10000;
            // hide auto-grading if it has no value
            if (($question->getScore() == 0) && (substr($question->getTitle(), 0, 12) === "AUTO-GRADING")) {
                $question->setScore(floatval($gradeable->getGradedAutograderPoints()));
            }

            if(substr($question->getTitle(), 0, 12) === "AUTO-GRADING") {
                $disabled = 'disabled';
            }

            $return .= <<<HTML
                <tr>
HTML;
            $penalty = !(intval($question->getMaxValue()) >= 0);
            $message = htmlentities($question->getTitle());
            $message = "<b>{$message}</b>";  // {$num_peer_components}</b>";
            if ($question->getGradedVersion() != -1 && $gradeable->getActiveVersion() != $question->getGradedVersion()) {
                $message .= "<span id='wrong_version_{$c}' style='color:rgb(200, 0, 0); font-weight: bold; font-size:medium;'>  " . "Please edit or ensure that comments from version " . $question->getGradedVersion() . " still apply.</span>";
            }
            $note = htmlentities($question->getTaComment());
            if ($note != "") {
                $note = "<br/><div style='margin-bottom:5px; color:#777;'><i><b>Note to TA: </b>" . $note . "</i></div>";
            }
            $page = intval($question->getPage());
            // if the page is determined by the student json
            if ($page == -1) {
                // usually the order matches the json
                if ($student_pages[intval($question->getOrder())]["order"] == intval($question->getOrder())) {
                    $page = intval($student_pages[intval($question->getOrder())]["page #"]);
                }
                // otherwise, iterate through until the order matches
                else {
                    foreach ($student_pages as $student_page) {
                        if ($student_page["order"] == intval($question->getOrder())) {
                            $page = intval($student_page["page #"]);
                            break;
                        }
                    }
                }
            }
            if ($page > 0) {
                $message .= "<i> Page #: " . $page . "</i>";
            }

            //get the grader's id if it exists
            $grader_id = "";
            $graded_color = "";
            $displayVerifyUser = false;
            if($question->getGrader() === null || !$show_graded_info) {
                $grader_id = "Ungraded!";
                $graded_color = "";
            } else {
                $grader_id = "Graded by " . $question->getGrader()->getId();
                $graded_color = " background-color: #eebb77";
                if($this->core->getUser()->getId() !== $question->getGrader()->getId() && $this->core->getUser()->accessFullGrading()){
                    $displayVerifyUser = true;
                }
            }

            $return .= <<<HTML
                    <td id="title-{$c}" style="font-size: 12px;" colspan="4" data-changebg="true">
                        <b><span id="progress_points-{$c}" style="display: none;" data-changedisplay1="true"></span></b>
                        {$message}
                        <div style="float: right;">
HTML;
            if($displayVerifyUser){
              $return .= <<<HTML
                            <span style="display: inline; color: red;">
                            <input type="button" class = "btn btn-default" onclick="verifyMark('{$gradeable->getId()}','{$question->getId()}','{$user->getAnonId()}')" value = "Verify Grader"/>
                            </span>
HTML;
            }
            $return .= <<<HTML
                            <span id="graded-by-{$c}" style="font-style: italic; padding-right: 10px;">{$grader_id}</span>
                            <span id="save-mark-{$c}" style="cursor: pointer;  display: none;" data-changedisplay1="true"> <i class="fa fa-check" style="color: green;" aria-hidden="true" onclick="{$break_onclick}; closeMark({$c}, true);">Done</i> </span>
                        </div>
                        </span> <span id="ta_note-{$c}" style="display: none;" data-changedisplay1="true"> {$note}</span>
                        <span id="page-{$c}" style="display: none;">{$page}</span>
HTML;
            $student_note = htmlentities($question->getStudentComment());
            if ($student_note != ''){
                $student_note = "<div style='margin-bottom:5px; color:#777;'><i><b>Note to Student: </b>" . $student_note . "</i></div>";
            }
            $return .= <<<HTML
                        <span id="student_note-{$c}" style="display: none;" data-changedisplay1="true">{$student_note}</span>
                    </td>
                    <td id="title-cancel-{$c}" style="font-size: 12px; display: none; width: 5%;" colspan="0" data-changebg="true" data-changedisplay1="true">
                        <div>
                            <span id="cancel-mark-{$c}" onclick="{$break_onclick}; closeMark(${c}, false);" style="cursor: pointer; float: right;"> <i class="fa fa-times" style="color: red;" aria-hidden="true">Cancel</i></span>
                        </div>
                    </td>
                </tr>
HTML;

            //gets the initial point value and text


            if((!$question->getHasMarks() && !$question->getHasGrade()) || !$show_graded_info) {
                $initial_text = "Click me to grade!";
            }
            else if($show_graded_info) {
                $nl = "<br>";
                $initial_text = $question->getGradedTAComments($nl, false, $gradeable);
            }


            $question_points = $question->getGradedTAPoints();

            if((!$question->getHasMarks() && !$question->getHasGrade()) || !$show_graded_info) {
                $question_points = " ";
            }

            $background = "";
            if ($question_points > $question->getMaxValue()) {
                $background = "background-color: #D8F2D8;";
            }
            else if ($question_points < 0) {
                $background = "background-color: #FAD5D3;";
            }

            $return .= <<<HTML
                <tr id="summary-{$c}" style="{$graded_color}" onclick="openMark({$c});" data-changedisplay2="true" data-question_id="{$question->getId()}" data-min="{$min}" data-max="{$max}" data-precision="{$precision}">
                    <td style="white-space:nowrap; vertical-align:middle; text-align:center; {$background}" colspan="1">
                        <strong><span id="grade-{$c}" name="grade-{$c}" class="grades" data-lower_clamp="{$question->getLowerClamp()}" data-default="{$question->getDefault()}" data-max_points="{$question->getMaxValue()}" data-upper_clamp="{$question->getUpperClamp()}"> {$question_points}</span> / {$question->getMaxValue()}</strong>
                    </td>
                    <td style="width:98%;" colspan="3">
                        <div id="rubric-{$c}">
                            <span id="rubric-textarea-{$c}" name="comment-{$c}" rows="4" style="width:95%; height:100%; min-height:20px;  float:left; cursor: pointer;">{$initial_text}</span>
                        </div>
                    </td>
                </tr>
                <tbody id="marks-parent-{$c}" style="display: none; background-color: #e6e6e6" colspan="4" data-question_id="{$question->getId()}" data-changedisplay1="true">
                </tbody>

                <tbody id="marks-extra-{$c}" style="display: none; background-color: #e6e6e6" colspan="4" data-question_id="{$question->getId()}" data-changedisplay1="true">
HTML;

            $d = 0;
            $first = true;
            $noChange = "";
            $has_custom_mark = false;
            if (($question->getScore() == 0 && $question->getComment() == "") || !$show_graded_info) {
                $has_custom_mark = false;
            }
            else {
                $has_custom_mark = true;
            }
            $icon_mark = ($has_custom_mark === true) ? "fa-square" : "fa-square-o";
            if(!$peer) {
                $return .= <<<HTML
                    <tr>
                        <td colspan="4">
                            <span style="cursor: pointer;" onclick="{$break_onclick} addMark(this, {$c}, '', {$min}, {$max}, '{$precision}', '{$gradeable->getId()}', '{$user->getAnonId()}', {$gradeable->getActiveVersion()}, {$question->getId()}, '{$your_user_id}'); return false;"><i class="fa fa-plus-square " aria-hidden="true"></i>
                            Add New Common Mark</span>
                        </td>
                    </tr>
HTML;
            }
            $return .= <<<HTML
                    <tr id="mark_custom_id-{$c}" name="mark_custom_{$c}">
                        <td colspan="1" style="text-align: center; white-space: nowrap;">

                        <span onclick=""> <i class="fa {$icon_mark} mark fa-lg" name="mark_icon_{$c}_custom" style="visibility: visible; cursor: pointer; position: relative; top: 2px;"></i>&nbsp;</span>
                        <input name="mark_points_custom_{$c}" type="number" step="{$precision}" onchange="fixMarkPointValue(this); checkIfSelected(this); updateProgressPoints({$c});" value="{$question->getScore()}" min="{$min}" max="{$max}" style="width: 50%; resize:none;  min-width: 50px;">
                        </td>
                        <td colspan="3" style="white-space: nowrap;">
                            Custom: <textarea name="mark_text_custom_{$c}" onkeyup="autoResizeComment(event); checkIfSelected(this);" onchange="checkIfSelected(this); updateProgressPoints({$c});" rows="1" placeholder="Custom message for student..." style="width:80.4%; resize:none;">{$question->getComment()}</textarea>
                        </td>
                    </tr>
                </tbody>

HTML;
            $c++;
        }
        if ($peer) {
            $break_onclick = 'return false;';
            $disabled = 'disabled';
        }
        $overallComment = htmlentities($gradeable->getOverallComment(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $return .= <<<HTML
                <tr>
                    <td id="title-general" colspan="4" onclick="{$break_onclick}; closeGeneralMessage(true);" data-changebg="true">
                        <b>General Comment</b>
                        <div style="float: right;">
                            <span id="save-mark-general" style="cursor: pointer;  display: none;" data-changedisplay1="true"> <i class="fa fa-check" style="color: green;" aria-hidden="true">Done</i> </span>
                        </div>
                    </td>
                    <td id="title-cancel-general" style="font-size: 12px; display: none; width: 5%" colspan="0" data-changebg="true" data-changedisplay1="true">
                        <span id="cancel-mark-general" onclick="{$break_onclick}; closeGeneralMessage(false);" style="cursor: pointer; display: none; float: right;" data-changedisplay1="true"> <i class="fa fa-times" style="color: red;" aria-hidden="true">Cancel</i></span>
                    </td>
                </tr>
                <tr id="summary-general" style="" onclick="{$break_onclick}; openGeneralMessage();" data-changedisplay2="true">
                    <td style="white-space:nowrap; vertical-align:middle; text-align:center" colspan="1">
                    </td>
                    <td style="width:98%;" colspan="3">
                        <div id="rubric-custom">
                            <span id="rubric-textarea-custom" name="comment-custom" rows="4" style="width:95%; height:100%; min-height:20px;  float:left;"><pre>{$overallComment}</pre></span>
                        </div>
                    </td>
                </tr>
                <tbody id="extra-general" style="display: none" colspan="4" data-changebg="true" data-changedisplay1="true">
                    <tr>
                        <td colspan="4">
                            <textarea id="comment-id-general" name="comment-general" rows="5" style="width:98%; height:100%; min-height:100px; resize:none; float:left;" onkeyup="autoResizeComment(event);" placeholder="Overall message for student about the gradeable..." comment-position="0" {$disabled}>{$overallComment}</textarea>
                        </td>
                    </tr>
                </tbody>
HTML;

        if ($peer) {
            $total_points = $gradeable->getTotalNonHiddenNonExtraCreditPoints() + $gradeable->getTotalPeerGradingNonExtraCredit();
        }
        else {
            $total_points = $gradeable->getTotalAutograderNonExtraCreditPoints() + $gradeable->getTotalTANonExtraCreditPoints();
        }

        $return .= <<<HTML
                <tr>
                    <td style="background-color: #EEE; border-left: 1px solid #EEE; border-top:5px #FAA732 solid;" colspan="1"><strong>TOTAL</strong></td>
                    <td style="background-color: #EEE; border-top:5px #FAA732 solid;" colspan="1"><strong id="score_total">0 / {$total_points}&emsp;&emsp;&emsp;
                        AUTO-GRADING {$gradeable->getGradedAutograderPoints()} / {$gradeable->getTotalAutograderNonExtraCreditPoints()}</strong></td>
                    <td style="background-color: #EEE; border-left: 1px solid #EEE; border-top:5px #FAA732 solid;" colspan="2"></td>
                </tr>
            </tbody>
        </table>
HTML;
        $return .= <<<HTML
        <div style="width:100%;">
HTML;
        $now = new \DateTime('now');
        $return .= <<<HTML
            </form>
        </div>
        </div>
    </div>

HTML;
        }

        $this->core->getOutput()->addInternalJs('ta-grading.js');
        $this->core->getOutput()->addInternalJs('ta-grading-mark.js');

        $return .= <<<HTML
</div>

<script type="text/javascript">
//
// This is needed to resolve conflicts between Chrome and other browsers
//   where Chrome can only do synchronous ajax calls on 'onbeforeunload'
//   and other browsers can only do synchronous ajax calls on 'onunload'
//
// Reference:
//    https://stackoverflow.com/questions/4945932/window-onbeforeunload-ajax-request-in-chrome
//
var __unloadRequestSent = false;
function unloadSave() {
    if (!__unloadRequestSent) {
        __unloadRequestSent = true;
        saveLastOpenedMark('{$gradeable->getId()}' ,'{$user->getAnonId()}', {$gradeable->getActiveVersion()}, '{$your_user_id}', '-1', false, function() {
        }, function() {
            // Unable to save so try saving at a different time
            __unloadRequestSent = false;
        });
    }
}
// Will work for Chrome
window.onbeforeunload = unloadSave;
// Will work for other browsers
window.onunload = unloadSave;
</script>
<script type="text/javascript">
    function openFrame(html_file, url_file, num) {
        var iframe = $('#file_viewer_' + num);
        if (!iframe.hasClass('open')) {
            var iframeId = "file_viewer_" + num + "_iframe";
            var directory = "";
            if (url_file.includes("submissions")) {
                directory = "submissions";
            }
            else if (url_file.includes("results")) {
                directory = "results";
            }
            else if (url_file.includes("checkout")) {
                directory = "checkout";
            }
            // handle pdf
            if (url_file.substring(url_file.length - 3) === "pdf") {
                iframe.html("<iframe id='" + iframeId + "' src='{$this->core->getConfig()->getSiteUrl()}&component=misc&page=display_file&dir=" + directory + "&file=" + html_file + "&path=" + url_file + "&ta_grading=true' width='95%' height='1200px' style='border: 0'></iframe>");
            }
            else {
                iframe.html("<iframe id='" + iframeId + "' onload='resizeFrame(\"" + iframeId + "\");' src='{$this->core->getConfig()->getSiteUrl()}&component=misc&page=display_file&dir=" + directory + "&file=" + html_file + "&path=" + url_file + "&ta_grading=true' width='95%' style='border: 0'></iframe>");
            }
            iframe.addClass('open');
        }

        if (!iframe.hasClass('shown')) {
            iframe.show();
            iframe.addClass('shown');
            $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-plus-circle').addClass('fa-minus-circle');
        }
        else {
            iframe.hide();
            iframe.removeClass('shown');
            $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-minus-circle').addClass('fa-plus-circle');
        }
        return false;
    }

    function calculatePercentageTotal() {
        var total=0;

        $('#rubric-table').find('.grades').each(function() {
            if(!isNaN(parseFloat($(this)[0].innerHTML))) {
                total += parseFloat($(this)[0].innerHTML);
            }
        });

        total = Math.max(parseFloat(total + {$gradeable->getGradedAutograderPoints()}), 0);

        $("#score_total").html(total+" / "+parseFloat({$gradeable->getTotalAutograderNonExtraCreditPoints()} + {$gradeable->getTotalTANonExtraCreditPoints()}) + "&emsp;&emsp;&emsp;" + " AUTO-GRADING: " + {$gradeable->getGradedAutograderPoints()} + "/" + {$gradeable->getTotalAutograderNonExtraCreditPoints()});
    }
    function openFile(html_file, url_file) {
        var directory = "";
        if (url_file.includes("submissions")) {
            directory = "submissions";
        }
        else if (url_file.includes("results")) {
            directory = "results";
        }
        else if (url_file.includes("checkout")) {
            directory = "checkout";
        }
        window.open("{$this->core->getConfig()->getSiteUrl()}&component=misc&page=display_file&dir=" + directory + "&file=" + html_file + "&path=" + url_file + "&ta_grading=true","_blank","toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600");
        return false;
    }
</script>
<script type="text/javascript">
        function adjustSize(name) {
          var textarea = document.getElementById(name);
          textarea.style.height = "";
          textarea.style.height = Math.min(textarea.scrollHeight, 300) + "px";
        };
</script>
HTML;
        return $return;
    }

    public function popupStudents() {
        $return = <<<HTML
<div class="popup-form" id="student-marklist-popup" style="display: none; width: 500px; margin-left: -250px;">
    <div style="width: auto; height: 450px; overflow-y: auto;" id="student-marklist-popup-content">
        <h3>Students who received
            <br><br>
            <span id="student-marklist-popup-question-name">Name:</span>
            <br>
            <em id="student-marklist-popup-mark-note">"Title"</em>
        </h3>
        <br>
        # of students with mark: <span id="student-marklist-popup-student-amount">0</span>
        <br>
        # of graded components: <span id="student-marklist-popup-graded-components">0</span>
        <br>
        # of total components: <span id="student-marklist-popup-total-components">0</span>
        <br>
        <span id="student-marklist-popup-student-names">
            <br>Name1
        </span>
    </div>
    <div style="float: right; width: auto">
        <a onclick="$('#student-marklist-popup').css('display', 'none');" class="btn btn-danger">Cancel</a>
    </div>
</div>
</div>
HTML;
        return $return;
    }

    public function popupNewMark() {
        $return = <<<HTML
<div class="popup-form" id="mark-creation-popup" style="display: none; width: 500px; margin-left: -250px;">
    <div style="width: auto; height: 450px; overflow-y: auto;" id="mark-creation-popup-content">
        Amount of points:<br>
        <input id="mark-creation-popup-points" type="number" value="0"><br>
        <br>
        Note:
        <input id="mark-creation-popup-note" type="text" oninput="$('#mark-creation-popup-error').css('display', 'none');" value="">
        <br>
        <span id="mark-creation-popup-error" class="alert-error" style="display: none;">
            Note cannot be blank!
        </span>
    </div>
    <div style="float: right; width: auto">
        <a id="mark-creation-popup-cancel" onclick="$('#mark-creation-popup').css('display', 'none');" class="btn btn-danger">Cancel</a>
    </div>
    <div style="float: right; width: auto">
        <a id="mark-creation-popup-confirm" class="btn btn-primary">Create</a>
    </div>
</div>
</div>
HTML;
        return $return;
    }

}

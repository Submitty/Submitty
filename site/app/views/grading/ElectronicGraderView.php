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
        $total_submissions,
        $registered_but_not_rotating,
        $rotating_but_not_registered,
        $viewed_grade,
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
        $team_total=0;
        foreach ($sections as $key => $section) {
            if ($key === "NULL") {
                continue;
            }
            $graded += $section['graded_components'];
            $total += $section['total_components'];
            if ($gradeable->isTeamAssignment()) {
               $no_team_total += $section['no_team'];
               $team_total += $section['team'];
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
            if ($gradeable->isTeamAssignment()) {
                $total_students = $team_total + $no_team_total;
            } else {
                $total_students = $total_submissions;
            }
            $change_value = $gradeable->getNumTAComponents();
            $show_total = $total/$change_value;
            $show_graded = round($graded/$change_value, 2);
            if($peer) {
                $change_value = $gradeable->getNumPeerComponents() * $gradeable->getPeerGradeSet();
                $show_graded = $graded/$change_value;
                $show_total = $total/$change_value;
            }
            $submitted_percentage = 0;
            if($total_submissions!=0){
                $submitted_percentage = round(($show_total / $total_submissions) * 100, 1);
            }
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
            if($gradeable->isTeamAssignment()){
            $team_percentage = round(($team_total/$total_students) * 100, 1);
            $return .= <<<HTML
            Students on a team: {$team_total}/{$total_students} ({$team_percentage}%)
            <br />
            <br />
            Number of teams: {$total_submissions}
            <br />
            <br />
            Teams who have submitted: {$show_total} / {$total_submissions} ({$submitted_percentage}%)
HTML;
            }
            else{
            $return .= <<<HTML
            Students who have submitted: {$show_total} / {$total_submissions} ({$submitted_percentage}%)
            <br />
            <br />
            Current percentage of grading done: {$show_graded}/{$show_total} ({$percentage}%)
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
HTML;
                if ($gradeable->taGradesReleased()) {
                    $show_total = $total/$change_value;
                    $viewed_percent = number_format(($viewed_grade / max($show_total, 1)) * 100, 1);
                    if ($gradeable->isTeamAssignment()) {
                        $return .= <<<HTML
            <br />
            Number of teams who have viewed their grade: {$viewed_grade} / {$show_total} ({$viewed_percent}%)
HTML;
                    } else {
                        $return .= <<<HTML
            <br />
            Number of students who have viewed their grade: {$viewed_grade} / {$show_total} ({$viewed_percent}%)
HTML;
                    }
                }
                $return .= <<<HTML
        </div>
HTML;
            }
            if(!$peer) {
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
                //This else encompasses the above calculations for Teamss
                //END OF ELSE
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
            href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId()))}">
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
    public function detailsPage($gradeable, $rows, $graders, $all_teams, $empty_teams) {
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
HTML;
    if ($gradeable->isTeamAssignment()) {

        if(count($all_teams) > count($empty_teams)) {
            $return .= <<<HTML
            <a style="float: right;" class="btn btn-primary" href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'export_teams', 'gradeable_id'=>$gradeable->getId()))}">Export Teams Members</a>
HTML;
        }
        if(count($all_teams) == count($empty_teams)) {
            $return .= <<<HTML
           <button style="float: right;" class="btn btn-primary" onclick="importTeamForm();">Import Teams Members</button>
HTML;
        }
    }        
        $return .= <<<HTML
    <br /><br /><br /><table class="table table-striped table-bordered persist-area">
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
                            $reg_section = ($row->getUser()->getRegistrationSection() === null) ? "NULL": $row->getUser()->getRegistrationSection();
                            $rot_section = ($row->getUser()->getRotatingSection() === null) ? "NULL": $row->getUser()->getRegistrationSection();
                            $return .= <<<HTML
                <td><a onclick='adminTeamForm(true, "{$row->getUser()->getId()}", "{$reg_section}", "{$rot_section}", [], [], {$gradeable->getMaxTeamSize()});'>
                    <i class="fa fa-pencil" aria-hidden="true"></i></a></td>
                <td></td>
HTML;
                        }
                        else {
                            $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable->getId(), $row->getTeam()->getId(), "user_assignment_settings.json");
                            $user_assignment_setting = FileUtils::readJsonFile($settings_file);
                            $user_assignment_setting_json = json_encode($user_assignment_setting);
                            $members = json_encode($row->getTeam()->getMembers());
                            $reg_section = ($row->getTeam()->getRegistrationSection() === null) ? "NULL": $row->getTeam()->getRegistrationSection();
                            $rot_section = ($row->getTeam()->getRotatingSection() === null) ? "NULL": $row->getTeam()->getRotatingSection();
                            $return .= <<<HTML
                <td><a onclick='adminTeamForm(false, "{$row->getTeam()->getId()}", "{$reg_section}", "{$rot_section}", {$user_assignment_setting_json}, {$members}, {$gradeable->getMaxTeamSize()});'>
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
                    <a class="btn {$btn_class}" href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'who_id'=>$row->getUser()->getId()))}">
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
                if($row->getTaGradesReleased()){
                    $return .= <<<HTML
                    <td title="{$grade_viewed}" style="{$grade_viewed_color}">{$viewed_grade}</td>
HTML;
                } else {
                    $return .= <<<HTML
                    <td title="{$grade_viewed}" style="{$grade_viewed_color}"></td>
HTML;
                }
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
                $reg_section = ($team->getRegistrationSection() === null) ? "NULL": $team->getRegistrationSection();
                $rot_section = ($team->getRotatingSection() === null) ? "NULL": $team->getRotatingSection();
                $return .= <<<HTML
            <tr id="{empty-team-row-{$team->getId()}}" {$style}>
                <td>{$count}</td>
                <td>{$display_section}</td>
                <td><a onclick='adminTeamForm(false, "{$team->getId()}", "{$reg_section}", "{$rot_section}", {$user_assignment_setting_json}, [], {$gradeable->getMaxTeamSize()});'>
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

    public function adminTeamForm($gradeable, $all_reg_sections, $all_rot_sections) {
        $students = $this->core->getQueries()->getAllUsers();
        $student_full = array();
        foreach ($students as $student) {
            $student_full[] = array('value' => $student->getId(),
                                    'label' => str_replace("'","&#039;",$student->getDisplayedFirstName()).' '.str_replace("'","&#039;",$student->getLastName()).' <'.$student->getId().'>');
        }
        $student_full = json_encode($student_full);

        return $this->core->getOutput()->renderTwigTemplate("grading/AdminTeamForm.twig", [
            "gradeable" => $gradeable,
            "student_full" => $student_full,
            "view" => isset($_REQUEST["view"]) ? $_REQUEST["view"] : null,
            "all_reg_sections" => $all_reg_sections,
            "all_rot_sections" => $all_rot_sections,
        ]);
    }

    public function importTeamForm($gradeable) {
        return $this->core->getOutput()->renderTwigTemplate("grading/ImportTeamForm.twig", [
            "gradeable" => $gradeable
        ]);
    }


    //The student not in section variable indicates that an full access grader is viewing a student that is not in their
    //assigned section. canViewWholeGradeable determines whether hidden testcases can be viewed.
    public function hwGradingPage(Gradeable $gradeable, float $progress, string $prev_id, string $next_id, $studentNotInSection=false, $canViewWholeGradeable=false) {
        $peer = false;
        if($this->core->getUser()->getGroup()==4 && $gradeable->getPeerGrading()) {
            $peer = true;
        }
        $return = $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderNavigationBar', $gradeable, $progress, $prev_id, $next_id, $studentNotInSection, $peer);
        $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderAutogradingPanel', $gradeable, $canViewWholeGradeable);
        $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderSubmissionPanel', $gradeable);

        $user = $gradeable->getUser();
        if(!$peer) {
            $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderInformationPanel', $gradeable, $user);
        }
        if($gradeable->useTAGrading()) {
            $return .= $this->core->getOutput()->renderTemplate(array('grading', 'ElectronicGrader'), 'renderRubricPanel', $gradeable, $user);
        }

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

    private function makeTable($user_id, $gradeable, &$status){
        $return = <<<HTML
        <h3>Overall Late Day Usage for {$user_id}</h3><br/>
        <table>
            <thead>
                <tr>
                    <th></th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Allowed per term</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Allowed per assignment</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Submitted days after deadline</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Extensions</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Status</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Late Days Charged</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Total Late Days Used</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Remaining Days</th>
                </tr>
            </thead>
            <tbody>
HTML;
        $total_late_used = 0;
        $status = "Good";
        $order_by = [ 
            'CASE WHEN eg.eg_submission_due_date IS NOT NULL THEN eg.eg_submission_due_date ELSE g.g_grade_released_date END' 
        ];
        foreach ($this->core->getQueries()->getGradeablesIterator(null, $user_id, 'registration_section', 'u.user_id', 0, $order_by) as $g) { 
            $g->calculateLateDays($total_late_used);
            $class = "";
            if($g->getId() == $gradeable->getId()){
                $class = "class='yellow-background'";
                $status = $g->getLateStatus();
            }
            if(!$g->hasSubmitted()){
                $status = "No submission";
            }
            $remaining = max(0, $g->getStudentAllowedLateDays() - $total_late_used);
            $return .= <<<HTML
                <tr>
                    <th $class style="padding:5px; border:thin solid black">{$g->getName()}</th>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getStudentAllowedLateDays()}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getAllowedLateDays()}</td> 
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getLateDays()}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getLateDayExceptions()}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$status}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getCurrLateCharged()}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$total_late_used}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$remaining}</td>
                </tr>
HTML;
        }
        $return .= <<<HTML
            </tbody>
        </table>
HTML;
        return $return;
    }

    /**
     * @param Gradeable $gradeable
     * @param float $progress
     * @param string $prev_id
     * @param string $next_id
     * @param bool $studentNotInSection
     * @param bool $peer
     * @return string
     */
    public function renderNavigationBar(Gradeable $gradeable, float $progress, string $prev_id, string $next_id, bool $studentNotInSection, bool $peer) {
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/NavigationBar.twig", [
            "studentNotInSection" => $studentNotInSection,
            "prev_id" => $prev_id,
            "next_id" => $next_id,
            "progress" => $progress,
            "gradeable" => $gradeable,
            "peer" => $peer
        ]);
    }

    /**
     * Render the Auto-Grading Testcases panel
     * @param Gradeable $gradeable
     * @param bool $canViewWholeGradeable
     * @return string
     */
    public function renderAutogradingPanel(Gradeable $gradeable, bool $canViewWholeGradeable) {
        $return = <<<HTML
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
HTML;
        return $return;
    }

    /**
     * Render the Submissions and Results Browser panel
     * @param Gradeable $gradeable
     * @return string
     */
    public function renderSubmissionPanel(Gradeable $gradeable) {
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
        $submissions = array();
        $results = array();
        $checkout = array();

        // NOTE TO FUTURE DEVS: There is code around line 830 (ctrl-f openAll) which depends on these names,
        // if you change here, then change there as well
        // order of these statements matter I believe

        add_files($submissions, array_merge($gradeable->getMetaFiles(), $gradeable->getSubmittedFiles()), 'submissions');

        $vcsFiles = $gradeable->getVcsFiles();
        if( count( $vcsFiles ) != 0 ) { //if there are checkout files, then display folder, otherwise don't
            add_files($checkout, $vcsFiles, 'checkout');
        }

        add_files($results, $gradeable->getResultsFiles(), 'results');

        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/SubmissionPanel.twig", [
            "gradeable" => $gradeable,
            "submissions" => $submissions,
            "checkout" => $checkout,
            "results" => $results
        ]);
    }

    /**
     * @param Gradeable $gradeable
     * @param User $user
     * @return string
     */
    public function renderInformationPanel(Gradeable $gradeable, User $user) {
        $return = <<<HTML

<div id="student_info" class="draggable rubric_panel" style="right:15px; bottom:40px; width:48%; height:30%;">
    <div class="draggable_content">
    <span class="grading_label">Student Information</span>
    <div class="inner-container">
        <h5 class='label' style="float:right; padding-right:15px;">Browse Student Submissions:</h5>
        <div class="rubric-title">
HTML;
        $who = $gradeable->getUser()->getId();
        $onChange = "versionChange('{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'action' => 'grade', 'gradeable_id' => $gradeable->getId(), 'who_id'=>$who, 'gradeable_version' => ""))}', this)";
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
            } else {
                $version = $gradeable->getCurrentVersionNumber();
                $button = '<input type="submit" class="btn btn-default btn-xs" style="float:right; margin: 0 10px;" value="Grade This Version">';
            }
            $return .= <<<HTML
                <br/><br/>
                <form style="display: inline;" method="post" onsubmit='return checkTaVersionChange();'
                        action="{$this->core->buildUrl(array('component' => 'student',
                'action' => 'update',
                'gradeable_id' => $gradeable->getId(),
                'new_version' => $version, 'ta' => true, 'who' => $who))}">
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
        } else {
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
                <input type="hidden" name="graded_version" value="{$gradeable->getActiveVersion()}" />
HTML;

        //Late day calculation
        $status = "Good";
        $color = "green";
        if ($gradeable->isTeamAssignment() && $gradeable->getTeam() !== null) {
            foreach ($gradeable->getTeam()->getMembers() as $team_member) {
                $team_member = $this->core->getQueries()->getUserById($team_member);
                $return .= $this->makeTable($team_member->getId(), $gradeable, $status);
            }

        } else {
            $return .= $this->makeTable($user->getId(), $gradeable, $status);
            if ($status != "Good" && $status != "Late" && $status != "No submission") {
                $color = "red";
                $my_color = "'#F62817'"; // fire engine red
                $my_message = "Late Submission";
                $return .= <<<HTML
                <script>
                    $('body').css('background', $my_color);
                    $('#bar_wrapper').append("<div id='bar_banner' class='banner'>$my_message</div>");
                    $('#bar_banner').css('background-color', $my_color);
                    $('#bar_banner').css('color', 'black');
                </script>
                <b>Status:</b> <span style="color:{$color};">{$status}</span><br />
HTML;
            }
        }


        $return .= <<<HTML
        </div>
    </div>
    </div>
</div>
HTML;
        return $return;
    }

    /**
     * Render the Grading Rubric panel
     * @param Gradeable $gradeable
     * @param User $user
     * @return string
     */
    public function renderRubricPanel(Gradeable $gradeable, User $user) {
        $return = "";

        $display_verify_all = false;
        //check if verify all button should be shown or not
        foreach ($gradeable->getComponents() as $component) {
            if (!$component->getGrader()) {
                continue;
            }
            if ($component->getGrader()->getId() !== $this->core->getUser()->getId() && $this->core->getUser()->accessFullGrading()) {
                $display_verify_all = true;
                break;
            }
        }
        $disabled = '';
        //TODO: Move this to somewhere more central
        if ($gradeable->getActiveVersion() == 0) {
            $disabled = 'disabled';
            $my_color = "'#FF8040'"; // mango orange
            $my_message = "Cancelled Submission";
            if ($gradeable->hasSubmitted()) {
                $return .= <<<HTML
                <script>
                    $('body').css('background', $my_color);
                    $('#bar_wrapper').append("<div id='bar_banner' class='banner'>$my_message</div>");
                    $('#bar_banner').css('background-color', $my_color);
                    $('#bar_banner').css('color', 'black');
                </script>
HTML;
            } else {
                $my_color = "'#C38189'";  // lipstick pink (purple)
                $my_message = "No Submission";
                $return .= <<<HTML
                <script>
                    $('body').css('background', $my_color);
                    $('#bar_wrapper').append("<div id='bar_banner' class='banner'>$my_message</div>");
                    $('#bar_banner').css('background-color', $my_color);
                    $('#bar_banner').css('color', 'black');
                </script>
HTML;
            }
        } else if ($gradeable->getCurrentVersionNumber() != $gradeable->getActiveVersion()) {
            $disabled = 'disabled';
        }
        // if use student components, get the values for pages from the student's submissions
        $files = $gradeable->getSubmittedFiles();
        $student_pages = array();
        foreach ($files as $filename => $content) {
            if ($filename == "student_pages.json") {
                $path = $content["path"];
                $student_pages = FileUtils::readJsonFile($content["path"]);
            }
        }

        $grading_data = [
            "gradeable" => $gradeable->getGradedData(),
            "your_user_id" => $this->core->getUser()->getId(),
            "disabled" => $disabled === "disabled",
            "can_verify" => $display_verify_all // If any can be then this is set
        ];

        foreach ($grading_data["gradeable"]["components"] as &$component) {
            $page = intval($component["page"]);
            // if the page is determined by the student json
            if ($page == -1) {
                // usually the order matches the json
                if ($student_pages[intval($component["order"])]["order"] == intval($component["order"])) {
                    $page = intval($student_pages[intval($component["order"])]["page #"]);
                } // otherwise, iterate through until the order matches
                else {
                    foreach ($student_pages as $student_page) {
                        if ($student_page["order"] == intval($component["order"])) {
                            $page = intval($student_page["page #"]);
                            $component["page"] = $page;
                            break;
                        }
                    }
                }
            }
        }
        //References need to be cleaned up
        unset($component);

        $grading_data = json_encode($grading_data, JSON_PRETTY_PRINT);

        $this->core->getOutput()->addInternalJs('ta-grading.js');
        $this->core->getOutput()->addInternalJs('ta-grading-mark.js');
        $this->core->getOutput()->addInternalJs('twig.min.js');
        $this->core->getOutput()->addInternalJs('gradeable.js');

        $return .= $this->core->getOutput()->renderTwigTemplate("grading/electronic/RubricPanel.twig", [
            "gradeable" => $gradeable,
            "display_verify_all" => $display_verify_all,
            "user" => $user,
            "grading_data" => $grading_data
        ]);
        return $return;
    }
}

<?php

namespace app\views\grading;

use app\models\Gradeable;
use app\models\User;
use app\views\AbstractView;

class SimpleGraderView extends AbstractView {

    /**
     * @param Gradeable $gradeable
     * @param Gradeable[] $rows
     * @param array       $graders
     *
     * @return string
     */
    public function simpleDisplay($gradeable, $rows, $graders, $section_type) {

        $g_id = $gradeable->getId();
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $action = ($gradeable->getType() === 1) ? 'lab' : 'numeric';
        $return = <<<HTML
<div class="content">
    <div style="float: right; margin-bottom: 10px; margin-left: 20px">
HTML;

        // Default is viewing your sections sorted by id
        // Limited grader does not have "View All"
        // If nothing to grade, Instuctor will see all sections
        if(!isset($_GET['sort'])){
            $sort = 'id';
        }
        else{
            $sort = $_GET['sort'];
        }
        if (!isset($_GET['view']) || $_GET['view'] !== 'all') {
            $text = 'View All';
            $view = 'all';
        }
        else{
            $text = 'View Your Sections';
            $view = null;
        }
        if($gradeable->isGradeByRegistration()){
            $grading_count = count($this->core->getUser()->getGradingRegistrationSections());
        }
        else{
            $grading_count = count($this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable->getId(),$this->core->getUser()->getId()));
        }

        if($this->core->getUser()->accessFullGrading() && (!$this->core->getUser()->accessAdmin() || $grading_count !== 0)){
            $return .= <<<HTML
        <a class="btn btn-default"
            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => $action, 'g_id' => $gradeable->getId(), 'sort' => $sort, 'view' => $view))}">$text</a>
HTML;
        }

        $return .= <<<HTML
    </div>
HTML;


        if(isset($_GET['view']) && $_GET['view'] == 'all'){
            $view = 'all';
        }
        else{
            $view = null;
        }

        if($action == 'lab'){
            $info = "No Color - No Credit<br />
                    Dark Blue - Full Credit<br />
                    Light Blue - Half Credit<br />
                    Red - [SAVE ERROR] Refresh Page";
        }
        else{
            $info = "Red - [SAVE ERROR] Refresh Page";
        }

        if($gradeable->getTaInstructions() != "") {
            $ta_instruct = "Overall TA Instructions: " . $gradeable->getTaInstructions();
        }
        else {
            $ta_instruct = "";
        }
        
        $return .= <<<HTML
    <h2>{$gradeable->getName()}</h2><p>{$ta_instruct}</p><br>
    <p style="float: left;">$info</p>
    <a class="btn btn-primary" id="simple-stats-btn" style="float: right;" onclick='showSimpleGraderStats("{$action}")'>View Statistics</a>
HTML;
        // Get all the names/ids from all the students
        $student_full = array();
        foreach($rows as $gradeable_row) {
            $student_full[] = array('value' => $gradeable_row->getUser()->getId(),
                                    'label' => $gradeable_row->getUser()->getDisplayedFirstName().' '.$gradeable_row->getUser()->getLastName().' <'.$gradeable_row->getUser()->getId().'>');
        }
        $student_full = json_encode($student_full);
        // render using twig
        $return .= $this->core->getOutput()->renderTwigTemplate("grading/StudentSearch.twig", [
            "student_full" => $student_full
        ]);
        
        if($action === 'numeric') {
            if ($this->core->getUser()->accessAdmin()) {
                $return .= <<<HTML
    <br> <br> <br>
    <p style="float: right;">The CSV file should be formated as such: <br />
    user id,first name,last name,grade1,grade2,...,total points earned,text1,text2,...</p>
    <input class ="csvButtonUpload" type="file" id="csvUpload" style="float: right" accept=".csv, .txt">
    <label for="csvUpload" style="float: right; padding-right: 10px;">Upload CSV</label> 
HTML;
            }
        }
        $return .= <<<HTML
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="1%"></td>
                <td width="3%">Section</td>
                <td width="68" style="text-align: left"><a href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => $action, 'g_id' => $gradeable->getId(), 'sort' => 'id', 'view' => $view))}"><span class="tooltiptext" title="sort by ID" aria-hidden="true">User ID </span><i class="fa fa-sort"></i></a></td>
                <td width="92" style="text-align: left"> <a href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => $action, 'g_id' => $gradeable->getId(), 'sort' => 'first', 'view' => $view))}"><span class="tooltiptext" title="sort by First Name" aria-hidden="true">First Name </span><i class="fa fa-sort"></i></a></td>
                <td width="91" style="text-align: left"> <a href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => $action, 'g_id' => $gradeable->getId(), 'sort' => 'last', 'view' => $view))}"><span class="tooltiptext" title="sort by Last Name" aria-hidden="true">Last Name </span><i class="fa fa-sort"></i></a></td>
HTML;
        $num_text = 0;
        $num_numeric = count($gradeable->getComponents());
        $comp_ids = array();
        if($action == 'lab'){
            foreach ($gradeable->getComponents() as $component) {
                $return .= <<<HTML
                <td width="100">{$component->getTitle()}</td>
HTML;
            }
        }
        else{
            $num_numeric = 0;
            foreach ($gradeable->getComponents() as $component) {
                if($component->getIsText()){
                    $num_text++;
                }
                else{
                    $num_numeric++;
                }
            }
            if($num_numeric !== 0){
                foreach ($gradeable->getComponents() as $component) {
                    if(!$component->getIsText()){
                        $return .= <<<HTML
                <td width="35" style="text-align: center">{$component->getTitle()}({$component->getMaxValue()})</td>
HTML;
                        $comp_ids[] = $component->getId();
                    }
                }
                $return .= <<<HTML
                <td width="25" style="text-align: center">Total</td>
HTML;
            }
            foreach ($gradeable->getComponents() as $component) {
                if($component->getIsText()){
                    $return .= <<<HTML
                <td style="text-align: center">{$component->getTitle()}</td>
HTML;
                }
            }
        }

        $return .= <<<HTML
            </tr>
        </thead>
        <tbody>
HTML;

        $count = 1;
        $row = 0;
        $last_section = false;
        $tbody_open = false;
        $colspan = 5 + count($gradeable->getComponents());
        $num_users = 0;

        if($action == 'numeric'){
            $colspan++;
        }
        if(count($rows) == 0){
            $return .= <<<HTML
            <tr class="info">
                <td colspan="{$colspan}" style="text-align: center">No Grading To Be Done! :)</td>
            </tr>
HTML;
        }
        // Iterate through every row
        foreach ($rows as $gradeable_row) {
            if ($gradeable->isGradeByRegistration()) {
                $section = $gradeable_row->getUser()->getRegistrationSection();
            }
            else {
                $section = $gradeable_row->getUser()->getRotatingSection();
            }
            $display_section = ($section === null) ? "NULL" : $section;
            if ($section !== $last_section) {
                $last_section = $section;
                $count = 1;
                if ($tbody_open) {
                    $return .= <<<HTML
        </tbody>
HTML;
                }
                if (isset($graders[$display_section]) && count($graders[$display_section]) > 0) {
                    $section_graders = implode(", ", array_map(function(User $user) { return $user->getId(); }, $graders[$display_section]));
                }
                else {
                    $section_graders = "Nobody";
                }
                $return .= <<<HTML
            <tr class="info persist-header">
                <td colspan="{$colspan}" style="text-align: center">
                Students Enrolled in Section {$display_section}
HTML;
                if($action == 'lab'){
                    $return .= <<<HTML
                    <a target=_blank href="{$this->core->buildUrl(array(
                                                                  'component' => 'grading',
                                                                  'page' => 'simple',
                                                                  'action' => 'print_lab', 
                                                                  'sort' => $sort,
                                                                  'section' => $section,
                                                                  'sectionType' => $section_type,
                                                                  'g_id' => $g_id))}">
                      <i class="fa fa-print"></i>
                    </a>
HTML;
                }
                $component_ids = json_encode($comp_ids);
                $return .= <<<HTML
                </td>
            </tr>
            <tr class="info">
                <td colspan="{$colspan}" style="text-align: center">Graders: {$section_graders}</td>
            </tr>
        <tbody id="section-{$section}" data-numnumeric="{$num_numeric}" data-numtext="{$num_text}" data-compids = "{$component_ids}">
HTML;
            }
            $style = "";
            if ($gradeable_row->getUser()->accessGrading()) {
                $style = "style='background: #7bd0f7;'";
            }
            $return .= <<<HTML
            <tr data-gradeable="{$gradeable->getId()}" data-user="{$gradeable_row->getUser()->getId()}" data-row="{$row}" {$style}> 
                <td class="">{$count}</td>
                <td class="">{$gradeable_row->getUser()->getRegistrationSection()}</td>
                <td class="cell-all" style="text-align: left">{$gradeable_row->getUser()->getId()}</td>
                <td class="" style="text-align: left">{$gradeable_row->getUser()->getDisplayedFirstName()}</td>
                <td class="" style="text-align: left">{$gradeable_row->getUser()->getLastName()}</td>
HTML;
            if($action == 'lab'){
                $col = 0;
                foreach ($gradeable_row->getComponents() as $component) {
                    $grader = ($component->getGrader() !== null) ? "data-grader='{$component->getGrader()->getId()}'" : '';
                    $time = ($component->getGradeTime() !== null) ? "data-grade-time='{$component->getGradeTime()->format('Y-m-d H:i:s')}'" : '';
                    if ($component->getIsText()) {
                        $return .= <<<HTML
                <td>{$component->getComment()}</td>
HTML;
                    }
                    else {
                        if($component->getScore() === 1.0) {
                            $background_color = "background-color: #149bdf";
                        }
                        else if($component->getScore() === 0.5) {
                            $background_color = "background-color: #88d0f4";
                        }
                        else {
                            $background_color = "";
                        }


                        $return .= <<<HTML
                <td class="cell-grade" id="cell-{$row}-{$col}" data-id="{$component->getId()}" data-score="{$component->getScore()}" {$grader} {$time} style="{$background_color}"></td>
HTML;
                    }
                    $gradeable_row++;
                    $col++;
                }
            }
            else{
                $col = 0;
                $total = 0;
                if($num_numeric !== 0){
                    foreach ($gradeable_row->getComponents() as $component) {
                        $grader = ($component->getGrader() !== null) ? "data-grader='{$component->getGrader()->getId()}'" : '';
                        $time = ($component->getGradeTime() !== null) ? "data-grade-time='{$component->getGradeTime()->format('Y-m-d H:i:s')}'" : '';
                        if (!$component->getIsText()) {
                            $total+=$component->getScore();
                            if ($component->getScore() == 0){
                                $return .= <<<HTML
                <td class="option-small-input"><input class="option-small-box" style="text-align: center; color: #bbbbbb;" type="text" id="cell-{$row}-{$col}" value="{$component->getScore()}" data-id="{$component->getId()}" {$grader} {$time} data-num="true"/></td>
HTML;
                            }
                            else {
                                $return .= <<<HTML
                <td class="option-small-input"><input class="option-small-box" style="text-align: center" type="text" id="cell-{$row}-{$col}" value="{$component->getScore()}" data-id="{$component->getId()}" {$grader} {$time} data-num="true"/></td>
HTML;
                            }
                            $gradeable_row++;
                            $col++;
                        }
                    }
                    $return .= <<<HTML
                <td class="option-small-output"><input class="option-small-box" style="text-align: center" type="text" border="none" id="total-{$row}" value=$total data-total="true" readonly></td>
HTML;

                }

                foreach ($gradeable_row->getComponents() as $component) {
                    if ($component->getIsText()) {
                        $return .= <<<HTML
                <td class="option-small-input"><input class="option-small-box" type="text" id="cell-{$row}-{$col}" value="{$component->getComment()}" data-id="{$component->getId()}"/></td>
HTML;
                        $gradeable_row++;
                        $col++;
                    }
                }
            }

            if($gradeable_row->getUser()->getRegistrationSection() != "") {
                $num_users++;
            }

            $return .= <<<HTML
            </tr>
HTML;
            $row++;
            $count++;
        }
        
        $return .= <<<HTML
        </tbody></table></div>
HTML;

        $return .= <<<HTML
        <div class="popup-form" id="simple-stats-popup">
HTML;
        
        if($num_users > 0) {

            $return .= <<<HTML
            
                <table class="table table-striped table-bordered persist-area">
                    <thead class="persist-thead">
                        <tr>
                            <td width="33%">Component</td>
                            <td width="33%">Average</td>
                            <td width="33%">Std. Deviation</td>
                        </tr>
                    </thead>

HTML;
            $i = 0;
            foreach($gradeable->getComponents() as $component) {
                if(!$component->getIsText()) {
                    $return .= <<<HTML
                    <tbody><tr>
                        <td>{$component->getTitle()}</td>
                        <td id="avg-{$i}"></td>
                        <td id="stddev-{$i}"></td>
                    </tr></tbody>
HTML;
                    $i++;
                }
            }

            $return .= <<<HTML
            <tbody><tr>
                <td>Total</td>
                <td id="avg-t"></td>
                <td id="stddev-t"></td>
            </tr></tbody>
            </table>
            <p></br></p>
            <p id="num-graded"></p>
HTML;
        }
        else {
            $return .= <<<HTML
            <p style="text-align: center">No Statistics To View.</p>
HTML;

        }

        return $return;
    }

  public function displayPrintLab($gradeable, $sort_by, $section, $students){
        // exit(1);
        $g_id = $gradeable->getId();
        $section_type = ($gradeable->isGradeByRegistration() ? "Registration": "Rotating");

        $return = <<<HTML
        Name: ________________________________________
        <span style="float:right;">
        Date: ____________________
        </span>
        <br />
        {$gradeable->getName()}</b>
        <span style="float:right;">
        Section: <b>{$section}</b>
        </span>
        <br />
        <br />
        <table border="1" width="100%">
            <tr>
                <td style="width: 10%">User Id</td>
                <td style="width: 20%">First Name</td>
                <td style="width: 20%">Last Name</td>
HTML;

        //Get the names of all of the checkpoints
        $checkpoints = array();
        foreach($gradeable->getComponents() as $row){
          array_push($checkpoints, $row->getTitle());
        }

        $width = (50/count($checkpoints));
        for($i = 0; $i < count($checkpoints); $i++) {
            $return .= <<<HTML
                <td style="width: {$width}%">{$checkpoints[$i]}</td>
HTML;
        }

        $return .= <<<HTML
            </tr>
HTML;
        $j = 0;
        foreach($students as $student) {
            $color = ($j % 2 == 0) ? "white" : "lightgrey";
            $return .= <<<HTML
            <tr style="background-color: {$color}">
                <td>
                    {$student->getId()}
                </td>
HTML;
            $return .= <<<HTML
                <td>
                    {$student->getDisplayedFirstName()}
                </td>
HTML;
            $return .= <<<HTML
                <td>
                    {$student->getLastName()}
                </td>
HTML;
            for($i = 0; $i < count($checkpoints); $i++) {
                $return .= <<<HTML
                <td></td>
HTML;
            }
            $return .= <<<HTML
            </tr>
HTML;
            $j++;
        }

        $return .= <<<HTML
        </table>
HTML;
    return $return;

    }
}

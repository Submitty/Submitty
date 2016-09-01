<?php

namespace app\views;

use app\libraries\Core;
use \app\libraries\GradeableType;
use app\models\Gradeable;

class NavigationView {
    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }
    
    public function showGradeables($sections_to_list) {
        $ta_base_url = $this->core->getConfig()->getTABaseUrl();
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $site_url = $this->core->getConfig()->getSiteUrl();
        
        $return = <<<HTML
<div class="content">
    <div class="nav-buttons">
HTML;
        if ($this->core->getUser()->accessAdmin()) {
            $return .= <<<HTML
        <button class="btn btn-primary" onclick="window.location.href='{$ta_base_url}/account/admin-gradeable.php?course={$course}&semester={$semester}&this=New%20Gradeable'">New Gradeable</button>
        <button class="btn btn-primary" onclick="batchImportJSON('{$ta_base_url}/account/submit/admin-gradeable.php?course={$course}&semester={$semester}&action=import', '{$this->core->getCsrfToken()}');">Import From JSON</button>
HTML;
        }
        $return .= <<<HTML
        <!--<button class="btn btn-primary">View Grades</button>-->
    </div>
    <table class="gradeable_list" style="width:100%;">

HTML;
        $title_to_button_type_submission = array("FUTURE" => "btn-default", "OPEN" => "btn-primary" , "CLOSED" => "btn-danger",
                                                 "ITEMS BEING GRADED" => "btn-default", "GRADED" => 'btn-success');
        $title_to_button_type_grading = array("FUTURE" => "btn-default", "OPEN" => "btn-default" , "CLOSED" => "btn-default",
                                                 "ITEMS BEING GRADED" => "btn-primary", "GRADED" => 'btn-danger');
        $title_to_prefix = array("FUTURE" => "OPEN DATE", "OPEN" => "SUBMIT", "CLOSED" => "CLOSED", "ITEMS BEING GRADED" => "GRADING", "GRADED" => "GRADED");
        foreach ($sections_to_list as $title => $gradeable_list) {
            if ($title == "FUTURE" && !$this->core->getUser()->accessAdmin()) {
                continue;
            }
            
            if (count($gradeable_list) == 0) {
                continue;
            }
            
            $return .= <<<HTML
        <tr class="bar"><td colspan="4"></td></tr>
        <tr class="colspan"><td colspan="4">{$title}</td></tr>
HTML;
            foreach ($gradeable_list as $gradeable => $g_data) {
                /** @var Gradeable $g_data */
                $time = ($title=="GRADED") ? "": " @ H:i";
                $gradeable_grade_range = ($title=='GRADED' || $title=='ITEMS BEING GRADED') ?
                                         'GRADING (due '.$g_data->getGradeStartDate()->format("m/d/y{$time}").')' : 'GRADING (open '.$g_data->getGradeReleasedDate()->format("m/d/y{$time}").")";
                if(trim($g_data->getInstructionsURL())!=''){
                    $gradeable_title = '<label>'.$g_data->getName().'</label><a class="external" href="'.$g_data->getInstructionsURL().'" target="_blank"><i style="margin-left: 10px;" class="fa fa-external-link"></i></a>';
                }
                else{
                    $gradeable_title = '<label>'.$g_data->getName().'</label>';
                }

                if ($g_data->getType() == GradeableType::ELECTRONIC_FILE){

                    $display_date = ($title=="FUTURE") ? $g_data->getOpenDate()->format("m/d/y{$time}") : "(due ".$g_data->getDueDate()->format("m/d/y{$time}").")";
                    $button_text = "{$title_to_prefix[$title]} {$display_date}";
                    if ($g_data->hasConfig()) {
                        $gradeable_open_range = <<<HTML
             <button class="btn {$title_to_button_type_submission[$title]}" style="width:100%;" onclick="location.href='{$site_url}&component=student&gradeable_id={$gradeable}';">
                 {$button_text}
             </button>
HTML;
                        if ($g_data->useTAGrading()) {
                            $gradeable_grade_range = <<<HTML
            <button class="btn {$title_to_button_type_grading[$title]}" style="width:100%;" \\
            onclick="location.href='{$ta_base_url}/account/index.php?course={$course}&semester={$semester}&g_id={$gradeable}'">
            {$gradeable_grade_range}</button>
HTML;
                        }
                        else {
                            $gradeable_grade_range = "";
                        }
                    }
                    else {
                        $gradeable_open_range = <<<HTML
             <button class="btn {$title_to_button_type_submission[$title]}" style="width:100%;" disabled>
                 Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh
             </button>
HTML;
                        $gradeable_grade_range = <<<HTML
            <button class="btn {$title_to_button_type_grading[$title]}" style="width:100%;" disabled>
                Need to run BUILD_{$this->core->getConfig()->getCourse()}.sh
            </button>
HTML;
                    }
                }
                else{
                    $gradeable_open_range = '';
                    if($g_data->getType() == GradeableType::CHECKPOINTS){
                       $gradeable_grade_range = <<<HTML
            <button class="btn {$title_to_button_type_grading[$title]}" style="width:100%;" \\
            onclick="location.href='{$ta_base_url}/account/account-checkpoints-gradeable.php?course={$course}&semester={$semester}&g_id={$gradeable}'">
            {$gradeable_grade_range}</button>
HTML;
                    }
                    elseif($g_data->getType() == GradeableType::NUMERIC_TEXT){
                        $gradeable_grade_range = <<<HTML
            <button class="btn {$title_to_button_type_grading[$title]}" style="width:100%;" \\
            onclick="location.href='{$ta_base_url}/account/account-numerictext-gradeable.php?course={$course}&semester={$semester}&g_id={$gradeable}'">
            {$gradeable_grade_range}</button>
HTML;
                    }
                }
    
                if ($this->core->getUser()->accessAdmin()) {
                    $admin_button = <<<HTML
            <button class="btn btn-default" style="width:100%;" \\
            onclick="location.href='{$ta_base_url}/account/admin-gradeable.php?course={$course}&semester={$semester}&action=edit&id={$gradeable}&this=Edit%20Gradeable'">
                Edit
            </button>
HTML;
                }
                else {
                    $admin_button = "";
                }
                
                if (!$this->core->getUser()->accessGrading()) {
                    $gradeable_grade_range = "";
                    
                }
                
                $return.= <<<HTML
        <tr id="gradeable_row">
            <td>{$gradeable_title}</td>
            <td style="padding: 10px;">{$gradeable_open_range}</td>
            <td style="padding: 10px;">{$gradeable_grade_range}</td>
            <td>{$admin_button}</td>
        </tr>
HTML;
            }
            $return .= '<tr class="colspan"><td colspan="4" style="border-bottom:2px black solid;"></td></tr>';
        }
        $return .= <<<HTML
                            </table>
                        </div>
HTML;
        return $return;
    }
}
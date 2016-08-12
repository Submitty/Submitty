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
        $return = '<div class="content"><table class="gradeable_list" style="width:100%;">';
        $ta_base_url = $this->core->getConfig()->getTABaseUrl();
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $site_url = $this->core->getConfig()->getSiteUrl();
        $return.= <<<HTML
HTML;
        $title_to_button_type = array("FUTURE" => "btn-default", "OPEN" => "btn-primary" , "CLOSED" => "btn-danger", "ITEMS BEING GRADED" => "btn-default", "GRADED" => 'btn-success');
        $title_to_prefix = array("FUTURE" => "OPEN DATE", "OPEN" => "SUBMIT", "CLOSED" => "CLOSED", "ITEMS BEING GRADED" => "GRADING", "GRADED" => "GRADED");
        foreach($sections_to_list as $title => $gradeable_list){
            $return .= <<<HTML
                                    <tr class="bar"><td colspan="4"></td></tr>
                                    <tr class="colspan"><td colspan="4" style="border-bottom:2px black solid;">{$title}</td></tr>
HTML;
            foreach($gradeable_list as $gradeable => $g_data){
                $gradeable_grade_range = ($title=='GRADED' || $title=='ITEMS BEING GRADED') ? 
                                         'GRADES (open '.$g_data->getGradeStartDate()->format("m-d H:i ").')' : 'GRADES (due '.$g_data->getGradeReleasedDate()->format("m-d H:i ").")";
                
                if ($g_data->getType() == GradeableType::ELECTRONIC_FILE){
                    if(trim($g_data->getInstructionsURL())!=''){
                        $gradeable_title = '<a href="'.$g_data->getInstructionsURL().'" target="_blank"><label class="has-url">'.$g_data->getName().'</label></a>';
                    }
                    else{
                        $gradeable_title = $g_data->getName();
                    }
                    $display_date = ($title=="FUTURE") ? $g_data->getOpenDate()->format("m-d H:i ") : "(due ".$g_data->getDueDate()->format("m-d H:i").")";  
                    $gradeable_open_range = <<<HTML
                                         <button class="btn {$title_to_button_type[$title]}" style="width:100%;" onclick="location.href='{$site_url}&component=student&gradeable_id={$gradeable}';">
                                             {$title_to_prefix[$title]} {$display_date}
                                         </button>
HTML;
                    $gradeable_grade_range = <<<HTML
                                            <button class="btn {$title_to_button_type[$title]}" style="width:100%;" \\
                                            onclick="location.href='{$ta_base_url}/account/index.php?course={$course}&semester={$semester}&g_id={$gradeable}'">
                                            {$gradeable_grade_range}</button>
HTML;
                }
                else{
                    $gradeable_title = '<label>'.$g_data->getName().'</label>';
                    $gradeable_open_range = '';
                    if($g_data->getType() == GradeableType::CHECKPOINTS){
                       $gradeable_grade_range = <<<HTML
                                            <button class="btn {$title_to_button_type[$title]}" style="width:100%;" \\
                                            onclick="location.href='{$ta_base_url}/account/account-checkpoints-gradeable.php?course={$course}&semester={$semester}&g_id={$gradeable}'">
                                            {$gradeable_grade_range}</button>
HTML;
                    }
                    elseif($g_data->getType() == GradeableType::NUMERIC){
                        $gradeable_grade_range = <<<HTML
                                            <button class="btn {$title_to_button_type[$title]}" style="width:100%;" \\
                                            onclick="location.href='{$ta_base_url}/account/account-numerictext-gradeable.php?course={$course}&semester={$semester}&g_id={$gradeable}'">
                                            {$gradeable_grade_range}</button>
HTML;
                    }
                }
                
                $return.= <<<HTML
                                    <tr id="gradeable_row">
                                        <td>{$gradeable_title}</td>
                                        <td style="padding: 10px;">{$gradeable_open_range}</td>
                                        <td style="padding: 10px;">{$gradeable_grade_range}</td>
                                        <td><button class="btn btn-default" style="width:100%;" \\
                                        onclick="location.href='{$ta_base_url}/account/admin-gradeable.php?course={$course}&semester={$semester}&action=edit&id={$gradeable}&this=Edit%20Gradeable'">
                                        Edit</button></td>
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
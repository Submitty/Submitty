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
        $return = '<div class="content"><table class="gradeable_list">';
        $ta_base_url = $this->core->getConfig()->getTABaseUrl();
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $site_url = $this->core->getConfig()->getSiteUrl();
        
        foreach($sections_to_list as $title => $gradeable_list){
            $return .= <<<HTML
                                    <tr class="bar"><td colspan="4"></td></tr>
                                    <tr class="colspan"><td colspan="4" style="border-bottom:2px black solid;">{$title}</td></tr>
HTML;
            foreach($gradeable_list as $gradeable => $g_data){
                $gradeable_grade_range = $g_data->getGradeStartDate()->format("M d H:i ").' - '.$g_data->getGradeReleasedDate()->format("M d H:i ");
                
                if ($g_data->getType() == GradeableType::ELECTRONIC_FILE){
                    if(trim($g_data->getInstructionsURL())!=''){
                        $gradeable_title = '<a href="'.$g_data->getInstructionsURL().'" target="_blank"><label class="has-url">'.$gradeable.'</label></a>';
                    }
                    $gradeable_open_range = <<<HTML
                                         <button class="btn btn-primary" style="width:70%;" onclick="location.href='{$site_url}&component=student&gradeable_id={$gradeable}';">
                                             {$g_data->getOpenDate()->format("M d H:i ")}-{$g_data->getDueDate()->format("M d H:i ")}
                                         </button>
HTML;
                    $gradeable_grade_range = <<<HTML
                                            <button class="btn btn-primary" style="width:70%;" \\
                                            onclick="location.href='{$ta_base_url}/account/index.php?course={$course}&semester={$semester}&g_id={$gradeable}'">
                                            {$gradeable_grade_range}</button>
HTML;
                }
                else{
                    $gradeable_title = '<label>'.$gradeable.'</label>';
                    $gradeable_open_range = '';
                    if($g_data->getType() == GradeableType::CHECKPOINTS){
                       $gradeable_grade_range = <<<HTML
                                            <button class="btn btn-primary" style="width:70%;" \\
                                            onclick="location.href='{$ta_base_url}/account/account-checkpoints-gradeable.php?course={$course}&semester={$semester}&g_id={$gradeable}'">
                                            {$gradeable_grade_range}</button>
HTML;
                    }
                    elseif($g_data->getType() == GradeableType::NUMERIC){
                        $gradeable_grade_range = <<<HTML
                                            <button class="btn btn-primary" style="width:70%;" \\
                                            onclick="location.href='{$ta_base_url}/account/account-numerictext-gradeable.php?course={$course}&semester={$semester}&g_id={$gradeable}'">
                                            {$gradeable_grade_range}</button>
HTML;
                    }
                }
                
                $return.= <<<HTML
                                    <tr>
                                        <td>{$gradeable_title}</td>
                                        <td>{$gradeable_open_range}</td>
                                        <td>{$gradeable_grade_range}</td>
                                        <td><button class="btn btn-primary" style="width:100%;" \\
                                        onclick="location.href='{$ta_base_url}/account/admin-gradeable.php?course={$course}&semester={$semester}&action=edit&id={$gradeable}'">
                                        Edit</button></td>
                                    </tr>
HTML;
            }
        }
        $return .= <<<HTML
                            </table>
                        </div>
HTML;
        return $return;
    }
    
}
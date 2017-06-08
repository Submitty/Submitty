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
    public function checkpointForm($gradeable, $rows, $graders) {
        $return = <<<HTML
<div class="content">
HTML;
        $return .= <<<HTML
    <div style="float: right; margin-bottom: 10px; margin-left: 20px">
HTML;

        $button_url_params = array('component' => 'grading', 'page' => 'simple', 'action' => 'lab', 'g_id' => $gradeable->getId());
        if (isset($_GET['view'])) {
            $button_url_params['view'] = $_GET['view'];
        }
        if (isset($_GET['time'])) {
            $button_url_params['time'] = $_GET['time'];
        }
        if (isset($_GET['grader'])) {
            $button_url_params['grader'] = $_GET['grader'];
        }
        
        $previous = 'on';
        if (!isset($_GET['time']) || $_GET['time'] !== 'on') {
            $previous = 'off';
            $button_url_params['time'] = 'on';
            $return .= <<<HTML
        <a class="btn btn-default" href="{$this->core->buildUrl($button_url_params)}">
        View Grade Times
        </a>
HTML;
        }
        else {
            $previous = 'on';
            $button_url_params['time'] = 'off';
            $return .= <<<HTML
        <a class="btn btn-default" href="{$this->core->buildUrl($button_url_params)}">
        Hide Grade Times
        </a>
HTML;
        }
        $button_url_params['time'] = $previous;
        if (!isset($_GET['grader']) || $_GET['grader'] !== 'on') {
            $previous = 'off';
            $button_url_params['grader'] = 'on';
            $return .= <<<HTML
        <a class="btn btn-default" href="{$this->core->buildUrl($button_url_params)}">
        View Graders
        </a>
HTML;
        }
        else {
            $previous = 'on';
            $button_url_params['grader'] = 'off';
            $return .= <<<HTML
        <a class="btn btn-default" href="{$this->core->buildUrl($button_url_params)}">
        Hide Graders
        </a>
HTML;
        }
        $button_url_params['grader'] = $previous;
        $return .= <<<HTML
    </div>
HTML;
        
        if (!$this->core->getUser()->accessAdmin()) {
            $return .= <<<HTML
    <div style="float: right; margin-bottom: 10px; margin-left: 20px">
HTML;
            if (!isset($_GET['view']) || $_GET['view'] !== 'all') {
                $previous = 'section';
                $button_url_params['view'] = 'all';
                $return .= <<<HTML
        <a class="btn btn-default"
            href="{$this->core->buildUrl($button_url_params)}">
            View All    
        </a>
HTML;
            }
            else {
                $previous = 'all';
                $button_url_params['view'] = 'section';
                $return .= <<<HTML
        <a class="btn btn-default"
            href="{$this->core->buildUrl($button_url_params)}">
            View Your Sections    
        </a>
HTML;
            }
            $button_url_params['view'] = $previous;
            $return .= <<<HTML
    </div>
HTML;
        }
        $return .= <<<HTML
    <i class="fa fa-question-circle tooltip" style="float: right" aria-hidden="true">
        <span class="tooltiptext">
No Color - No Credit<br />
Dark Blue - Full Credit<br />
Light Blue - Half Credit<br />
Red - [SAVE ERROR] Refresh Page
        </span>
    </i>
    <h2>Overview of {$gradeable->getName()}</h2>
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="3%"></td>
                <td width="5%">Section</td>
                <td width="10%" style="text-align: left">User ID</td>
                <td width="20%" colspan="2">Name</td>
HTML;
        foreach ($gradeable->getComponents() as $component) {
            $return .= <<<HTML
                <td>{$component->getTitle()}</td>
HTML;
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
                <a target=_blank href="{$this->core->getConfig()->getTABaseUrl()}/account/print/print_checkpoints_gradeable.php?course={$this->core->getConfig()->getCourse()}&semester={$this->core->getConfig()->getSemester()}&g_id={$gradeable->getId()}&section_id={$display_section}&grade_by_reg_section={$gradeable->isGradeByRegistration()}">
                    <i class="fa fa-print"></i>
                </a>
            </td>
        </tr>
        <tr class="info">
            <td colspan="{$colspan}" style="text-align: center">Graders: {$section_graders}</td>
        </tr>
        <tbody id="section-{$section}">
HTML;
            }

            $return .= <<<HTML
            <tr data-gradeable="{$gradeable->getId()}" data-user="{$gradeable_row->getUser()->getId()}">
                <td class="">{$count}</td>
                <td class="">{$gradeable_row->getUser()->getRegistrationSection()}</td>
                <td class="cell-all" style="text-align: left">{$gradeable_row->getUser()->getId()}</td>
                <td class="" style="text-align: left">{$gradeable_row->getUser()->getDisplayedFirstName()}</td>
                <td class="" style="text-align: left">{$gradeable_row->getUser()->getLastName()}</td>
HTML;

            $col = 0;
            foreach ($gradeable_row->getComponents() as $component) {
                if ($component->isText()) {
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
                    if($component->hasGrade()) {
                        $specific_grade_time = (($_GET['time']== 'on' && $component->getGradeTime() != '"1900-01-01 00:00:00"') ? $component->getGradeTime() : "");
                        $specific_grader_id = (($_GET['grader']=='on') ? $component->getGrader() : "").(($specific_grade_time != "" && $_GET['grader'] == 'on') ? ";" : "");
                        $specific_grader_id = trim($specific_grader_id, '"');
                        $return .= <<<HTML
                    <td class="cell-grade" id="cell-{$row}-{$col}" data-id="{$component->getId()}" data-score="{$component->getScore()}" style="{$background_color}">
                    {$specific_grader_id} {$specific_grade_time}</td>
HTML;
                    }
                    else {
                        $return .= <<<HTML
                    <td class="cell-grade" id="cell-{$row}-{$col}" data-id="{$component->getId()}" data-score="{$component->getScore()}" style="{$background_color}"></td>
HTML;
                    }
                }
                $gradeable_row++;
                $col++;
            }
            $return .= <<<HTML
            </tr>
HTML;
            $row++;
            $count++;
        }

        $return .= <<<HTML
        </tbody>
    </table>
</div>
HTML;


        return $return;
    }
}

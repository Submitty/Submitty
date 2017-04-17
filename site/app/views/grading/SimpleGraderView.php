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
        if (!$this->core->getUser()->accessAdmin()) {
            $return .= <<<HTML
    <div style="float: right; margin-bottom: 10px; margin-left: 20px">
HTML;
            if (!isset($_GET['view']) || $_GET['view'] !== 'all') {
                $return .= <<<HTML
        <a class="btn btn-default"
            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => 'lab', 'g_id' => $gradeable->getId(), 'view' => 'all'))}">
            View All    
        </a>
HTML;
            }
            else {
                $return .= <<<HTML
        <a class="btn btn-default"
            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'simple', 'action' => 'lab', 'g_id' => $gradeable->getId()))}">
            View Your Sections    
        </a>
HTML;
            }
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
            <td colspan="{$colspan}" style="text-align: center">Students Enrolled in Section {$display_section}</td>
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
                    $return .= <<<HTML
               <td class="cell-grade" id="cell-{$row}-{$col}" data-id="{$component->getId()}" data-score="{$component->getScore()}" style="{$background_color}"></td>
HTML;
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

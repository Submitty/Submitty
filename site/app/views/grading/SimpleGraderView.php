<?php

namespace app\views\grading;

use app\models\Gradeable;
use app\models\User;
use app\views\AbstractView;

class SimpleGraderView extends AbstractView {

    /**
     * @param Gradeable $gradeable
     * @param Gradeable[] $rows
     *
     * @return string
     */
    public function checkpointForm($gradeable, $rows) {
        $return = <<<HTML
<div class="content">
    <h2>Overview of {$gradeable->getName()}</h2>
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="3%"></td>
                <td width="5%">Section</td>
                <td width="20%" style="text-align: left">User ID</td>
                <td width="30%" colspan="2">Name</td>
                <td>Question 1</td>
                <td>Question 2</td>
                <td>Text 1</td>
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
        $last_section = false;
        $tbody_open = false;
        foreach ($rows as $row) {
            if ($gradeable->isGradeByRegistration()) {
                $section = $row->getUser()->getRegistrationSection();
            }
            else {
                $section = $row->getUser()->getRotatingSection();
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
            <td colspan="8" style="text-align: center">Students Enrolled in Section {$display_section}</td>
        </tr>
        <!--<tr class="info">
            <td colspan="8" style="text-align: center">Graders: {$section_graders}</td>
        </tr>-->
        <tbody id="section-{$section}">
HTML;
            }
            $return .= <<<HTML
            <tr>
                <td>{$count}</td>
                <td>{$row->getUser()->getRegistrationSection()}</td>
                <td style="text-align: left">{$row->getUser()->getId()}</td>
                <td style="text-align: left">{$row->getUser()->getDisplayedFirstName()}</td>
                <td style="text-align: left">{$row->getUser()->getLastName()}</td>
HTML;
            foreach ($row->getComponents() as $component) {
                if ($component->isText()) {
                    $return .= <<<HTML
                <td>{$component->getComment()}</td>
HTML;
                }
                else {
                    $return .= <<<HTML
                <td>{$component->getScore()}</td>
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
    </table>
</div>
HTML;


        return $return;
    }
}
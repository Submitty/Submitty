<?php

namespace app\views\grading;

use app\models\Gradeable;
use app\views\AbstractView;

class ElectronicGraderView extends AbstractView {
    /**
     * @param Gradeable $gradeable
     * @param array     $sections
     * @return string
     */
    public function overviewPage($gradeable, $sections) {
        $course = $this->core->getConfig()->getCourse();
        $semester = $this->core->getConfig()->getSemester();
        $graded = 0;
        $total = 0;
        foreach ($sections as $key => $section) {
            if ($key === "NULL") {
                continue;
            }
            $graded += $section['graded_students'];
            $total += $section['total_students'];
        }
        $percentage = round(($graded / $total) * 100);
        $return = <<<HTML
<div class="content">
    <h2>Overview of {$gradeable->getName()}</h2>
    <div class="sub">
        Current percentage of grading done: {$percentage}% ({$graded}/{$total})
        <br />
        <br />
        By Grading Sections:
        <div style="margin-left: 20px">
HTML;
        foreach ($sections as $key => $section) {
            $percentage = round(($section['graded_students'] / $section['total_students']) * 100);
            $return .= <<<HTML
            Section {$key}: {$percentage}% ({$section['graded_students']} / {$section['total_students']})<br />
HTML;
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
            if (count($section['graders']) > 0) {
                $graders = implode(", ", array_map(function($grader) { $grader->getId(); }, $section['graders']));
            }
            else {
                $graders = "Nobody";
            }
            $return .= <<<HTML
            Section {$key}: {$graders}<br />
HTML;
        }
        $return .= <<<HTML
        </div>
        <div style="margin-top: 20px">
            <a class="btn btn-primary" 
                href="{$this->core->buildUrl(array('component'    => 'grading',
                                                   'page'         => 'electronic',
                                                   'action'       => 'summary',
                                                   'gradeable_id' => $gradeable->getId()))}">
                Grading Homework Overview
            </a>
            <a class="btn btn-primary"
                href="{$this->core->getConfig()->getTABaseUrl()}account/index.php?course={$course}&semester={$semester}&g_id={$gradeable->getId()}">
                Grade Next Student
            </a>
        </div>
    </div>
</div>
HTML;
        return $return;
    }

    /**
     * @param Gradeable   $gradeable
     * @param Gradeable[] $rows
     * @param string      $section_key
     * @return string
     */
    public function summaryPage($gradeable, $rows, $section_key="registration_section") {
        $return = <<<HTML
<div class="content">
    <h2>Summary Page for {$gradeable->getName()}</h2>
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="3%"></td>
                <td width="5%">Section</td>
                <td width="20%" style="text-align: left">User ID</td>
                <td width="30%" colspan="2">Name</td>
                <td width="14%">Autograding</td>
                <td width="14%">TA Grading</td>
                <td width="14%">Total</td>
            </tr>
        </thead>
HTML;
        if (count($rows) === 0) {
            $return .= <<<HTML
        <tbody>
            <tr>
                <td colspan="8">No students found for grading</td>
            </tr>
        </tbody>
HTML;
        }
        else {
            $return .= <<<HTML
HTML;
            $count = 1;
            $last_section = false;
            $tbody_open = false;
            foreach ($rows as $row) {
                $total_possible = $row->getTotalAutograderNonExtraCreditPoints() + $row->getTotalTANonExtraCreditPoints();
                $graded = $row->getGradedAutograderPoints() + $row->getGradedTAPoints();
                if ($section_key === "rotating_section") {
                    $section = $row->getUser()->getRotatingSection();
                }
                else {
                    $section = $row->getUser()->getRegistrationSection();
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
                    $return .= <<<HTML
        <tr class="info persist-header">
            <td colspan="8" style="text-align: center">Students Enrolled in Section {$display_section}</td>
        </tr>
        <tbody id="section-{$section}">
HTML;
                }
                $return .= <<<HTML
            <tr id="user-row-{$row->getUser()->getId()}">
                <td>{$count}</td>
                <td>{$display_section}</td>
                <td>{$row->getUser()->getId()}</td>
                <td>{$row->getUser()->getDisplayedFirstName()}</td>
                <td>{$row->getUser()->getLastName()}</td>
                <td>{$row->getGradedAutograderPoints()} / {$row->getTotalAutograderNonExtraCreditPoints()}</td>
                <td>
HTML;
                if ($row->beenTAgraded()) {
                    $btn_class = "btn-default";
                    $contents = "{$row->getGradedTAPoints()} / {$row->getTotalTANonExtraCreditPoints()}";
                }
                else {
                    $btn_class = "btn-primary";
                    $contents = "Grade";
                }
                $return .= <<<HTML
                    <a class="btn {$btn_class}" href="{$this->core->getConfig()->getTABaseUrl()}account/index.php?g_id={$gradeable->getId()}&amp;individual={$row->getUser()->getId()}&amp;course={$this->core->getConfig()->getCourse()}&amp;semester={$this->core->getConfig()->getSemester()}">
                        {$contents}
                    </a>
                </td>
                <td>{$graded} / {$total_possible}</td>
            </tr>
HTML;
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
}
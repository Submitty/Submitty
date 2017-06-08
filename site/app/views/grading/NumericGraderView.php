<?php

namespace app\views\grading;

use app\models\Gradeable;
use app\models\User;
use app\views\AbstractView;

class NumericGraderView extends AbstractView {

    /**
     * @param Gradeable $gradeable
     * @param Gradeable[] $rows
     * @param array       $graders
     *
     * @return string
     */
    public function NumericText($gradeable, $rows, $graders) {
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
            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'numeric', 'action' => 'data_entry', 'g_id' => $gradeable->getId(), 'sort' => $sort, 'view' => $view))}">
                $text
        </a>
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
        $return .= <<<HTML

    <h2>{$gradeable->getName()}</h2>
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="1%"></td>
                <td width="3%">Section</td>
                <td width="10%" style="text-align: left"><a href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'numeric', 'action' => 'data_entry', 'g_id' => $gradeable->getId(), 'sort' => 'id', 'view' => $view))}"><span class="tooltiptext" title="sort by ID" aria-hidden="true">User ID </span><i class="fa fa-sort"></i></a></td>
                <td width="10%" style="text-align: left"> <a href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'numeric', 'action' => 'data_entry', 'g_id' => $gradeable->getId(), 'sort' => 'first', 'view' => $view))}"><span class="tooltiptext" title="sort by First Name" aria-hidden="true">First Name </span><i class="fa fa-sort"></i></a></td>
                <td width="10%" style="text-align: left"> <a colspan="1" href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'numeric', 'action' => 'data_entry', 'g_id' => $gradeable->getId(), 'sort' => 'last', 'view' => $view))}"><span class="tooltiptext" title="sort by Last Name" aria-hidden="true">Last Name </span><i class="fa fa-sort"></i></a></td>
HTML;
        foreach ($gradeable->getComponents() as $component) {
/////////////////////////////////////////////HARD CODED COME BACK AND FIX ///////////////////////////////////////////////////////////////////////////////////
            $return .= <<<HTML
                <td width="10%" style="text-align: center">{$component->getTitle()}</td>
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
        if(count($rows) == 0){
            $return .= <<<HTML
            <tr class="info">
                <td colspan="{$colspan}" style="text-align: center">No Grading To Be Done! :)</td>
            </tr>
HTML;
        }
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
                <a target=_blank href="{$this->core->getConfig()->getTABaseUrl()}/account/print/print_checkpoints_gradeable.php?course={$this->core->getConfig()->getCourse()}&semester={$this->core->getConfig()->getSemester()}&g_id={$gradeable->getId()}&section_id={$display_section}&grade_by_reg_section={$gradeable->isGradeByRegistration()}&sort_by={$sort}">
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
                <td class="option-small-input" id="cell-{$row}-{$col}" data-id="{$component->getId()}" data-value="{$component->getComment()}"><input type="text" value="{$component->getComment()}"/></td>
HTML;
                }
                else {
                    $return .= <<<HTML
                <td class="option-small-input" id="cell-{$row}-{$col}" data-id="{$component->getId()}" data-value="{$component->getScore()}"><input type="text" value="{$component->getScore()}"/></td>
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

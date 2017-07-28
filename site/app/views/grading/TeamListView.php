<?php

namespace app\views\grading;

use app\views\AbstractView;

class TeamListView extends AbstractView {

    /**
    * Show table of all team ids and members, for instructors only
    * @param Gradeable $gradeable
    * @param Team[] $teams
    * @return string
    */
    public function showTeamList($gradeable, $teams) {
        $return = <<<HTML
<div class="content">
    <h2>Teams Overview for {$gradeable->getName()}</h2>
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="5%"></td>
                <td width="25%" style="text-align: left">Team ID</td>
                <td width="65%" style="text-align: left">Team Members</td>
                <td width="5%"></td>
            </tr>
        </thead>
HTML;
        if (count($teams) === 0) {
            $return .= <<<HTML
        <tr>
            <td colspan="4">No teams have been created</td>
        </tr>
HTML;
        }
        else {
            foreach($teams as $team) {
                $members = $team->getMembers();
                $members = implode(", ", $members);
                $return .= <<<HTML
        <tr>
            <td width="5%"></td>
            <td width="25%" style="text-align: left">{$team->getId()}</td>
            <td width="65%" style="text-align: left">{$members}</td>
            <td width="5%"></td>
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

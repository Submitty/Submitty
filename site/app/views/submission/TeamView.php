<?php

namespace app\views\submission;

use app\libraries\FileUtils;
use app\views\AbstractView;

class TeamView extends AbstractView {

    /**
    * Show team management page
    * @param \app\models\Gradeable $gradeable
    * @param \app\models\Team[] $teams
    * @return string
    */
    public function showTeamPage($gradeable, $teams, $lock, $users_seeking_team) {
        $site_url = $this->core->getConfig()->getSiteUrl();
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $user_id = $this->core->getUser()->getId();
        $members = [];
        $seekers = [];
        $invites_received = [];
        $seeking_partner = false;

        $team = $gradeable->getTeam();
        $repo = "";

        //Top content box, has team
        if ($team !== null) {

            //List team members
            foreach ($team->getMembers() as $teammate) {
                $members[] = $this->core->getQueries()->getUserById($teammate);
            }

            if ($gradeable->getIsRepository()) {
                if (strpos($gradeable->getSubdirectory(), '://') !== false || substr($gradeable->getSubdirectory(), 0, 1) === '/') {
                    $vcs_path = $gradeable->getSubdirectory();
                }
                else {
                    if (strpos($this->core->getConfig()->getVcsBaseUrl(), '://')) {
                        $vcs_path = rtrim($this->core->getConfig()->getVcsBaseUrl(), '/') . '/' . $gradeable->getSubdirectory();
                    }
                    else {
                        $vcs_path = FileUtils::joinPaths($this->core->getConfig()->getVcsBaseUrl(), $gradeable->getSubdirectory());
                    }
                }
                $repo = $vcs_path;

                $repo = str_replace('{$gradeable_id}', $gradeable->getId(), $repo);
                $repo = str_replace('{$user_id}', $this->core->getUser()->getId(), $repo);
                $repo = str_replace(FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), 'vcs'),
                    $this->core->getConfig()->getVcsUrl(), $repo);
                $repo = str_replace('{$team_id}', $team->getId(), $repo);
            }
        }

        //Bottom content box, no team
        if ($team === null) {
            foreach($teams as $t) {
                if ($t->sentInvite($user_id)) {
                    $invites_received[] = $t;
                }
            }

            $seeking_partner = in_array($user_id, $users_seeking_team);
        }

        foreach ($users_seeking_team as $user_seeking_team) {
            $seekers[] = $this->core->getQueries()->getUserById($user_seeking_team);
        }

        $students = $this->core->getQueries()->getAllUsers();
        $student_full = array();
        foreach ($students as $student) {
            $student_full[] = array('value' => $student->getId(),
                                    'label' => $student->getDisplayedFirstName().' '.$student->getLastName().' <'.$student->getId().'>');
        }
        $student_full = json_encode($student_full);

        return $this->core->getOutput()->renderTwigTemplate("submission/Team.twig", [
            "gradeable" => $gradeable,
            "team" => $team,
            "lock" => $lock,
            "members" => $members,
            "seekers" => $seekers,
            "repo" => $repo,
            "invites_received" => $invites_received,
            "seeking_partner" => $seeking_partner,
            "student_full" => $student_full
        ]);
    }
}

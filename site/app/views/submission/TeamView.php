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

        if ($team !== null) {
            //List team members
            foreach ($team->getMembers() as $teammate) {
                $members[] = $this->core->getQueries()->getUserById($teammate);
            }
        } else {
            //Invites
            foreach($teams as $t) {
                if ($t->sentInvite($user_id)) {
                    $invites_received[] = $t;
                }
            }

            //Are you seeking a team?
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
            "invites_received" => $invites_received,
            "seeking_partner" => $seeking_partner,
            "student_full" => $student_full
        ]);
    }
}

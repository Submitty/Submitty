<?php

namespace app\views\submission;

use app\models\gradeable\Gradeable;
use app\views\AbstractView;

class TeamView extends AbstractView {

    /**
    * Show team management page
    * @param \app\models\gradeable\Gradeable $gradeable
    * @param \app\models\Team|null $team The team the user is on
    * @param \app\models\Team[] $teams
    * @return string
    */
    public function showTeamPage(Gradeable $gradeable, $team, $teams, $lock, $users_seeking_team) {
        $gradeable_id = $gradeable->getId();
        $user_id = $this->core->getUser()->getId();
        $members = [];
        $seekers = [];
        $invites_received = [];
        $seeking_partner = false;

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

        return $this->core->getOutput()->renderTwigTemplate("submission/Team.twig", [
            "gradeable" => $gradeable,
            "team" => $team,
            "user" => $this->core->getUser(),
            "lock" => $lock,
            "members" => $members,
            "seekers" => $seekers,
            "invites_received" => $invites_received,
            "seeking_partner" => $seeking_partner,
            "create_team_url" => $this->core->buildNewCourseUrl([$gradeable_id, 'team', 'new']),
            "leave_team_url" => $this->core->buildNewCourseUrl([$gradeable_id, 'team', 'leave']),
            "seek_url" => $this->core->buildNewCourseUrl([$gradeable_id, 'team', 'seek', 'new']),
            "stop_seek_url" => $this->core->buildNewCourseUrl([$gradeable_id, 'team', 'seek', 'stop']),
            "send_invitation_url" => $this->core->buildNewCourseUrl([$gradeable_id, 'team', 'invitation', 'new']),
            "accept_invitation_url" => $this->core->buildNewCourseUrl([$gradeable_id, 'team', 'invitation', 'accept']),
            "cancel_invitation_url" => $this->core->buildNewCourseUrl([$gradeable_id, 'team', 'invitation', 'cancel']),
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }
}

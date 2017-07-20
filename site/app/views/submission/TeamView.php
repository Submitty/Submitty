<?php

namespace app\views\submission;

use app\views\AbstractView;

class TeamView extends AbstractView {

    /**
    * Show team management page
    * @param Gradeable $gradeable
    * @param Team[] $teams
    * @return string
    */
    public function showTeamPage($gradeable, $teams) {
        $site_url = $this->core->getConfig()->getSiteUrl();
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $user_id = $this->core->getUser()->getId();
        
        $team = $gradeable->getTeam();
        $return = <<<HTML
<div class="content">
    <h2>Manage Team For: {$gradeable->getName()}</h2> <br />
HTML;

    //Top content box, has team
    if ($team !== null) {

        //List team members
        $return .= <<<HTML
    <h3>Your Team:</h3> <br />
HTML;
        foreach ($team->getMembers())
        }
        else {
            $teammates = implode(", ", array_diff($t->getMembers(), array($user_id)));
            $return .= <<<HTML
    <span>You are on a team with {$teammates}.</span> <br />
HTML;
        }

        //Team invitations status
        $invites_sent = $team->getInvitations();
        if(count($invites_sent) === 0) {
            $return .= <<<HTML
    <span>No invitations have been sent.</span> <br />        
HTML;
        }
        else {
            $invites_sent = implode(", ", $invites_sent);
            $return .= <<<HTML
    <span>Invitation(s) have been sent to {$invites_sent}.</span> <br />
HTML;
        }
    }

    //Top content box, no team
    else {
        $return .= <<<HTML
    <span>You are not on a team.</span>
HTML;
    }
    $return .= <<<HTML
</div>

<div class="content">
HTML;

    //Bottom content box, has team
    if ($has_team) {
        $return .= <<<HTML
    <!--<h3>Your team is full.</h3>-->
    <h3>Invite new teammates by their user ID:</h3>
    <br />
    <form action="{$site_url}" method="post">
        <input type="hidden" name="semester" value="{$semester}" />
        <input type="hidden" name="course" value="{$course}" />
        <input type="hidden" name="component" value="student" />
        <input type="hidden" name="gradeable_id" value="{$gradeable->getId()}" />
        <input type="hidden" name="page" value="team" />
        <input type="hidden" name="action" value="invitation" />
        <input type="text" name="invite_id" placeholder="User ID" />
        <input type="submit" value = "Invite" class="btn btn-primary" />
    </form>
    <br />
    <button class="btn btn-danger" onclick="location.href='{$this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId(), 'page' => 'team', 'action' => 'leave_team'))}'">Leave Team</button>
HTML;
    }

    //Bottom content box, no team
    else {

        //Invitations received
        $invites_received = array();
        foreach($teams as $t) {
            if ($t->sentInvite($user_id)) {
                $pair = array();
                $pair['team_id'] = $t->getId();
                $pair['members'] = implode(", ", $t->getMembers());
                $invites_received[] = $pair;
            }
        }

        if(count($invites_received) === 0) {
            $return .= <<<HTML
    <span>You have not received any invitations.</span> <br />
HTML;
        }
        else {
            foreach ($invites_received as $invite) {
                $return .= <<<HTML
    <form action="{$site_url}" method="post">
        <input type="hidden" name="semester" value="{$semester}" />
        <input type="hidden" name="course" value="{$course}" />
        <input type="hidden" name="component" value="student" />
        <input type="hidden" name="gradeable_id" value="{$gradeable->getId()}" />
        <input type="hidden" name="page" value="team" />
        <input type="hidden" name="action" value="accept" />
        <input type="hidden" name="team_id" value={$invite['team_id']} />
        Invitation from {$invite['members']}: <input type="submit" value = "Accept" class="btn btn-success" />
    </form>
    <br />
HTML;
            }
        }

        //Create new team button
        $return .= <<<HTML
    <br />
    <button class="btn btn-primary" onclick="location.href='{$this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId(), 'page' => 'team', 'action' => 'create_new_team'))}'">Create New Team </button>
HTML;
    }
    $return .= <<<HTML
</div>
HTML;
    return $return;
    }
}

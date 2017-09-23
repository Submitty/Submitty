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
    public function showTeamPage($gradeable, $teams, $lock) {
        $site_url = $this->core->getConfig()->getSiteUrl();
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $user_id = $this->core->getUser()->getId();
        
        $team = $gradeable->getTeam();
        $return = <<<HTML
<div class="content">
    <h2>Manage Team For: {$gradeable->getName()}</h2>
HTML;
    
    if ($lock) {
        if ($team === null) {
        $return .= <<<HTML
    <p class="red-message">
    Teams are now locked for this assignment.<br>
    You can create a new team of 1 or accept an invitation sent before teams were locked.<br>
    Contact your instructor to make further changes to your team.
    </p><br />
HTML;
        }
        else {
            $return .= <<<HTML
    <p class="red-message">
    Teams are now locked for this assignment.<br>
    Contact your instructor to make changes to your team.
    </p><br />
HTML;
        }
    }

    //Top content box, has team
    if ($team !== null) {

        //List team members
        $return .= <<<HTML
    <h3>Your Team:</h3> <br />
HTML;
        foreach ($team->getMembers() as $teammate) {
            $teammate = $this->core->getQueries()->getUserById($teammate);
            $return .= <<<HTML
        <span>&emsp;{$teammate->getFirstName()} {$teammate->getLastName()} ({$teammate->getId()}) - {$teammate->getEmail()}</span> <br />
HTML;
        }
        //Team invitations status
        if (count($team->getInvitations()) !== 0) {
            $return .= <<<HTML
    <br />
    <h3>Pending Invitations:</h3> <br />
HTML;
            foreach ($team->getInvitations() as $invited) {
                if ($lock) {
                    $return .= <<<HTML
    <span>&emsp;{$invited}</span> <br />
HTML;
                }
                else {
                    $return .= <<<HTML
    <form action="{$this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId(), 'page' => 'team', 'action' => 'cancel'))}" method="post">
        <input type="hidden" name="cancel_id" value={$invited} />
        &emsp;{$invited}: <input type="submit" value = "Cancel" class="btn btn-danger" />
    </form><br />
HTML;
                }
            }
        }
        if ($gradeable->getIsRepository()) {

	    // FIXME: We'll eventually use this course config option.
	    // Right now, we assume full path is specifified in gradeable
	    //$this->core->getConfig()->getVcsBaseUrl().$gradeable->getSubdirectory();

	    $repo = $gradeable->getSubdirectory();

	    // FIXME: Read from submitty config
	    $vcs_https_path = "https://submitty-vcs.cs.rpi.edu/git/";

	    $repo = str_replace("{\$gradeable_id}", $gradeable->getId(), $repo);
	    $repo = str_replace("/var/local/submitty/vcs/",$vcs_https_path, $repo);
            $repo = str_replace("{\$team_id}", $team->getId(), $repo);
            $return .= <<<HTML
    <br />
    <h3>To access your Team Repository:</h3>
    <span>
<em>Note: There may be a delay before your repository is prepared, please refer to assignment instructions.</em>
    <br />
    <br />

<samp>git  clone  {$repo}  SPECIFY_TARGET_DIRECTORY</samp>
<p>

    </span> <br />
HTML;
        }
    }

    //Top content box, no team
    else {
        $return .= <<<HTML
    <h4>You are not on a team.</h4> <br />
HTML;
    }
    $return .= <<<HTML
</div>
HTML;

    //Bottom content box, has team, teams not locked
    if ($team !== null && !$lock) {
        $return .= <<<HTML
<div class="content">
    <h3>Invite new teammates by their user ID:</h3>
    <br />
    <form action="{$this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId(), 'page' => 'team', 'action' => 'invitation'))}" method="post">
        <input type="text" name="invite_id" placeholder="User ID" />
        <input type="submit" value = "Invite" class="btn btn-primary" />
    </form>
    <br />
    <button class="btn btn-danger" onclick="location.href='{$this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId(), 'page' => 'team', 'action' => 'leave_team'))}'">Leave Team</button>
HTML;
    }

    //Bottom content box, no team
    else if ($team === null) {

        //Invitations received
        $invites_received = array();
        foreach($teams as $t) {
            if ($t->sentInvite($user_id)) {
                $invites_received[] = $t;
            }
        }

        if(count($invites_received) === 0) {
            $return .= <<<HTML
<div class="content">
    <h4>You have not received any invitations.</h4> <br />
HTML;
        }
        else {
            $return .= <<<HTML
<div class="content">
    <h3>Invitations:</h3> <br />
HTML;
            foreach ($invites_received as $invite) {
                $return .= <<<HTML
    <form action="{$this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId(), 'page' => 'team', 'action' => 'accept'))}" method="post">
        <input type="hidden" name="team_id" value={$invite->getId()} />
        &emsp;{$invite->getMemberList()}: <input type="submit" value = "Accept" class="btn btn-success" />
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

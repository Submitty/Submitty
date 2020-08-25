<?php

namespace app\views\submission;

use app\models\gradeable\Gradeable;
use app\views\AbstractView;

class TeamView extends AbstractView {

    /**
     * Show team management page
     * @param \app\models\gradeable\Gradeable $gradeable
     * @param \app\models\Team|null $team The team the user is on
     * @param (\app\models\User|null)[] $members
     * @param (\app\models\User|null)[] $seekers
     * @param \app\models\Team[] $invites_received
     * @param bool $seeking_partner
     * @param bool $lock
     * @return string
     */
    public function showTeamPage(Gradeable $gradeable, $team, $members, $seekers, $invites_received, bool $seeking_partner, bool $lock): string {
        $gradeable_id = $gradeable->getId();

        return $this->core->getOutput()->renderTwigTemplate("submission/Team.twig", [
            "gradeable" => $gradeable,
            "seeking_enabled" => $this->core->getConfig()->isSeekMessageEnabled(),
            "seeking_instructions" => $this->core->getConfig()->getSeekMessageInstructions(),
            "team" => $team,
            "user" => $this->core->getUser(),
            "lock" => $lock,
            "members" => $members,
            "seekers" => $seekers,
            "invites_received" => $invites_received,
            "seeking_partner" => $seeking_partner,
            "create_team_url" => $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team', 'new']),
            "leave_team_url" => $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team', 'leave']),
            "seek_url" => $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team', 'seek', 'new']),
            "stop_seek_url" => $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team', 'seek', 'stop']),
            "send_invitation_url" => $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team', 'invitation', 'new']),
            "accept_invitation_url" => $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team', 'invitation', 'accept']),
            "cancel_invitation_url" => $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team', 'invitation', 'cancel']),
            "set_message_url" => $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team', 'seek', 'message']),
            "remove_message_url" => $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team', 'seek', 'message', 'remove']),
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }
}

<?php

namespace app\views\submission;

use app\authentication\SamlAuthentication;
use app\models\gradeable\Gradeable;
use app\views\AbstractView;
use app\models\Team;
use app\libraries\FileUtils;

class TeamView extends AbstractView {
    /**
     * Show team management page
     * @param \app\models\gradeable\Gradeable $gradeable
     * @param \app\models\Team|null $team The team the user is on
     * @param (\app\models\User|null)[] $members
     * @param (\app\models\User|null)[] $seekers
     * @param (\app\models\User|null)[] $users_by_subsection
     * @param (\app\models\User|null)[] $user_team_map
     * @param \app\models\Team[] $invites_received
     * @param bool $seeking_partner
     * @param bool $lock
     * @return string
     */
    public function showTeamPage(Gradeable $gradeable, $team, $members, $seekers, $users_by_subsection, $user_team_map, $invites_received, bool $seeking_partner, bool $lock): string {
        $gradeable_id = $gradeable->getId();
        $is_instructor = $this->core->getUser()->getGroup() === 1;

        $this->core->getOutput()->addInternalModuleJs('team.js');

        $vcs_repo_exists = false;
        if ($gradeable->isVcs()) {
            $path = FileUtils::joinPaths(
                $this->core->getConfig()->getSubmittyPath(),
                'vcs',
                'git',
                $this->core->getConfig()->getTerm(),
                $this->core->getConfig()->getCourse(),
                $gradeable->getId(),
                $team?->getId()
            );
            $vcs_repo_exists = file_exists($path);
        }

        return $this->core->getOutput()->renderTwigTemplate("submission/Team.twig", [
            "gradeable" => $gradeable,
            "gradeable_id" => $gradeable->getId(),
            "seeking_enabled" => $this->core->getConfig()->isSeekMessageEnabled(),
            "seeking_instructions" => $this->core->getConfig()->getSeekMessageInstructions(),
            "team" => $team,
            "teams" => $gradeable->getTeams(),
            "team_name" => $team == null ? null : $team->getTeamName(),
            "team_name_length" => Team::MAX_TEAM_NAME_LENGTH,
            "change_team_name_url" => $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team', 'setname']),
            "user" => $this->core->getUser(),
            "user_id" => $this->core->getUser()->getId(),
            "lock" => $lock,
            "members" => $members,
            "seekers" => $seekers,
            "users_by_subsection" => $users_by_subsection,
            "user_team_map" => $user_team_map,
            "is_instructor" => $is_instructor,
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
            "csrf_token" => $this->core->getCsrfToken(),
            'git_auth_token_url' => $this->core->buildUrl(['authentication_tokens']),
            'is_vcs' => $gradeable->isVcs(),
            'vcs_repo_exists' => $vcs_repo_exists,
            'git_auth_token_required' => $this->core->getAuthentication() instanceof SamlAuthentication
        ]);
    }
}

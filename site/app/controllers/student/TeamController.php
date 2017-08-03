<?php

namespace app\controllers\student;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\FileUtils;

class TeamController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'create_new_team':
                $this->createNewTeam();
                break;
            case 'leave_team':
                $this->leaveTeam();
                break;
            case 'invitation':
                $this->sendInvitation();
                break;
            case 'accept':
                $this->acceptInvitation();
                break;
            case 'cancel':
                $this->cancelInvitation();
                break;
            case 'show_page':
            default:
                $this->showPage();
                break;
        }
    }

    public function createNewTeam() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $user_id = $this->core->getUser()->getId();
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        if ($gradeable == null) {
            $this->core->addErrorMessage("Failed to load gradeable: {$gradeable_id}");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }
        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getName()} is not a team assignment");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $return_url = $this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable_id, 'page' => 'team'));
        if ($gradeable->getTeam() !== null) {
            $this->core->addErrorMessage("You must leave your current team before you can create a new team");
            $this->core->redirect($return_url);
        }

        $this->core->getQueries()->declineAllTeamInvitations($gradeable_id, $user_id);
        $team_id = $this->core->getQueries()->createTeam($gradeable_id, $user_id, $this->core->getUser()->getRegistrationSection(), $this->core->getUser()->getRotatingSection());
        $this->core->addSuccessMessage("Created a new team");

        $gradeable_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id);
        if (!FileUtils::createDir($gradeable_path)) {
            $this->core->addErrorMEssage("Failed to make folder for this assignment");
            $this->core->redirect($return_url);
        }

        $user_path = FileUtils::joinPaths($gradeable_path, $team_id);
        if (!FileUtils::createDir($user_path)) {
            $this->core->addErrorMEssage("Failed to make folder for this assignment for the user");
            $this->core->redirect($return_url);
        }

        $current_time = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO")." ".$this->core->getConfig()->getTimezone()->getName();
        $settings_file = FileUtils::joinPaths($user_path, "user_assignment_settings.json");
        $json = array("team_history" => array(array("action" => "create", "time" => $current_time, "user" => $user_id)));

        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $this->core->addErrorMEssage("Failed to write to team history to settings file");
        }
        $this->core->redirect($return_url);
    }

    public function leaveTeam() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $user_id = $this->core->getUser()->getId();
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        if ($gradeable == null) {
            $this->core->addErrorMessage("Failed to load gradeable: {$gradeable_id}");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }
        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getName()} is not a team assignment");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $return_url = $this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable_id, 'page' => 'team'));
        $team = $gradeable->getTeam();
        if ($team === null) {
            $this->core->addErrorMessage("You are not on a team");
            $this->core->redirect($return_url);
        }

        $date = new \DateTime("now", $this->core->getConfig()->getTimezone());
        if ($date->format('Y-m-d H:i:s') > $gradeable->getTeamLockDate()->format('Y-m-d H:i:s')) {
            $this->core->addErrorMessage("Teams are now locked. Contact your instructor to change your team.");
            $this->core->redirect($return_url);
        }

        $this->core->getQueries()->leaveTeam($team->getId(), $user_id);
        $this->core->addSuccessMessage("Left team");

        $current_time = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO")." ".$this->core->getConfig()->getTimezone()->getName();
        $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id, $team->getId(), "user_assignment_settings.json");
        $json = FileUtils::readJsonFile($settings_file);
        if ($json === false) {
            $this->core->addErrorMEssage("Failed to open settings file");
            $this->core->redirect($return_url);
        }
        $json["team_history"][] = array("action" => "leave", "time" => $current_time, "user" => $user_id);

        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $this->core->addErrorMEssage("Failed to write to team history to settings file");
        }
        $this->core->redirect($return_url);
    }

    public function sendInvitation() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $user_id = $this->core->getUser()->getId();
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        if ($gradeable == null) {
            $this->core->addErrorMessage("Failed to load gradeable: {$gradeable_id}");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }
        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getName()} is not a team assignment");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $return_url = $this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable_id, 'page' => 'team'));
        $team = $gradeable->getTeam();
        if ($team === null) {
            $this->core->addErrorMessage("You are not on a team");
            $this->core->redirect($return_url);
        }

        $date = new \DateTime("now", $this->core->getConfig()->getTimezone());
        if ($date->format('Y-m-d H:i:s') > $gradeable->getTeamLockDate()->format('Y-m-d H:i:s')) {
            $this->core->addErrorMessage("Teams are now locked. Contact your instructor to change your team.");
            $this->core->redirect($return_url);
        }

        if ($team->getSize() >= $gradeable->getMaxTeamSize()) {
            $this->core->addErrorMessage("Maximum team size is {$gradeable->getMaxTeamSize()}");
            $this->core->redirect($return_url);
        }

        if (!isset($_POST['invite_id']) || ($_POST['invite_id'] === '')) {
            $this->core->addErrorMessage("No user ID specified");
            $this->core->redirect($return_url);
        }

        $invite_id = $_POST['invite_id'];
        if ($this->core->getQueries()->getUserByID($invite_id) === null) {
            $this->core->addErrorMessage("User {$invite_id} does not exist");
            $this->core->redirect($return_url);
        }

        $invite_team = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $invite_id);
        if ($invite_team !== null) {
            $this->core->addErrorMessage("Did not send invitation, {$invite_id} is already on a team");
            $this->core->redirect($return_url);
        }

        if ($team->sentInvite($invite_id)) {
            $this->core->addErrorMessage("Invitation has already been sent to {$invite_id}");
            $this->core->redirect($return_url);
        }

        $this->core->getQueries()->sendTeamInvitation($team->getId(), $invite_id);
        $this->core->addSuccessMessage("Invitation sent to {$invite_id}");

        $current_time = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO")." ".$this->core->getConfig()->getTimezone()->getName();
        $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id, $team->getId(), "user_assignment_settings.json");
        $json = FileUtils::readJsonFile($settings_file);
        if ($json === false) {
            $this->core->addErrorMEssage("Failed to open settings file");
            $this->core->redirect($return_url);
        }
        $json["team_history"][] = array("action" => "send_invitation", "time" => $current_time, "sent_by_user" => $user_id, "sent_to_user" => $invite_id);

        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $this->core->addErrorMEssage("Failed to write to team history to settings file");
        }
        $this->core->redirect($return_url);
    }

    public function acceptInvitation() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $user_id = $this->core->getUser()->getId();
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        if ($gradeable == null) {
            $this->core->addErrorMessage("Failed to load gradeable: {$gradeable_id}");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }
        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getName()} is not a team assignment");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $return_url = $this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable_id, 'page' => 'team'));
        $team = $gradeable->getTeam();
        if ($team !== null) {
            $this->core->addErrorMessage("You must leave your current team before you can accept an invitation");
            $this->core->redirect($return_url);
        }

        $accept_team_id = (isset($_REQUEST['team_id'])) ? $_REQUEST['team_id'] : null;
        $accept_team = $this->core->getQueries()->getTeamById($accept_team_id);
        if ($accept_team === null) {
            $this->core->addErrorMessage("{$accept_team_id} is not a valid team id");
            $this->core->redirect($return_url);
        }

        if (!$accept_team->sentInvite($user_id)) {
            $this->core->addErrorMessage("No invitation from {$accept_team->getMemberList()}");
            $this->core->redirect($return_url);
        }

        $this->core->getQueries()->declineAllTeamInvitations($gradeable_id, $user_id);
        $this->core->getQueries()->acceptTeamInvitation($accept_team_id, $user_id);
        $this->core->addSuccessMessage("Accepted invitation from {$accept_team->getMemberList()}");

        $current_time = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO")." ".$this->core->getConfig()->getTimezone()->getName();
        $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id, $accept_team_id, "user_assignment_settings.json");
        $json = FileUtils::readJsonFile($settings_file);
        if ($json === false) {
            $this->core->addErrorMEssage("Failed to open settings file");
            $this->core->redirect($return_url);
        }
        $json["team_history"][] = array("action" => "accept_invitation", "time" => $current_time, "user" => $user_id);

        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $this->core->addErrorMEssage("Failed to write to team history to settings file");
        }
        $this->core->redirect($return_url);
    }

    public function cancelInvitation() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $user_id = $this->core->getUser()->getId();
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        if ($gradeable == null) {
            $this->core->addErrorMessage("Failed to load gradeable: {$gradeable_id}");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }
        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getName()} is not a team assignment");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $return_url = $this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable_id, 'page' => 'team'));
        $team = $gradeable->getTeam();
        if ($team === null) {
            $this->core->addErrorMessage("You are not on a team");
            $this->core->redirect($return_url);
        }

        $date = new \DateTime("now", $this->core->getConfig()->getTimezone());
        if ($date->format('Y-m-d H:i:s') > $gradeable->getTeamLockDate()->format('Y-m-d H:i:s')) {
            $this->core->addErrorMessage("Teams are now locked. Contact your instructor to change your team.");
            $this->core->redirect($return_url);
        }

        $cancel_id = (isset($_REQUEST['cancel_id'])) ? $_REQUEST['cancel_id'] : null;
        if (!$team->sentInvite($cancel_id)) {
            $this->core->addErrorMessage("No invitation sent to {$cancel_id}");
            $this->core->redirect($return_url);
        }

        $this->core->getQueries()->cancelTeamInvitation($team->getId(), $cancel_id);
        $this->core->addSuccessMessage("Cancelled invitation to {$cancel_id}");

        $current_time = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO")." ".$this->core->getConfig()->getTimezone()->getName();
        $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id, $team->getId(), "user_assignment_settings.json");
        $json = FileUtils::readJsonFile($settings_file);
        if ($json === false) {
            $this->core->addErrorMEssage("Failed to open settings file");
            $this->core->redirect($return_url);
        }
        $json["team_history"][] = array("action" => "cancel_invitation", "time" => $current_time, "canceled_by_user" => $user_id, "canceled_user" => $cancel_id);

        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $this->core->addErrorMEssage("Failed to write to team history to settings file");
        }
        $this->core->redirect($return_url);
    }

    public function showPage() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $this->core->getUser()->getId());
        if ($gradeable == null) {
            $this->core->addErrorMessage("Failed to load gradeable: {$gradeable_id}");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }
        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getName()} is not a team assignment");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable_id);
        $date = new \DateTime("now", $this->core->getConfig()->getTimezone());
        $lock = $date->format('Y-m-d H:i:s') > $gradeable->getTeamLockDate()->format('Y-m-d H:i:s');
        $this->core->getOutput()->renderOutput(array('submission', 'Team'), 'showTeamPage', $gradeable, $teams, $lock);
    }
}

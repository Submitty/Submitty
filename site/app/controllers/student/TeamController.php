<?php

namespace app\controllers\student;

use app\controllers\AbstractController;
use app\controllers\admin\AdminGradeableController;
use app\libraries\FileUtils;
use app\libraries\response\RedirectResponse;
use app\models\User;
use app\models\Team;
use app\models\gradeable\LateDayInfo;
use Symfony\Component\Routing\Annotation\Route;

class TeamController extends AbstractController {
    /**
     * Check if a string has special characters.
     */
    public static function hasSpecialCharacters(string $str): bool {
        $pattern = '/[^a-zA-Z0-9\-_ ]/';
        return preg_match($pattern, $str) === 1;
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team/new")]
    public function createNewTeam($gradeable_id) {
        $user_id = $this->core->getUser()->getId();

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid or missing gradeable id!');
            $this->core->redirect($this->core->buildCourseUrl());
            return $this->core->getOutput()->renderJsonFail("Invalid or missing gradeable id!");
        }

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getTitle()} is not a team assignment");
            $this->core->redirect($this->core->buildCourseUrl());
            return $this->core->getOutput()->renderJsonFail($gradeable->getTitle() . " is not a team assignment");
        }

        $return_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team']);

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id, false);
        if ($graded_gradeable !== false) {
            $this->core->addErrorMessage("You must leave your current team before you can create a new team");
            $this->core->redirect($return_url);
            return $this->core->getOutput()->renderJsonFail("You must leave your current team before you can create a new team");
        }

        $this->core->getQueries()->declineAllTeamInvitations($gradeable_id, $user_id);
        $this->core->getQueries()->removeFromSeekingTeam($gradeable_id, $user_id);
        $team_id = $this->core->getQueries()->createTeam($gradeable_id, $user_id, $this->core->getUser()->getRegistrationSection(), $this->core->getUser()->getRotatingSection(), null);
        $this->core->addSuccessMessage("Created a new team");

        $gradeable_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id);
        if (!FileUtils::createDir($gradeable_path)) {
            $this->core->addErrorMEssage("Failed to make folder for this assignment");
            $this->core->redirect($return_url);
            return $this->core->getOutput()->renderJsonFail("Failed to make folder for this assignment");
        }

        $user_path = FileUtils::joinPaths($gradeable_path, $team_id);
        if (!FileUtils::createDir($user_path)) {
            $this->core->addErrorMEssage("Failed to make folder for this assignment for the team");
            $this->core->redirect($return_url);
            return $this->core->getOutput()->renderJsonFail("Failed to make folder for this assignment for the team");
        }

        $current_time = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO") . " " . $this->core->getConfig()->getTimezone()->getName();
        $settings_file = FileUtils::joinPaths($user_path, "user_assignment_settings.json");
        $json = ["team_history" => [["action" => "create", "time" => $current_time, "user" => $user_id]]];

        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $this->core->addErrorMEssage("Failed to write to team history to settings file");
            return $this->core->getOutput()->renderJsonFail("Failed to write to team history to settings file");
        }

        if ($gradeable->isVcs()) {
            $config = $this->core->getConfig();
            AdminGradeableController::enqueueGenerateRepos($config->getTerm(), $config->getCourse(), $gradeable_id, $gradeable->getVcsSubdirectory(), $config->getSubmittyPath());
        }

        $this->core->redirect($return_url);
        return $this->core->getOutput()->renderJsonSuccess();
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team/leave")]
    public function leaveTeam($gradeable_id) {
        $user_id = $this->core->getUser()->getId();

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid or missing gradeable id!');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getTitle()} is not a team assignment");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $return_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team']);

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id, false);
        if ($graded_gradeable === false) {
            $this->core->addErrorMessage("You are not on a team");
            $this->core->redirect($return_url);
        }
        $team = $graded_gradeable->getSubmitter()->getTeam();

        $date = $this->core->getDateTimeNow();
        if ($date->format('Y-m-d H:i:s') > $gradeable->getTeamLockDate()->format('Y-m-d H:i:s')) {
            $this->core->addErrorMessage("Teams are now locked. Contact your instructor to change your team.");
            $this->core->redirect($return_url);
        }

        $this->core->getQueries()->leaveTeam($team->getId(), $user_id);
        $this->core->addSuccessMessage("Left team");

        $current_time = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO") . " " . $this->core->getConfig()->getTimezone()->getName();
        $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id, $team->getId(), "user_assignment_settings.json");
        $json = FileUtils::readJsonFile($settings_file);
        if ($json === false) {
            $this->core->addErrorMessage("Failed to open settings file");
            $this->core->redirect($return_url);
        }
        $json["team_history"][] = ["action" => "leave", "time" => $current_time, "user" => $user_id];

        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $this->core->addErrorMessage("Failed to write to team history to settings file");
        }
        $this->core->redirect($return_url);
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team/invitation/new", methods: ["POST"])]
    public function sendInvitation($gradeable_id) {
        $user_id = $this->core->getUser()->getId();

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid or missing gradeable id!');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getTitle()} is not a team assignment");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $return_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team']);

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id, false);
        if ($graded_gradeable === false) {
            $this->core->addErrorMessage("You are not on a team");
            $this->core->redirect($return_url);
        }
        $team = $graded_gradeable->getSubmitter()->getTeam();

        $date = $this->core->getDateTimeNow();
        if ($date->format('Y-m-d H:i:s') > $gradeable->getTeamLockDate()->format('Y-m-d H:i:s')) {
            $this->core->addErrorMessage("Teams are now locked. Contact your instructor to change your team.");
            $this->core->redirect($return_url);
        }

        if (($team->getSize() + count($team->getInvitations())) >= $gradeable->getTeamSizeMax()) {
            $this->core->addErrorMessage("Cannot send invitation. Max team size is {$gradeable->getTeamSizeMax()}");
            $this->core->redirect($return_url);
        }

        if (!isset($_POST['invite_id']) || ($_POST['invite_id'] === '')) {
            $this->core->addErrorMessage("No user ID specified");
            $this->core->redirect($return_url);
        }

        $invite_id = self::cleanInviteId($_POST['invite_id']);
        $invited_user = $this->core->getQueries()->getUserById($invite_id);

        if ($invited_user === null) {
            // If a student with this id does not exist in the course...
            $this->core->addErrorMessage("User {$invite_id} does not exist");
            $this->core->redirect($return_url);
        }

        if ($invited_user->getRegistrationSection() === null) {
            // If a student with this id is in the null section...
            // (make this look the same as a non-existent student so as not to
            // reveal information about dropped students)
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

        // send invited user a notification
        $metadata = json_encode(
            ['url' => $this->core->buildCourseUrl(['gradeable', $gradeable_id,'team'])]
        );
        $subject = "New Team Invitation: " . $graded_gradeable->getGradeable()->getTitle();
        $content = "You have received a new invitation to join a team from $user_id";
        $event = ['component' => 'team', 'metadata' => $metadata, 'subject' => $subject, 'content' => $content, 'type' => 'team_invite', 'sender_id' => $user_id];
        $this->core->getNotificationFactory()->onTeamEvent($event, [$invite_id]);

        $this->core->addSuccessMessage("Invitation sent to {$invite_id}");

        $current_time = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO") . " " . $this->core->getConfig()->getTimezone()->getName();
        $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id, $team->getId(), "user_assignment_settings.json");
        $json = FileUtils::readJsonFile($settings_file);
        if ($json === false) {
            $this->core->addErrorMEssage("Failed to open settings file");
            $this->core->redirect($return_url);
        }
        $json["team_history"][] = ["action" => "send_invitation", "time" => $current_time, "sent_by_user" => $user_id, "sent_to_user" => $invite_id];

        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $this->core->addErrorMEssage("Failed to write to team history to settings file");
        }
        $this->core->redirect($return_url);
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team/invitation/accept", methods: ["POST"])]
    public function acceptInvitation($gradeable_id) {
        $user_id = $this->core->getUser()->getId();

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid or missing gradeable id!');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getTitle()} is not a team assignment");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $return_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team']);

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id, false);
        if ($graded_gradeable !== false) {
            $this->core->addErrorMessage("You must leave your current team before you can accept an invitation");
            $this->core->redirect($return_url);
        }

        $accept_team_id = (isset($_POST['team_id'])) ? $_POST['team_id'] : null;
        $accept_team = $this->core->getQueries()->getTeamById($accept_team_id);
        if ($accept_team === null) {
            $this->core->addErrorMessage("{$accept_team_id} is not a valid team id");
            $this->core->redirect($return_url);
        }

        if (!$accept_team->sentInvite($user_id)) {
            $this->core->addErrorMessage("No invitation from {$accept_team->getMemberList()}");
            $this->core->redirect($return_url);
        }

        if ($accept_team->getSize() >= $gradeable->getTeamSizeMax()) {
            $this->core->addErrorMessage("Cannot accept invitation. Max team size is {$gradeable->getTeamSizeMax()}");
            $this->core->redirect($return_url);
        }



        $this->core->getQueries()->declineAllTeamInvitations($gradeable_id, $user_id);
        $this->core->getQueries()->acceptTeamInvitation($accept_team_id, $user_id);
        $this->core->getQueries()->removeFromSeekingTeam($gradeable_id, $user_id);
        $team_members = $accept_team->getMembers();
        // send notification to team members that user joined
        $metadata =  json_encode(
            ['url' => $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team'])]
        );
        $subject = "New Team Member: " . $gradeable->getTitle();
        $content = "A new team member with the user name, $user_id, joined your team for gradeable, " . $gradeable->getTitle();
        $event = ['component' => 'team', 'metadata' => $metadata, 'subject' => $subject, 'content' => $content, 'type' => 'team_joined', 'sender_id' => $user_id];
        $this->core->getNotificationFactory()->onTeamEvent($event, $team_members);

        $this->core->addSuccessMessage("Accepted invitation from {$accept_team->getMemberList()}");

        $current_time = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO") . " " . $this->core->getConfig()->getTimezone()->getName();
        $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id, $accept_team_id, "user_assignment_settings.json");
        $json = FileUtils::readJsonFile($settings_file);
        if ($json === false) {
            $this->core->addErrorMessage("Failed to open settings file");
            $this->core->redirect($return_url);
        }
        $json["team_history"][] = ["action" => "accept_invitation", "time" => $current_time, "user" => $user_id];

        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $this->core->addErrorMessage("Failed to write to team history to settings file");
        }
        $this->core->redirect($return_url);
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team/invitation/cancel", methods: ["POST"])]
    public function cancelInvitation($gradeable_id) {
        $user_id = $this->core->getUser()->getId();

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid or missing gradeable id!');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getTitle()} is not a team assignment");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $return_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team']);

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id, false);
        if ($graded_gradeable === false) {
            $this->core->addErrorMessage("You are not on a team");
            $this->core->redirect($return_url);
        }
        $team = $graded_gradeable->getSubmitter()->getTeam();

        $date = $this->core->getDateTimeNow();
        if ($date->format('Y-m-d H:i:s') > $gradeable->getTeamLockDate()->format('Y-m-d H:i:s')) {
            $this->core->addErrorMessage("Teams are now locked. Contact your instructor to change your team.");
            $this->core->redirect($return_url);
        }

        $cancel_id = (isset($_POST['cancel_id'])) ? $_POST['cancel_id'] : null;
        if (!$team->sentInvite($cancel_id)) {
            $this->core->addErrorMessage("No invitation sent to {$cancel_id}");
            $this->core->redirect($return_url);
        }

        $this->core->getQueries()->cancelTeamInvitation($team->getId(), $cancel_id);
        $this->core->addSuccessMessage("Cancelled invitation to {$cancel_id}");

        $current_time = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO") . " " . $this->core->getConfig()->getTimezone()->getName();
        $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id, $team->getId(), "user_assignment_settings.json");
        $json = FileUtils::readJsonFile($settings_file);
        if ($json === false) {
            $this->core->addErrorMEssage("Failed to open settings file");
            $this->core->redirect($return_url);
        }
        $json["team_history"][] = ["action" => "cancel_invitation", "time" => $current_time, "canceled_by_user" => $user_id, "canceled_user" => $cancel_id];

        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $this->core->addErrorMEssage("Failed to write to team history to settings file");
        }
        $this->core->redirect($return_url);
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team/seek/new")]
    public function seekTeam($gradeable_id) {
        $user_id = $this->core->getUser()->getId();
        $message = $this->core->getUser()->getSeekMessage($gradeable_id);

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid or missing gradeable id!');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getTitle()} is not a team assignment");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $return_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team']);

        $seeking = $this->core->getQueries()->getUsersSeekingTeamByGradeableId($gradeable_id);

        if (in_array($user_id, $seeking)) {
            $this->core->addErrorMessage('Already in the list of users seeking team/partner!');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $this->core->getQueries()->addToSeekingTeam($gradeable_id, $user_id, $message);
        $this->core->addSuccessMessage("Added to list of users seeking team/partner");
        $this->core->redirect($return_url);
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team/seek/message", methods: ["POST"])]
    public function editSeekMessage($gradeable_id) {
        $user_id = $this->core->getUser()->getId();

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid or missing gradeable id!');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getTitle()} is not a team assignment");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $return_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team']);

        if (!isset($_POST['seek_message']) || ($_POST['seek_message'] === '')) {
            $this->core->addErrorMessage("No message specified");
            $this->core->redirect($return_url);
        }
        $message = $_POST['seek_message'];

        $this->core->getQueries()->updateSeekingTeamMessageById($gradeable_id, $user_id, $message);
        $this->core->addSuccessMessage("Edited seeking team/partner message");
        $this->core->redirect($return_url);
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team/seek/message/remove")]
    public function removeSeekMessage($gradeable_id) {
        $user_id = $this->core->getUser()->getId();

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid or missing gradeable id!');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getTitle()} is not a team assignment");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $return_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team']);

        $this->core->getQueries()->updateSeekingTeamMessageById($gradeable_id, $user_id, null);
        $this->core->addSuccessMessage("Removed seeking team/partner message");
        $this->core->redirect($return_url);
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team/seek/stop")]
    public function stopSeekTeam($gradeable_id) {
        $user_id = $this->core->getUser()->getId();

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid or missing gradeable id!');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getTitle()} is not a team assignment");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $return_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team']);

        $this->core->getQueries()->removeFromSeekingTeam($gradeable_id, $user_id);
        $this->core->addSuccessMessage("Removed from list of users seeking team/partner");
        $this->core->redirect($return_url);
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team/setname")]
    public function setTeamName($gradeable_id): RedirectResponse {
        $user_id = $this->core->getUser()->getId();

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage("Invalid gradeable");
            return new RedirectResponse($this->core->buildCourseUrl());
        }

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getTitle()} is not a team assignment");
            return new RedirectResponse($this->core->buildCourseUrl());
        }

        $return_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'team']);

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id, false);
        if ($graded_gradeable === false) {
            $this->core->addErrorMessage("You are not on a team");
            return new RedirectResponse($return_url);
        }
        $team = $graded_gradeable->getSubmitter()->getTeam();

        if (!isset($_POST['team_name'])) {
            $this->core->addErrorMessage("You must pick a name");
            return new RedirectResponse($return_url);
        }

        if ($_POST['team_name'] == '') {
            $_POST['team_name'] = null;
        }
        else {
            if (strlen($_POST['team_name']) > Team::MAX_TEAM_NAME_LENGTH) {
                $this->core->addErrorMessage("Number of characters in team name must be less than " . Team::MAX_TEAM_NAME_LENGTH);
                return new RedirectResponse($return_url);
            }

            if (self::hasSpecialCharacters($_POST['team_name'])) {
                $this->core->addErrorMessage("Team name should not contain special characters");
                return new RedirectResponse($return_url);
            }
        }

        if ($_POST['team_name'] === $team->getTeamName()) {
            $this->core->addErrorMessage("No changes detected in team name");
            return new RedirectResponse($return_url);
        }

        $this->core->getQueries()->updateTeamName($team->getId(), $_POST['team_name']);

        $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id, $team->getId(), "user_assignment_settings.json");
        $json = FileUtils::readJsonFile($settings_file);
        if ($json === false) {
            $this->core->addErrorMessage("Failed to open settings file");
            return new RedirectResponse($return_url);
        }
        $current_time = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO") . " " . $this->core->getConfig()->getTimezone()->getName();
        $json["team_history"][] = ["action" => "change_name", "time" => $current_time, "user" => $user_id];

        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $this->core->addErrorMessage("Failed to write to team history to settings file");
            return new RedirectResponse($return_url);
        }

        $this->core->addSuccessMessage("Team name successfully set");
        return new RedirectResponse($return_url);
    }

    /**
     * Function to create single-student teams for all students without a team
     *
     * @param string $gradeable_id
     * @return array<string>
     */

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team/create_single_student_teams", methods: ["POST"])]
    public function createSingleStudentTeams($gradeable_id) {
        $students = $this->core->getQueries()->getAllUsers();
        $students_needing_teams = [];

        foreach ($students as $student) {
            // Skip null registration section users
            if ($student->getRegistrationSection() === null) {
                continue;
            }

            // Skip if user is already on a team for this gradeable
            if ($this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $student->getId()) !== null) {
                continue;
            }

            $students_needing_teams[] = $student;
        }

        if (count($students_needing_teams) === 0) {
            $result = "No new teams created. All registered students are already on teams.";
            return $this->core->getOutput()->renderResultMessage($result, false);
        }

        $teams_created_count = 0;

        // Create a single-person team for each remaining student
        foreach ($students_needing_teams as $student) {
            $this->core->getQueries()->createTeam(
                $gradeable_id,
                $student->getId(),
                $student->getRegistrationSection(),
                $student->getRotatingSection(),
                null
            );

            $this->core->getQueries()->declineAllTeamInvitations($gradeable_id, $student->getId());
            $this->core->getQueries()->removeFromSeekingTeam($gradeable_id, $student->getId());

            $teams_created_count++;
        }

        $result = "Successfully created {$teams_created_count} single-student teams.";
        return $this->core->getOutput()->renderResultMessage($result, true);
    }

    /**
    * Function to create teams from registration subsections.
    *
    * @param string $gradeable_id
    * @return array<string>
    */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team/create_teams_from_subsections", methods: ["POST"])]
    public function createTeamsFromSubsections($gradeable_id) {
        $users = $this->core->getQueries()->getAllUsers();
        $subsections = [];

        // Group users by their subsection
        foreach ($users as $user) {
            $section = $user->getRegistrationSection();
            $subsection = $user->getRegistrationSubsection();
            if ($section === null || $subsection === '') {
                continue;
            }

            // Skip if user is already on a team
            if ($this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $user->getId()) !== null) {
                continue;
            }

            $subsections[$subsection][] = $user;
        }

        if (count($subsections) === 0) {
            $result = "No new teams created. All students are already on teams or aren't assigned subsections.";
            return $this->core->getOutput()->renderResultMessage($result, false);
        }

        $teams_created_count = 0;

        foreach ($subsections as $members) {
            // use the first subsection member as team leader
            $team_leader = array_shift($members);

            $team_id = $this->core->getQueries()->createTeam(
                $gradeable_id,
                $team_leader->getId(),
                $team_leader->getRegistrationSection(),
                $team_leader->getRotatingSection(),
                null
            );

            // set team name to subsection
            $this->core->getQueries()->updateTeamName($team_id, $team_leader->getRegistrationSubsection());

            // add remaining subsection members to the team
            foreach ($members as $member) {
                $this->core->getQueries()->declineAllTeamInvitations($gradeable_id, $member->getId());
                $this->core->getQueries()->removeFromSeekingTeam($gradeable_id, $member->getId());

                $this->core->getQueries()->acceptTeamInvitation($team_id, $member->getId());
            }

            $teams_created_count++;
        }
        $result = "Successfully created {$teams_created_count} teams from registration subsections.";
        return $this->core->getOutput()->renderResultMessage($result, true);
    }

    /**
     * Function to delete all teams without submissions for a given gradeable.
     * Teams that have already made a submission will be skipped.
     * Teams with instructor-level users will be skipped.
     *
     * @param string $gradeable_id
     * @return array<string>
    */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team/delete_all_teams", methods: ["POST"])]
    public function deleteTeams($gradeable_id) {

        $teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable_id);

        if (count($teams) === 0) {
            $result = "No teams exist to delete.";
            return $this->core->getOutput()->renderResultMessage($result, false);
        }

        $deleted_count = 0;
        $skipped_count = 0;
        $instructor_skipped_count = 0;

        foreach ($teams as $team) {
            $team_id = $team->getId();
            $is_empty = $team->getSize() === 0;
            // Check if the team has a submission.
            $has_submission = $this->core->getQueries()->getActiveVersionForTeam($gradeable_id, $team_id) > 0;

            $gradeable = $this->tryGetGradeable($gradeable_id, false);
            $cancelled_submission = false;

            // Perform checks then delete team
            $members = $team->getMemberUsers();
            $is_instructor_team = false;
            foreach ($members as $member) {
                // Check if this is the instructor team
                if ($member->getGroup() === User::GROUP_INSTRUCTOR) {
                    $is_instructor_team = true;
                }

                // Check if the team has a cancelled submission
                $user_id = $member->getId();
                $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id);
                $cancelled_submission = ($graded_gradeable->getAutoGradedGradeable()->getActiveVersion() === LateDayInfo::STATUS_NO_ACTIVE_VERSION) && ($graded_gradeable->getAutoGradedGradeable()->hasSubmission());
            }

            if ($is_empty) {
                continue;
            }

            if ($is_instructor_team) {
                $instructor_skipped_count++;
                continue;
            }

            if ($cancelled_submission || $has_submission) {
                $skipped_count++;
                continue;
            }

            try {
                $this->core->getQueries()->deleteTeam($team_id);
            }
            catch (\Exception $e) {
                $result = $e->getMessage();
                return $this->core->getOutput()->renderResultMessage($result, false);
            }
            $deleted_count++;
        }

        $result = "Successfully deleted {$deleted_count} teams. Skipped {$skipped_count} teams with submissions. Skipped {$instructor_skipped_count} teams with instructor-level users.";
        return $this->core->getOutput()->renderResultMessage($result, true);
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/team")]
    public function showPage($gradeable_id) {
        $user_id = $this->core->getUser()->getId();

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid or missing gradeable id!');
            $this->core->redirect($this->core->buildCourseUrl());
        }

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getTitle()} is not a team assignment");
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id, false);
        $team = null;
        if ($graded_gradeable !== false) {
            $team = $graded_gradeable->getSubmitter()->getTeam();
        }

        $teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable_id);

        $members = [];
        $seekers = [];
        $invites_received = [];
        $users_seeking_team = $this->core->getQueries()->getUsersSeekingTeamByGradeableId($gradeable_id);
        $seeking_partner = false;

        // Batch-fetch all needed users in a single query
        $all_user_ids = array_merge($team?->getMembers() ?? [], $users_seeking_team);
        $users_map = $this->core->getQueries()->getUsersById(array_unique($all_user_ids));

        if ($team !== null) {
            //List team members
            foreach ($team->getMembers() as $teammate) {
                if (isset($users_map[$teammate])) {
                    $members[] = $users_map[$teammate];
                }
            }
        }
        else {
            //Invites
            foreach ($teams as $t) {
                if ($t->sentInvite($user_id)) {
                    $invites_received[] = $t;
                }
            }

            //Are you seeking a team?
            $seeking_partner = in_array($user_id, $users_seeking_team);
        }

        foreach ($users_seeking_team as $user_seeking_team) {
            if (isset($users_map[$user_seeking_team])) {
                $seekers[] = $users_map[$user_seeking_team];
            }
        }

        $all_users = $this->core->getQueries()->getAllUsers();

        $students_on_teams_count = 0;
        $students_not_on_teams_count = 0;
        $all_students_count = 0;

        foreach ($all_users as $user) {
            if ($user->getRegistrationSection() === null) {
                continue;
            }
            $all_students_count = $all_students_count + 1;
        }

        foreach ($teams as $t) {
            $t_members = $t->getMemberUsers();
            // handle the special case of instructor being on a team
            foreach ($t_members as $t_member) {
                if ($t_member->getRegistrationSection() !== null) {
                    $students_on_teams_count++;
                }
            }
        }

        $students_not_on_teams_count = $all_students_count - $students_on_teams_count;

        $date = $this->core->getDateTimeNow();
        $lock = $date->format('Y-m-d H:i:s') > $gradeable->getTeamLockDate()->format('Y-m-d H:i:s');
        $this->core->getOutput()->addBreadcrumb("Manage Team For: {$gradeable->getTitle()}");
        $this->core->getOutput()->renderOutput(['submission', 'Team'], 'showTeamPage', $gradeable, $team, $members, $seekers, $invites_received, $seeking_partner, $lock, $students_not_on_teams_count, $students_on_teams_count);
    }

    /**
     * Get clean user_id invite
     * @param string $invite_id
     * @return string
     */
    private static function cleanInviteId(string $invite_id): string {
        return trim(strtolower($invite_id));
    }
}

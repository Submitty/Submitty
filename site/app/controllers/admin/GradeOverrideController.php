<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class GradeOverrideController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 */
class GradeOverrideController extends AbstractController {
    #[Route("/courses/{_semester}/{_course}/grade_override")]
    public function viewOverriddenGrades() {
        $gradeables = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        $students = $this->core->getQueries()->getAllUsers();
        $this->core->getOutput()->addInternalCss('gradeOverride.css');
        $this->core->getOutput()->renderOutput(
            ['admin', 'GradeOverride'],
            'displayOverriddenGrades',
            $gradeables,
            $students
        );
    }

    #[Route("/courses/{_semester}/{_course}/grade_override/{gradeable_id}")]
    public function getOverriddenGrades($gradeable_id) {
        $users = $this->core->getQueries()->getUsersWithOverriddenGrades($gradeable_id);
        $user_table = [];
        foreach ($users as $user) {
            $user_table[] = ['user_id' => $user->getId(),'user_givenname' => $user->getDisplayedGivenName(), 'user_familyname' => $user->getDisplayedFamilyName(), 'marks' => $user->getMarks(), 'comment' => $user->getComment()];
        }
        return $this->core->getOutput()->renderJsonSuccess([
            'gradeable_id' => $gradeable_id,
            'users' => $user_table,
        ]);
    }

    #[Route("/courses/{_semester}/{_course}/grade_override/{gradeable_id}/delete", methods: ["POST"])]
    public function deleteOverriddenGrades($gradeable_id) {
        $this->core->getQueries()->deleteOverriddenGrades($_POST['user_id'], $gradeable_id);
        return $this->getOverriddenGrades($gradeable_id);
    }

    #[Route("/courses/{_semester}/{_course}/grade_override/{gradeable_id}/update", methods: ["POST"])]
    public function updateOverriddenGrades($gradeable_id) {
        $user = $this->core->getQueries()->getSubmittyUser($_POST['user_id']);
        $isUserNotInCourse = empty($this->core->getQueries()->getUsersById([$_POST['user_id']]));
        if (!isset($_POST['user_id']) || $_POST['user_id'] == "" || $isUserNotInCourse || $user->getId() !== $_POST['user_id']) {
            $error = "Invalid Student ID";
            return $this->core->getOutput()->renderJsonFail($error);
        }

        if (((!isset($_POST['marks'])) || $_POST['marks'] == "" || is_float($_POST['marks']))) {
            $error = "Marks must be an integer";
            return $this->core->getOutput()->renderJsonFail($error);
        }

        $team = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $_POST['user_id']);

        // if it is a team, generate the popup
        if ($team !== null && $team->getSize() > 1) {
            $team_members = [];
            foreach ($this->getTeamMemberIds($team) as $member_id) {
                $member = $this->core->getQueries()->getUserById($member_id);
                $team_members[$member_id] = $member->getDisplayedGivenName() . " " . $member->getDisplayedFamilyName();
            }

            return $this->core->getOutput()->renderJsonSuccess([
                'is_team' => true,
                'component' => 'OverrideTeamPopup',
                'args' => [
                    'userId' => $_POST['user_id'],
                    'memberList' => $team_members,
                ],
            ]);
        }
        // not a team, just single override
        else {
            $this->core->getQueries()->updateGradeOverride($_POST['user_id'], $gradeable_id, $_POST['marks'], $_POST['comment']);
            return $this->getOverriddenGrades($gradeable_id);
        }
    }

    #[Route("/courses/{_semester}/{_course}/grade_override/{gradeable_id}/update_team", methods: ["POST"])]
    public function updateTeamOverriddenGrades($gradeable_id) {
        $members = json_decode($_POST['members'] ?? '[]', true);

        // Which button did they click? 0 = solo, 1 = apply to all
        $members  = json_decode($_POST['members'] ?? '[]', true);
        $fullTeam = filter_var($_POST['full_team'] ?? '0', FILTER_VALIDATE_BOOLEAN);
        $marks    = (int) ($_POST['marks']   ?? 0);
        $comment  =           ($_POST['comment'] ?? '');
        $userId   =           ($_POST['user_id'] ?? '');

        if ($fullTeam) {
            // User selects yes: Update the entire team
            foreach (array_keys($members) as $member_id) {
                $this->core->getQueries()->updateGradeOverride($member_id, $gradeable_id, $marks, $comment);
            }
        }
        else {
            // User selects no: Only update original student
            $user_id = $_POST['user_id'];
            $this->core->getQueries()->updateGradeOverride($user_id, $gradeable_id, $marks, $comment);
        }

        return $this->getOverriddenGrades($gradeable_id);
    }

    /**
     * @param object $team  An object with getMemberList(): string
     * @return string[]     An array of member-ID strings
     */
    private function getTeamMemberIds(object $team): array {
        return explode(", ", $team->getMemberList());
    }

    /**
     * @param object       $team      An object with getMemberList(): string
     * @return array<string,mixed>    The payload for the popup
     */
    private function renderTeamPrompt(object $team): array {
        $team_members = [];
        foreach ($this->getTeamMemberIds($team) as $member_id) {
            $member = $this->core->getQueries()->getUserById($member_id);
            $team_members[$member_id] =
                $member->getDisplayedGivenName() . ' ' . $member->getDisplayedFamilyName();
        }

        return [
            'is_team'   => true,
            'component' => 'OverrideTeamPopup',
            'args'      => [
                'memberList' => $team_members,
            ],
        ];
    }
}

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
        $team = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $_POST['user_id']);
        //0 is for single submission, 1 is for team submission
        $option = $_POST['option'] ?? -1;
        if ($team !== null && $team->getSize() > 1) {
            if (intval($option) === 0) {
                $this->core->getQueries()->deleteOverriddenGrades($_POST['user_id'], $gradeable_id);
                return $this->getOverriddenGrades($gradeable_id);
            }
            elseif (intval($option) === 1) {
                foreach ($this->getTeamMemberIds($team) as $member_id) {
                    $this->core->getQueries()->deleteOverriddenGrades($member_id, $gradeable_id);
                }
                return $this->getOverriddenGrades($gradeable_id);
            }
            else {
                $popup_html = $this->renderTeamPrompt($team, true);
                return $this->core->getOutput()->renderJsonSuccess([
                    'is_team' => true,
                    'popup' => $popup_html
                ]);
            }
        }
        else {
            $this->core->getQueries()->deleteOverriddenGrades($_POST['user_id'], $gradeable_id);
            return $this->getOverriddenGrades($gradeable_id);
        }
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
        //0 is for single submission, 1 is for team submission
        $option = $_POST['option'] ?? -1;
        if ($team !== null && $team->getSize() > 1) {
            if (intval($option) === 0) {
                $this->core->getQueries()->updateGradeOverride($_POST['user_id'], $gradeable_id, $_POST['marks'], $_POST['comment']);
                return $this->getOverriddenGrades($gradeable_id);
            }
            elseif (intval($option) === 1) {
                foreach ($this->getTeamMemberIds($team) as $member_id) {
                    $this->core->getQueries()->updateGradeOverride($member_id, $gradeable_id, $_POST['marks'], $_POST['comment']);
                }
                return $this->getOverriddenGrades($gradeable_id);
            }
            else {
                $team_members = [];
                foreach ($this->getTeamMemberIds($team) as $member_id) {
                    $member = $this->core->getQueries()->getUserById($member_id);
                    $team_members[$member_id] = $member->getDisplayedGivenName() . " " . $member->getDisplayedFamilyName();
                }

                return $this->core->getOutput()->renderJsonSuccess([
                    'is_team' => true,
                    'component' => 'OverrideTeamPopup',
                    'args' => [
                        'memberList' => $team_members,
                        'isDelete' => false,
                    ],
                ]);
            }
        }
        else {
            $this->core->getQueries()->updateGradeOverride($_POST['user_id'], $gradeable_id, $_POST['marks'], $_POST['comment']);
            return $this->getOverriddenGrades($gradeable_id);
        }
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
     * @param bool         $is_delete
     * @return array<string,mixed>    The payload for the popup
     */
    private function renderTeamPrompt(object $team, bool $is_delete): array {
        $member_ids = $this->getTeamMemberIds($team);
        $users = $this->core->getQueries()->getUsersById($member_ids);
        $team_members = [];
        foreach ($users as $user) {
            $team_members[$user->getId()] =
                $user->getDisplayedGivenName() . ' ' . $user->getDisplayedFamilyName();
        }

        return [
            'is_team'   => true,
            'component' => 'OverrideTeamPopup',
            'args'      => [
                'memberList' => $team_members,
                'isDelete'   => $is_delete,
            ],
        ];
    }
}

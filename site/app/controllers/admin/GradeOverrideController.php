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
        $post    = $this->core->getRequest()->request;
        $user_id = $post->get('user_id', '');

        $users = $this->core->getQueries()->getUsersById([$user_id]);
        if ($user_id === '' || empty($users) || !isset($users[$user_id])) {
            return $this->core->getOutput()->renderJsonFail("Invalid Student ID");
        }

        $marks = $post->get('marks');
        if ($marks === '' || !ctype_digit($marks)) {
            return $this->core->getOutput()->renderJsonFail("Marks must be an integer");
        }
        $marks   = (int)$marks;
        $comment = $post->get('comment', '');

        $team   = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $user_id);
        $option = intval($post->get('option', -1));

        if ($team !== null && $team->getSize() > 1) {
            if ($option === 0) {
                $this->core->getQueries()
                    ->updateGradeOverride($user_id, $gradeable_id, $marks, $comment);
                return $this->getOverriddenGrades($gradeable_id);
            }
            elseif ($option === 1) {
                $member_ids = $this->getTeamMemberIds($team);
                foreach ($member_ids as $member_id) {
                    $this->core->getQueries()
                        ->updateGradeOverride($member_id, $gradeable_id, $marks, $comment);
                }
                return $this->getOverriddenGrades($gradeable_id);
            }
            else {
                $member_ids = $this->getTeamMemberIds($team);
                $members     = $this->core->getQueries()->getUsersById($member_ids);
                $team_members = [];
                foreach ($member_ids as $id) {
                    $u = $members[$id];
                    $team_members[$id] = $u->getDisplayedGivenName() . ' ' . $u->getDisplayedFamilyName();
                }
                return $this->core->getOutput()->renderJsonSuccess([
                    'is_team'   => true,
                    'component' => 'OverrideTeamPopup',
                    'args'      => [
                        'memberList' => $team_members,
                        'isDelete'   => false,
                    ],
                ]);
            }
        }

        // 5) Not a team (or team of one) → single override
        $this->core->getQueries()
            ->updateGradeOverride($user_id, $gradeable_id, $marks, $comment);
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

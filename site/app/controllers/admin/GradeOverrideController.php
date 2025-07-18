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
        $user_id = $_POST['user_id'] ?? '';
        $marks   = $_POST['marks']   ?? '';
        $comment = $_POST['comment'] ?? '';
        $option  = intval($_POST['option'] ?? -1);

        $user = $this->core->getQueries()->getSubmittyUser($user_id);
        if (!$user || $user->getId() !== $user_id) {
            return $this->core->getOutput()->renderJsonFail("Invalid Student ID");
        }

        if ($marks === '' || !ctype_digit($marks)) {
            return $this->core->getOutput()->renderJsonFail("Marks must be an integer");
        }
        $marks = (int)$marks;

        $team = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $user_id);

        if ($team !== null && $team->getSize() > 1) {
            if ($option === 0) {
                $this->core->getQueries()
                    ->updateGradeOverride($user_id, $gradeable_id, $marks, $comment);
                return $this->getOverriddenGrades($gradeable_id);
            }
            elseif ($option === 1) {
                foreach ($this->getTeamMemberIds($team) as $member_id) {
                    $this->core->getQueries()
                        ->updateGradeOverride($member_id, $gradeable_id, $marks, $comment);
                }
                return $this->getOverriddenGrades($gradeable_id);
            }
            else {
                $member_ids   = $this->getTeamMemberIds($team);
                $all_members  = $this->core->getQueries()->getUsersById($member_ids);
                $team_members = [];
                foreach ($member_ids as $id) {
                    $u = $all_members[$id];
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
}

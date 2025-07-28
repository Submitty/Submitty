<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class GradeOverrideController
 * @package app\controllers\admin
 */
#[AccessControl(role: "INSTRUCTOR")]
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
        $marks   = $_POST['marks'] ?? '';
        $comment = $_POST['comment'] ?? '';
        $option  = $_POST['option'] ?? '';

        $user = $this->core->getQueries()->getSubmittyUser($user_id);

        if ($marks === '' || !ctype_digit($marks)) {
            return $this->core->getOutput()->renderJsonFail("Marks must be at least 0");
        }
        $marks = (int) $marks;

        $team = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $user_id);

        if ($team !== null && $team->getSize() > 1) {
            if ($option === 'single') {
                $this->core->getQueries()
                    ->updateGradeOverride($user_id, $gradeable_id, $marks, $comment);
                return $this->getOverriddenGrades($gradeable_id);
            }
            elseif ($option === 'batch') {
                $team_member_ids = $this->getTeamMemberIds($team);
                $this->core->getQueries()->updateGradeOverrideBatch($team_member_ids, $gradeable_id, $marks, $comment);
                return $this->getOverriddenGrades($gradeable_id);
            }
            else {
                $member_ids  = $this->getTeamMemberIds($team);
                $all_members = $this->core->getQueries()->getUsersById($member_ids);
                $team_members = [];
                foreach ($all_members as $id => $member) {
                    $team_members[$id] = $member->getDisplayedGivenName() . " " . $member->getDisplayedFamilyName();
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

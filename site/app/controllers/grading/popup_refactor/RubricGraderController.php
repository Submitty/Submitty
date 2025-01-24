<?php

/**
 * ---------------------
 *
 * RubricGraderController.php
 *
 * This class's createMainRubricGradeablePage will eventually be called when a
 * Rubric Gradeable's grading page is opened.
 *
 * Currently, to access the page associated with this class, enter URL:
 *
 *     /courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading_beta[?more_stuff_here_is_okay]
 *
 * This class is also responsible for updating popup windows created.
 *
 * ---------------------
 */

// Namespace:
namespace app\controllers\grading\popup_refactor;

// Includes:
use app\controllers\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\GradeableType;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\User;

// Main Class:
class RubricGraderController extends AbstractController {
    // ---------------------------------

    // Functions:

    /**
     * Creates the Rubric Grading page.
     *
     * @param string $gradeable_id - The id string of the current gradeable.
     * @param string $who_id - The id of the student we should grade.
     * @param string $sort - The current way we are sorting students. Determines who the next and prev students are.
     * @param string $direction - Either "ASC" or "DESC" for ascending or descending sorting order.
     * @param string $navigate_assigned_students_only - When going to the next student, this variable controls
     *                whether we skip students.
     *
     * This page is loaded on line 476 of Details.twig when the Grade button is clicked.
     *
     * Note that the argument names cannot be changed easily as they need to line up with the arguments
     * provided to the URL.
     * @return void
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading_beta/grade")]
    public function createMainRubricGraderPage(
        string $gradeable_id,
        string $who_id = '',
        string $sort = "id",
        string $direction = "ASC",
        string $navigate_assigned_students_only = "true"
    ): void {
        $gradeable          = $this->getCurrentGradeable($gradeable_id);

        $sort_type          = $sort;
        $sort_direction     = $direction;
        $details_page       = $this->gradeableDetailsPage($gradeable, $sort_type, $sort_direction);

        $current_submission = $this->getCurrentSubmission($gradeable, $who_id, $details_page);
        $sort_type          = $sort;
        $sort_direction     = $direction;
        $user_group         = $this->getUserGroup();
        $is_peer_gradeable  = $this->getIfPeerGradeable($gradeable);
        $is_team_gradeable  = $this->getIfTeamGradeable($gradeable);
        $blind_access_mode  = $this->determineBlindAccessMode($user_group, $gradeable, $is_peer_gradeable);

        $this->core->getOutput()->renderOutput(
            // Path:
            ['grading', 'popup_refactor', 'RubricGrader'],
            // Function Name:
            'createRubricGradeableView',
            // Arguments:
            $gradeable,
            $current_submission,
            $sort_type,
            $sort_direction,
            $is_peer_gradeable,
            $is_team_gradeable,
            $blind_access_mode,
            $details_page
        );
    }


    /**
     * Returns gradeable to the appropriate assignment unless $gradeable_id is invalid,
     * in which case an error is printed and the code exits.
     *
     * @param string $gradeable_id - The id string of the current gradeable.
     * @return Gradeable - The current gradeable being graded.
     */
    private function getCurrentGradeable(string $gradeable_id): Gradeable {
        // tryGetGradeable inherited from AbstractController
        $gradeable = $this->tryGetGradeable($gradeable_id, false);

        // Gradeable must exist and be Rubric.
        $error_message = "";
        if ($gradeable === false) {
            $error_message = 'Invalid Gradeable!';
        }
        elseif ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $error_message = 'This gradeable is not a rubric gradeable.';
        }

        if (!empty($error_message)) {
            $this->core->addErrorMessage($error_message);
            // The following line exits execution.
            $this->core->redirect($this->core->buildCourseUrl());
        }

        return $gradeable;
    }


    /**
     * Returns the current student's submission we are looking at.
     * If the submission does not exist, we exit the page.
     *
     * @param Gradeable $gradeable    - The current gradeable being graded.
     * @param string    $who_id       - The anonymous id of the student we should grade.
     * @param string    $details_page - URL of this gradeable's details page in case we need to redirect there.
     *
     * @return GradedGradeable - Current submission being graded.
     */
    private function getCurrentSubmission(
        Gradeable $gradeable,
        string $who_id,
        string $details_page
    ): GradedGradeable {
        $submitter_id = $this->core->getQueries()->getSubmitterIdFromAnonId($who_id, $gradeable->getId());
        if ($submitter_id === null) {
            $submitter_id = $who_id;
        }
        $current_submission = $this->tryGetGradedGradeable($gradeable, $submitter_id, false);

        // Submission does not exist
        if ($current_submission === false) {
            $this->core->redirect($details_page);
        }

        return $current_submission;
    }


    /**
     * Returns the URL of this gradeable's details page.
     *
     * @param Gradeable $gradeable    - Current gradeable we are grading.
     * @param string $sort_type       - Way we are sorting through students, e.g. by "id", etc.
     * @param  string $sort_direction - Either "ASC" or "DESC" for which way we sort by that type.
     * @return string URL of the gradeable details page.
     */
    private function gradeableDetailsPage(
        Gradeable $gradeable,
        string $sort_type,
        string $sort_direction
    ): string {
        return $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'details'])
            . '?'
            . http_build_query(['sort' => $sort_type, 'direction' => $sort_direction]);
    }


    /**
     * Gets the current user type for the website user.
     * @return int - Group int. See User model class.
     */
    private function getUserGroup(): int {
        return $this->core->getUser()->getGroup();
    }


    /**
     * Returns whether the current gradeable has peer grading.
     *
     * @param Gradeable $gradeable - The current gradeable we are grading.
     * @return bool - True if this gradeable has peer grading.
     */
    private function getIfPeerGradeable(Gradeable $gradeable): bool {
        return $gradeable->hasPeerComponent();
    }


    /**
     * Returns whether the current gradeable has teams.
     *
     * @param Gradeable $gradeable - The current gradeable we are grading.
     * @return bool - True if this gradeable has peer grading.
     */
    private function getIfTeamGradeable(Gradeable $gradeable): bool {
        return $gradeable->isTeamAssignment();
    }


    /**
     * Returns the blind_access_mode for the current user for this grader session of this gradeable.
     *
     * Possible Values:
     *  - "unblind" - Nothing about students is hidden.
     *  - "single"  - For peer grading or for full access grading's Anonymous Mode. Graders cannot see
     *                who they are currently grading.
     *  - "double"  - For peer grading. In addition to blinded peer graders, students cannot
     *                see which peer they are currently grading.
     *
     * @param int $user_group         - Quasi-enum for which user_group we are.
     * @param Gradeable $gradeable    - Gradeable we are grading.
     * @param bool $is_peer_gradeable - True if we are this is a peer gradeable.
     * @return string The blind access mode for the current grader.
     */
    private function determineBlindAccessMode(
        int $user_group,
        Gradeable $gradeable,
        bool $is_peer_gradeable
    ): string {
        // Blind Settings for Instructors and Full Access Graders:
        if ($user_group === User::GROUP_INSTRUCTOR || $user_group === User::GROUP_FULL_ACCESS_GRADER) {
            $anon_mode = $gradeable->getInstructorBlind() - 1;
            $anon_mode_enabled = "anon_mode_" . $gradeable->getId();
            $anon_mode_override =  "default_" . $anon_mode_enabled . "_override";
            if (isset($_COOKIE[$anon_mode_override]) && $_COOKIE[$anon_mode_override] === 'on') {
                $anon_mode = (isset($_COOKIE[$anon_mode_enabled]) && $_COOKIE[$anon_mode_enabled] === 'on');
            }

            if ($anon_mode) {
                return "single";
            }
            else {
                return "unblind";
            }
        } // Blind Settings for Limited Access Graders:
        elseif ($user_group === User::GROUP_LIMITED_ACCESS_GRADER) {
            if ($gradeable->getLimitedAccessBlind() === Gradeable::SINGLE_BLIND_GRADING) {
                return "single";
            }
            else {
                return "unblind";
            }
        } // Blind Settings for Student Peer Graders:
        else { // ($user_group == User::GROUP_STUDENT)
            if ($is_peer_gradeable) {
                if ($gradeable->getPeerBlind() === Gradeable::DOUBLE_BLIND_GRADING) {
                    return "double";
                }
                elseif ($gradeable->getPeerBlind() === Gradeable::SINGLE_BLIND_GRADING) {
                    return "single";
                }
                else {
                    return "unblind";
                }
            }
            else {
                return "double";
            }
        }
    }
}

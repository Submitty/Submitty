<?php

/**
 * ---------------------
 *
 * RubricGradearView.php
 *
 * This class creates the main display window for the Rubric Grader, featuring the
 * NavigationBar and the PanelBar.
 *
 * The function createRubricGradeableView is called from createMainRubricGraderPage
 * of RubricGraderController.php.
 *
 * ---------------------
 */

// Namespace:
namespace app\views\grading\popup_refactor;

// Includes:
use app\views\AbstractView;
use app\models\GradingOrder;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;

// Main Class:
class RubricGraderView extends AbstractView {
    // ---------------------------------

    // Functions:

    /**
     * Creates the Rubric Grading page visually.
     * This function is called in createMainRubricGraderPage of RubricGraderController.php.
     *
     * @param Gradeable $gradeable - The current gradeable.
     * @param GradedGradeable $current_submission - The current submission we are looking at.
     * @param string $sort_type - The current way we are sorting students. Used to create the header.
     * @param string $sort_direction -  Either "ASC" or "DESC" for ascending or descending sorting order.
     *     Used to create the header.
     * @param bool $is_peer_gradeable - True if the gradeable has peer grading.
     * @param bool $is_team_gradeable - True if the gradeable is a team gradeable.
     * @param string $blind_access_mode - Either "unblind", "single", or "double". See RubricGraderController for details.
     * @param string $details_url - URL of the details page for this Gradeable.
     *
     * @return string HTML for the RubricGrader page.
     *
     */
    public function createRubricGradeableView(
        Gradeable $gradeable,
        GradedGradeable $current_submission,
        string $sort_type,
        string $sort_direction,
        bool $is_peer_gradeable,
        bool $is_team_gradeable,
        string $blind_access_mode,
        string $details_url
    ): string {
        $this->createBreadcrumbHeader($gradeable, $sort_type, $sort_direction);

        $this->addCSSs();
        $this->addJavaScriptCode();

        $page_html = $this->core->getOutput()->renderTwigTemplate("grading/popup_refactor/RubricGraderTop.twig");

        $page_html .= $this->renderNavigationBar($blind_access_mode, $is_team_gradeable, $current_submission, $details_url);

        $page_html .= $this->core->getOutput()->renderTwigTemplate("grading/popup_refactor/RubricGraderBottom.twig");

        return $page_html;
    }

    /**
     * Created breadcrumb navigation header based on current sorting and gradeable id.
     * Navigation should be:
     *     Submitty > COURSE_NAME > GRADEABLE_NAME Grading > Grading Interface $sort_id $sort_direction Order
     *
     * @param Gradeable $gradeable - The current gradeable.
     * @param string $sort_type - The current way we are sorting students.
     * @param string $sort_direction -  Either "ASC" or "DESC" for ascending or descending sorting order.
     * @return void
     */
    private function createBreadcrumbHeader(Gradeable $gradeable, string $sort_type, string $sort_direction): void {
        $gradeableUrl = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(),
            'grading', 'details']);
        $this->core->getOutput()->addBreadcrumb("{$gradeable->getTitle()} Grading", $gradeableUrl);

        $this->core->getOutput()->addBreadcrumb('Grading Interface ' .
            GradingOrder::getGradingOrderMessage($sort_type, $sort_direction));
    }


    /**
     * Adds CSS files used for the Rubric Grader page.
     * @return void
     */
    private function addCSSs(): void {
        $this->core->getOutput()->addInternalCss('electronic.css');
    }


    /**
     * Adds JavaScript code used for the Rubric Grader page.
     * @return void
     */
    private function addJavaScriptCode(): void {
        $this->core->getOutput()->addInternalModuleJs('ta-grading-rubric.js');
        $this->core->getOutput()->addInternalModuleJs('ta-grading.js');
    }

    /**
     * Creates the NavigationBar used to traverse between students.
     * @param string $blind_access_mode - Either "unblind", "single", or "double". See RubricGraderController for details.
     * @param bool $is_team_gradeable - True if the gradeable is a team gradeable.
     * @param GradedGradeable $current_submission - The current submission we are looking at.
     * @param string $details_url - URL of the Details page for this gradeable.
     * @return string HTML for the NavigationBar.
     */
    private function renderNavigationBar(
        string $blind_access_mode,
        bool $is_team_gradeable,
        GradedGradeable $current_submission,
        string $details_url
    ): string {
        return $this->core->getOutput()->renderTwigTemplate("grading/popup_refactor/NavigationBar.twig", [
            "blind_access_mode" => $blind_access_mode,
            "is_team_gradeable" => $is_team_gradeable,
            "gradeable_submitter" => $current_submission->getSubmitter(),
            "details_url" => $details_url,
            "progress" => 65 // TODO actually make progress work
        ]);
    }
}

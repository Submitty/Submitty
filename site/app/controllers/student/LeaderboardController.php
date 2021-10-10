<?php

namespace app\controllers\student;

use app\controllers\AbstractController;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\MultiResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\controllers\student\SubmissionController;

class LeaderboardController extends AbstractController {

    /**
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/leaderboard")
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/leaderboard/{leaderboard_tag}")
     * @return array
     */
    public function getLeaderboard($gradeable_id, $leaderboard_tag = null) {
        $gradeable = SubmissionController::tryGetElectronicGradeable($gradeable_id, $this->core);
        if ($gradeable === null) {
            $this->core->addErrorMessage("Invalid gradeable id");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl([]))
            );
        }

        $leaderboards = [];

        $autogradingConfig = $gradeable->getAutogradingConfig();
        if (is_null($autogradingConfig)) {
            // This means the gradeable is being rebuilt
            $this->core->addErrorMessage("Leaderboard currently unavalable, please try again in a few min");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl([]))
            );
        }

        $leaderboards = $autogradingConfig->getLeaderboards();
        if (is_null($leaderboard_tag)) {
            $leaderboard_tag = $leaderboards[0]->getTag();
        }

        $user_id = $this->core->getUser()->getId();
        $user_is_anonymous = $this->core->getQueries()->getUserAnonymousForGradeableLeaderboard($user_id, $gradeable_id);

        $this->core->getOutput()->addBreadcrumb($gradeable->getTitle(), $this->core->buildCourseUrl(["gradeable", $gradeable_id]));
        $this->core->getOutput()->addBreadcrumb("Leaderboard");
        $this->core->getOutput()->addInternalCss('leaderboard.css');
        return $this->core->getOutput()->renderTwigOutput('submission/homework/leaderboard/Leaderboard.twig', [
            "gradeable_name" => $gradeable->getTitle(),
            "leaderboards" => $leaderboards,
            "studentIsAnonymous" => $user_is_anonymous,
            "initial_leaderboard_tag" => $leaderboard_tag,
            "base_url" => $this->core->buildCourseUrl(["gradeable", $gradeable_id]),
            "rebuildingGradeable" => is_null($autogradingConfig)
        ]);
    }

    /**
     * This route is for generating leaderboards for a specific gradable
     * users will not go to this route directly, instead this route should be dynamically requested
     * and its content be inserted inside another html page
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/leaderboard_data/{leaderboard_tag}")
     * @return array
     */
    public function getLeaderboardData($gradeable_id, $leaderboard_tag) {
        $gradeable = SubmissionController::tryGetElectronicGradeable($gradeable_id, $this->core);
        if ($gradeable === null) {
            $this->core->addErrorMessage("Invalid gradeable id");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl([]))
            );
        }

        $leaderboard_data = [];
        $valid_testcases = [];
        $title = "";
        $top_visible_students = 10;

        $autogradingConfig = $gradeable->getAutogradingConfig();
        if (!is_null($autogradingConfig)) {
            $valid_testcases = $autogradingConfig->getTestcasesWithTag($leaderboard_tag);

            $leaderboard = $autogradingConfig->getLeaderboard($leaderboard_tag);
            if (!is_null($leaderboard)) {
                $title = $leaderboard->getTitle();
                $top_visible_students = $leaderboard->getTopVisibleStudents();
                $leaderboard_data = $this->core->getQueries()->getLeaderboard($gradeable_id, false, $valid_testcases);
            }
        }

        $user_index = null;
        foreach ($leaderboard_data as $index => $row) {
            if ($row['user_id'] == $this->core->getUser()->getId()) {
                $user_index = $index;
            }
        }

        // Remove the extra submitty html as this route is just for getting the html for the leaderboard
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);

        return $this->core->getOutput()->renderTwigOutput('submission/homework/leaderboard/LeaderboardTable.twig', [
            "leaderboard" => $leaderboard_data,
            "accessFullGrading" => $this->core->getUser()->accessFullGrading(),
            "top_visible_students" => $top_visible_students,
            "user_id" => $this->core->getUser()->getId(),
            "user_index" => $user_index, //TODO use this to show your current score
            "user_name" => $this->core->getUser()->getDisplayedFirstName() . " " . $this->core->getUser()->getDisplayedLastName()
        ]);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/set_self_anonymity", methods={"POST"})
     * @return MultiResponse
     */
    public function toggleSelfLeaderboardAnonymity($gradeable_id) {
        $this->core->addErrorMessage($gradeable_id);
        if (empty($_POST['anonymity_state'])) {
            $this->core->addErrorMessage("Missing anonymity state");
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse("missing anonymity_state"));
        }

        $user_id = $this->core->getUser()->getId();
        $this->core->getQueries()->setUserAnonymousForGradeableLeaderboard($user_id, $gradeable_id, $_POST['anonymity_state']);
        return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse("Sucess, state changed"));
    }
}

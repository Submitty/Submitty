<?php

namespace app\controllers\student;

use app\controllers\AbstractController;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\MultiResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\controllers\student\SubmissionController;
use app\models\User;

class LeaderboardController extends AbstractController {

    /**
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/leaderboard")
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/leaderboard/{leaderboard_tag}")
     */
    public function getLeaderboard(string $gradeable_id, string $leaderboard_tag = null): MultiResponse {
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

        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'Leaderboard',
                'showLeaderboardPage',
                $gradeable,
                $leaderboards,
                $user_is_anonymous,
                $leaderboard_tag,
                $gradeable_id,
                is_null($autogradingConfig)
            )
        );
    }

    /**
     * This route is for generating leaderboards for a specific gradable
     * users will not go to this route directly, instead this route should be dynamically requested
     * and its content be inserted inside another html page
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/leaderboard_data/{leaderboard_tag}")
     */
    public function getLeaderboardData(string $gradeable_id, string $leaderboard_tag): MultiResponse {
        $gradeable = SubmissionController::tryGetElectronicGradeable($gradeable_id, $this->core);
        if ($gradeable === null) {
            $this->core->addErrorMessage("Invalid gradeable id");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl([]))
            );
        }

        $user_id = $this->core->getUser()->getId();

        $leaderboard_data = [];
        $valid_testcases = [];
        $description = "";
        $top_visible_students = 10;

        $autogradingConfig = $gradeable->getAutogradingConfig();
        if (!is_null($autogradingConfig)) {
            $valid_testcases = $autogradingConfig->getTestcasesWithTag($leaderboard_tag);

            $leaderboard = $autogradingConfig->getLeaderboard($leaderboard_tag);
            if (!is_null($leaderboard)) {
                $description = $leaderboard->getDescription();
                $top_visible_students = $leaderboard->getTopVisibleStudents();
                $leaderboard_data = $this->core->getQueries()->getLeaderboard($gradeable_id, true, $valid_testcases);
            }
        }

        $user_index = -1;
        foreach ($leaderboard_data as $index => $row) {
            if ($row['user_id'] == $user_id) {
                $user_index = $index;
            }
        }

        $user_is_anonymous = $this->core->getQueries()->getUserAnonymousForGradeableLeaderboard($user_id, $gradeable_id);

        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'Leaderboard',
                'showLeaderboardTable',
                $leaderboard_data,
                $top_visible_students,
                $user_id,
                $user_index,
                $description,
                $user_is_anonymous
            )
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/set_self_anonymity", methods={"POST"})
     */
    public function toggleSelfLeaderboardAnonymity(string $gradeable_id): JsonResponse {
        if (empty($_POST['anonymity_state'])) {
            $this->core->addErrorMessage("Missing anonymity state");
            return JsonResponse::getFailResponse("missing anonymity_state");
        }

        $state = $_POST['anonymity_state'] === 'true';

        $user_id = $this->core->getUser()->getId();
        $this->core->getQueries()->setUserAnonymousForGradeableLeaderboard($user_id, $gradeable_id, $state);
        return JsonResponse::getSuccessResponse($state);
    }
}

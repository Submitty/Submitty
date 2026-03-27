<?php
namespace app\controllers\api;

use app\libraries\response\JsonResponse;
use app\controllers\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use app\models\gradeable\Gradeable;
use app\models\User;
use app\models\gradeable\GradedGradeable;

class ScoreController extends AbstractController {
    #[Route("/api/score/get", methods: ["GET"])]
    public function getScore() {
        $gradeable_id = $_GET['gradeable_id'];
        $user_id = $_GET['user_id'];

        $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        $user = $this->core->getQueries()->getUserById($user_id);
        $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $user);

        $score = $graded_gradeable ? $graded_gradeable->getTotalScore() : 0;
        $max = $gradeable->getManualMaxValue();

        return JsonResponse::getSuccessResponse(['score' => $score, 'max' => $max]);
    }

    #[Route("/api/score/set", methods: ["POST"])]
    public function setScore() {
        $gradeable_id = $_POST['gradeable_id'];
        $user_id = $_POST['user_id'];
        $score = $_POST['score'];

        $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        $user = $this->core->getQueries()->getUserById($user_id);
        $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $user);

        // Assume only one component for simplicity; in real code, update the correct component
        $components = $gradeable->getComponents();
        if (count($components) > 0) {
            $component = $components[0];
            $graded_component = $graded_gradeable->getGradedComponent($component);
            $graded_component->setScore($score);
            $this->core->getQueries()->updateGradedComponent($graded_component);
        }

        return JsonResponse::getSuccessResponse(['message' => 'Score updated']);
    }
}

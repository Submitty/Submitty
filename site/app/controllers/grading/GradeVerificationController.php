<?php

namespace app\controllers\grading;

use app\libraries\GradeableType;
use app\libraries\routers\AccessControl;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\User;
use app\controllers\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class GradeVerificationController extends AbstractController {
    /**
     * Route for verifying the grader of a graded component
     * @param string $gradeable_id verify all components or not
     * @param bool $verify_all false be default
     */
    #[AccessControl(permission: "grading.electronic.verify_grader")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/components/verify", methods: ["POST"])]
    public function ajaxVerifyComponent($gradeable_id, $verify_all = false) {
        $anon_id = $_POST['anon_id'] ?? '';

        $grader = $this->core->getUser();

        // Get the gradeable
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }
        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        // Get user id from the anon id
        $submitter_id = $this->tryGetSubmitterIdFromAnonId($anon_id, $gradeable_id);
        if ($submitter_id === false) {
            return;
        }
        // Get the graded gradeable
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return;
        }
        // Get / create the TA grade
        $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();
        if (!$verify_all) {
            $component_id = $_POST['component_id'] ?? '';
            // get the component
            $component = $this->tryGetComponent($gradeable, $component_id);
            if ($component === false) {
                return;
            }

            // Get / create the graded component
            $graded_component = $ta_graded_gradeable->getOrCreateGradedComponent($component, $grader, false);

            // Verifying individual component should fail if its ungraded
            if ($graded_component === null) {
                $this->core->getOutput()->renderJsonFail('Cannot verify ungraded component');
                return;
            }
        }
        try {
            if ($verify_all === 'true') {
                foreach ($gradeable->getComponents() as $comp) {
                    if (!$comp->isPeerComponent()) {
                        $graded_component = $ta_graded_gradeable->getGradedComponent($comp);
                        if ($graded_component !== null && $graded_component->getGraderId() != $grader->getId()) {
                            $graded_component->setVerifier($grader);
                            $graded_component->setVerifyTime($this->core->getDateTimeNow());
                        }
                    }
                }
            }
            else {
                if (!isset($graded_component)) {
                    throw new \RuntimeException('Graded component should not be null if $verify_all === false');
                }
                $graded_component->setVerifier($grader);
                $graded_component->setVerifyTime($this->core->getDateTimeNow());
            }
            $this->core->getQueries()->saveTaGradedGradeable($ta_graded_gradeable);
            $this->core->getOutput()->renderJsonSuccess();
        }
        catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        }
        catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }
}

<?php

namespace app\controllers\grading;

use app\libraries\GradeableType;
use app\libraries\routers\AccessControl;
use app\models\gradeable\Gradeable;
use app\models\GradingOrder;
use app\libraries\response\JsonResponse;
use app\controllers\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class PeerGradingController extends AbstractController {
    /**
     * Helper function for Randomization
     * @param Array $student_array
     * @param int $number_to_grade
     * @return Array $final_grading_info
     */
    private function setRandomizedGraders(array $student_array, int $number_to_grade) {
        $final_grading_info = [];
        $graded_array = $student_array;
        /*n_array_peers : An Array of arrays that holds information on to be graded peers
        [ [A,B,C,D,E,F], [E,F,A,B,C,D], [C,D,E,F,A,B] ]
        A grades C and E and is graded by C and E.
        */
        $n_array_peers = [];
        shuffle($student_array);
        array_push($n_array_peers, $student_array);
        /*final_grading_info : An Array with clear structure of grading rules for peer grading
        [ [A,[C,E]],[B,[F,D]], ...]
        A grades C and E, B grades F and D ..and so on!
        */
        $max_offset = count($student_array);
        $offset_array = [];
        $temp_offset = [];
        for ($i = 1; $i < $max_offset; ++$i) {
            array_push($temp_offset, $i);
        }
        /* $offset_array contains randomly chosen offsets.
        $temp_offset helps to ensure no duplicate offsets exist (By removing already chosen offsets)
        Upon every random choice of an offset from $temp_offset, the value is removed from it.
        */
        for ($i = 0; $i < $number_to_grade; ++$i) {
            $random_offset = array_rand($temp_offset, 1);
            array_push($offset_array, $temp_offset[$random_offset]);
            unset($temp_offset[$random_offset]);
        }
        foreach ($offset_array as $element) {
            $temp_array = $student_array;
            for ($i = 0; $i < $element; $i++) {
                array_push($temp_array, array_shift($temp_array));
            }
            array_push($n_array_peers, $temp_array);
        }
        for ($i = 0; $i < count($n_array_peers[0]); ++$i) {
            $temp = [];
            for ($j = 1; $j < count($n_array_peers); ++$j) {
                array_push($temp, $n_array_peers[$j][$i]);
            }
            array_push($final_grading_info, [$n_array_peers[0][$i],$temp]);
        }
        return $final_grading_info;
    }

    /**
     * Helper function for all grade all in randomized peer assignments
     * @param Array $student_array
     * @return Array $final_grading_info
     */
    private function setAllGradAllGrading($student_array) {
        $final_grading_info = [];
        for ($grader = 0; $grader < count($student_array); ++$grader) {
            $peer_array = $student_array;
            unset($peer_array[$grader]);
            $peer_array = array_values($peer_array);
            array_push($final_grading_info, [$student_array[$grader],$peer_array]);
        }
        return $final_grading_info;
    }

    /**
     * Route for randomizing peer assignments with 'One Grades Many'
     */
    #[AccessControl(role: "INSTRUCTOR")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/RandomizePeers", methods: ["POST"])]
    public function RandomizePeers($gradeable_id) {
        /* How does this function work?
        1 - Number of students to grade (Y) is taken from the client using POST
            1.1 - If the number is > number of students, then ALL grade ALL.
        2 - Query DB to get students from registration section(X) (Without taking in students in NULL section)
        3 - Randomize the order of students
        4 - Randomly Select Y offsets
        5 - Shift the random order by the offsets to create the matrix, with no duplicates, and exactly Y assignments and & graders for each student.  no student grades self.
        */
        $restrict_to_registration = ($_POST['restrict_to_registration'] ?? '') === "checked";
        $submit_before_grading = ($_POST['submit_before_grading'] ?? '') === "checked";
        $number_to_grade = $_POST['number_to_grade'] ?? '';
        if (!is_numeric($number_to_grade)) {
            $this->core->addErrorMessage("Peer assignment failed: An invalid number of students to grade was assigned");
            return JsonResponse::getFailResponse("Number of students to grade expects an integer");
        }
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            $this->core->addErrorMessage('Invalid Gradeable!');
            return JsonResponse::getFailResponse("Invalid gradeable ID");
        }
        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->addErrorMessage('This gradeable is not an electronic file gradeable');
            return JsonResponse::getFailResponse('This gradeable is not an electronic file gradeable');
        }
        /* If Restrict to Registration checkbox is checked, then the randomised peer assignments should be restricted to each registration section" */
        if ($restrict_to_registration) {
            $sections = $this->core->getQueries()->getRegistrationSections();
            foreach ($sections as $i => $section) {
                $sections[$i] = $section['sections_registration_id'];
            }
            /* Duplicating the entire functionality of randomization, but for each registration section rather than whole class" */
            $final_grading_info = [];
            foreach ($sections as $section) {
                $student_list = [];
                $all_grade_all_registration = false;
                $student_array = [];
                $students = $this->core->getQueries()->getUsersByRegistrationSections([$section]);
                foreach ($students as $student) {
                    array_push($student_list, ['user_id' => $student->getId()]);
                    if ($submit_before_grading) {
                        if ($this->core->getQueries()->getUserHasSubmission($gradeable, $student->getId())) {
                            array_push($student_array, $student->getId());
                        }
                    }
                    else {
                        array_push($student_array, $student->getId());
                    }
                }

                $number_of_students = count($student_array);
                /* If number of students entered is more than number of students in registration section,
                then for each registration section with less number of students, everyone will grade everyone */
                if ($number_to_grade >= $number_of_students) {
                    $all_grade_all_registration = true;
                }
                if ($all_grade_all_registration) {
                    for ($grader = 0; $grader < count($student_array); ++$grader) {
                        $peer_array = $student_array;
                        unset($peer_array[$grader]);
                        $peer_array = array_values($peer_array);
                        array_push($final_grading_info, [$student_array[$grader],$peer_array]);
                    }
                }
                else {
                    $final_grading_info = $this->setRandomizedGraders($student_array, $number_to_grade);
                }
            }
            $gradeable->setRandomPeerGradersList($final_grading_info);
            return JsonResponse::getSuccessResponse($final_grading_info);
        }

        $all_grade_all = false;
        $order = new GradingOrder($this->core, $gradeable, $this->core->getUser(), true);
        $student_array = [];
        $student_list = [];
        $students = $this->core->getQueries()->getUsersByRegistrationSections($order->getSectionNames());
        foreach ($students as $student) {
            $reg_sec = ($student->getRegistrationSection() === null) ? 'NULL' : $student->getRegistrationSection();
            $sorted_students[$reg_sec][] = $student;
            array_push($student_list, ['user_id' => $student->getId()]);
            if ($submit_before_grading) {
                if ($this->core->getQueries()->getUserHasSubmission($gradeable, $student->getId())) {
                    array_push($student_array, $student->getId());
                }
            }
            else {
                array_push($student_array, $student->getId());
            }
        }
        $number_of_students = count($student_array);
        if ($number_of_students <= 1) {
            $this->core->addErrorMessage("Peer assignments failed: Not enough submissions");
            return JsonResponse::getFailResponse("Not Enough Submissions");
        }
        if ($number_to_grade > $number_of_students) {
            $all_grade_all = true;
        }
        if ($all_grade_all) {
            $final_grading_info = $this->setAllGradAllGrading($student_array);
            $gradeable->setRandomPeerGradersList($final_grading_info);
            return JsonResponse::getSuccessResponse($final_grading_info);
        }
        $final_grading_info = $this->setRandomizedGraders($student_array, $number_to_grade);
        if ($number_to_grade < 1) {
            $gradeable->setRandomPeerGradersList($final_grading_info);
            return JsonResponse::getSuccessResponse("Clear Peer Matrix");
        }
        $gradeable->setRandomPeerGradersList($final_grading_info);
        $gradeable->setPeerGradingSet($number_to_grade);
        return JsonResponse::getSuccessResponse($final_grading_info);
    }

    /**
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/feedback/set", methods: ["POST"])]
    public function ajaxSetPeerFeedback($gradeable_id) {
        $grader_id = $_POST['grader_id'] ?? '';
        $user_id = $_POST['user_id'] ?? '';
        $feedback = $_POST['feedback'];
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return null;
        }
        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonError("This gradeable is not an electronic file gradeable");
            return null;
        }
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id)->getGradeableId() == $gradeable_id;
        if ($graded_gradeable === false) {
            return null;
        }
        if ($gradeable->setPeerFeedback($this->core->getQueries()->getUserFromAnon($grader_id, $gradeable_id)[$grader_id], $user_id, $feedback)) {
            $this->core->getOutput()->renderJsonSuccess("Feedback successfully uploaded");
        }
        else {
            $this->core->getOutput()->renderJsonError("Failed to save feedback");
        }
        return true;
    }

    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/clear_peer_marks", methods: ["POST"])]
    public function ajaxClearPeerMarks($gradeable_id) {
        $submitter_id = $_POST['submitter_id'] ?? '';
        $peer_id = $_POST['peer_id'] ?? '';
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            $this->core->getOutput()->renderJsonFail('Could not fetch gradeable');
            return;
        }
        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            $this->core->getOutput()->renderJsonFail('This gradeable is not an electronic file gradeable');
            return;
        }
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            $this->core->getOutput()->renderJsonFail('Could not fetch graded gradeable');
            return;
        }
        $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();
        foreach ($ta_graded_gradeable->getGradedComponentContainers() as $container) {
            $component = $container->getComponent();
            $ta_graded_gradeable->deleteGradedComponent($component, $this->core->getQueries()->getUserById($peer_id));
        }
        $ta_graded_gradeable->removeOverallComment($peer_id);
        $this->core->getQueries()->deleteOverallComment($gradeable_id, $peer_id, $graded_gradeable->getSubmitter()->getId());
        $this->core->getQueries()->deleteTaGradedGradeableByIds($gradeable_id, $peer_id);
        $ta_graded_gradeable->resetUserViewedDate();

        // Finally, save the graded gradeable
        $this->core->getQueries()->saveTaGradedGradeable($ta_graded_gradeable);
        $submitter = $ta_graded_gradeable->getGradedGradeable()->getSubmitter();
        if ($submitter->isTeam()) {
            $this->core->getQueries()->clearTeamViewedTime($submitter->getId());
        }
        $this->core->getOutput()->renderJsonSuccess("Marks removed successfully!");
        return true;
    }
}

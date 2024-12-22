<?php

namespace app\controllers\grading;

use app\libraries\GradeableType;
use app\libraries\response\RedirectResponse;
use app\libraries\response\ResponseInterface;
use app\models\gradeable\GradedGradeable;
use app\models\User;
use app\controllers\AbstractController;
use app\libraries\Utils;
use app\libraries\routers\AccessControl;
use app\libraries\response\JsonResponse;
use app\libraries\response\WebResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SimpleGraderController
 * @package app\controllers\grading
 * @AccessControl(permission="grading.simple")
 */
class SimpleGraderController extends AbstractController {
    /**
     * @param string $gradeable_id
     * @param int|string|null $section
     * @param string|null $section_type
     * @param string $sort
     *
     * @return ResponseInterface
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/print", methods:["GET"])]
    public function printLab($gradeable_id, $section = null, $section_type = null, $sort = "id") {
        //convert from id --> u.user_id etc for use by the database.
        if ($sort === "id") {
            $sort_by = "u.user_id";
        }
        elseif ($sort === "first") {
            $sort_by = "coalesce(NULLIF(u.user_preferred_givenname, ''), u.user_givenname)";
        }
        else {
            $sort_by = "coalesce(NULLIF(u.user_preferred_familyname, ''), u.user_familyname)";
        }

        //Figure out what section we are supposed to print
        if (is_null($section)) {
            $this->core->addErrorMessage("ERROR: Section not set; You did not select a section to print.");
            return new RedirectResponse($this->core->buildCourseUrl());
        }

        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        }
        catch (\InvalidArgumentException $e) {
            return new WebResponse('Error', 'noGradeable', $gradeable_id);
        }

        // Make sure this gradeable is an electronic file gradeable
        if ($gradeable->getType() !== GradeableType::NUMERIC_TEXT && $gradeable->getType() !== GradeableType::CHECKPOINTS) {
            $this->core->addErrorMessage('This gradeable is not a checkpoint or numeric text gradeable');
            return new RedirectResponse($this->core->buildCourseUrl());
        }

        if (!$this->core->getAccess()->canI("grading.simple.grade", ["gradeable" => $gradeable, "section" => $section])) {
            $this->core->addErrorMessage("ERROR: You do not have access to grade this section.");
            return new RedirectResponse($this->core->buildCourseUrl());
        }

        //Figure out if we are getting users by rotating or registration section.
        if (is_null($section_type)) {
            return new WebResponse('Error', 'genericError', ['Got null section type']);
        }

        //Grab the students in section, sectiontype.
        if ($section_type === "rotating_section") {
            $students = $this->core->getQueries()->getUsersByRotatingSections([$section], $sort_by);
        }
        elseif ($section_type === "registration_section") {
            $students = $this->core->getQueries()->getUsersByRegistrationSections([$section], $sort_by);
        }
        else {
            $this->core->addErrorMessage("ERROR: You did not select a valid section type to print.");
            return new RedirectResponse($this->core->buildCourseUrl());
        }

        //Turn off header/footer so that we are using simple html.
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        //display the lab to be printed (in SimpleGraderView's displayPrintLab function)
        return new WebResponse(
            ['grading', 'SimpleGrader'],
            'displayPrintLab',
            $gradeable,
            $section,
            $students
        );
    }

    /**
     * @param string $gradeable_id
     * @param null|string $view
     * @param string $sort
     *
     * @return ResponseInterface
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading", methods: ["GET"])]
    public function gradePage($gradeable_id, $view = null, $sort = "section_subsection") {
        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        }
        catch (\InvalidArgumentException $e) {
            return new WebResponse('Error', 'noGradeable');
        }

        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            return new RedirectResponse($this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'details']));
        }

        //If you can see the page, you can grade the page
        if (!$this->core->getAccess()->canI("grading.simple.grade", ["gradeable" => $gradeable])) {
            $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getTitle()}");
            return new RedirectResponse($this->core->buildCourseUrl());
        }

        // sort makes sorting remain when clicking print lab or view all
        if ($sort === "id") {
            $sort_key = "u.user_id";
        }
        elseif ($sort === "first") {
            $sort_key = "coalesce(NULLIF(u.user_preferred_givenname, ''), u.user_givenname)";
        }
        elseif ($sort === "last") {
            $sort_key = "coalesce(NULLIF(u.user_preferred_familyname, ''), u.user_familyname)";
        }
        else {
            $sort_key = "u.registration_subsection";
        }

        if ($gradeable->isGradeByRegistration()) {
            $grading_count = count($this->core->getUser()->getGradingRegistrationSections());
        }
        else {
            $grading_count = count($this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable->getId(), $this->core->getUser()->getId()));
        }
        //Can you show all
        $can_show_all = $this->core->getAccess()->canI("grading.simple.show_all");
        //Are you currently showing all
        $show_all = ($view === 'all' || $grading_count === 0) && $can_show_all;
        //Should the button be shown
        $show_all_sections_button = $can_show_all;

        //Checks to see if the Grader has access to all users in the course,
        //Will only show the sections that they are graders for if not TA or Instructor
        if ($show_all) {
            $sections = $gradeable->getAllGradingSections();
        }
        else {
            $sections = $gradeable->getGradingSectionsForUser($this->core->getUser());
        }

        $students = [];
        foreach ($sections as $section) {
            $students = array_merge($students, $section->getUsers());
        }
        $student_ids = array_map(function (User $user) {
            return $user->getId();
        }, $students);

        $student_full = Utils::getAutoFillData($students);

        if ($gradeable->isGradeByRegistration()) {
            $section_key = "registration_section";
        }
        else {
            $section_key = "rotating_section";
        }

        $graders = [];
        foreach ($sections as $section) {
            $graders[$section->getName()] = $section->getGraders();
        }

        $rawAnonIds = $this->core->getQueries()->getAllAnonIdsByGradeableWithUserIds($gradeable->getId());
        $anon_ids = [];
        foreach ($rawAnonIds as $anon) {
            $anon_ids[$anon['user_id']] = $anon['anon_id'];
        }

        $rows = $this->core->getQueries()->getGradedGradeables([$gradeable], $student_ids, null, [$section_key, $sort_key, "u.user_id"]);
        return new WebResponse(
            ['grading', 'SimpleGrader'],
            'simpleDisplay',
            $gradeable,
            $rows,
            $student_full,
            $graders,
            $section_key,
            $show_all_sections_button,
            $sort,
            $anon_ids
        );
    }

    /**
     * @param string $gradeable_id
     *
     * @return ResponseInterface
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading", methods: ["POST"])]
    public function save($gradeable_id) {
        if (!isset($_POST['user_id'])) {
            return JsonResponse::getFailResponse('Did not pass in user_id');
        }
        $user_id = $_POST['user_id'];

        $grader = $this->core->getUser();
        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        }
        catch (\InvalidArgumentException $e) {
            return JsonResponse::getFailResponse("Invalid gradeable ID");
        }

        $user = $this->core->getQueries()->getUserById($user_id);
        if ($gradeable->getType() !== GradeableType::NUMERIC_TEXT && $gradeable->getType() !== GradeableType::CHECKPOINTS) {
            return JsonResponse::getFailResponse('This gradeable is not a checkpoint or numeric text gradeable');
        }
        elseif ($user === null) {
            return JsonResponse::getFailResponse("Invalid user ID");
        }
        elseif (!isset($_POST['scores']) || empty($_POST['scores'])) {
            return JsonResponse::getFailResponse("Didn't submit any scores");
        }

        $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $user_id, null);

        //Make sure they're allowed to do this
        if (!$this->core->getAccess()->canI("grading.simple.grade", ["graded_gradeable" => $graded_gradeable])) {
            return JsonResponse::getFailResponse("You do not have permission to do this.");
        }

        $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();

        // Return ids and scores of updated components in success response so frontend can validate
        $return_data = [];

        foreach ($gradeable->getComponents() as $component) {
            $data = $_POST['scores'][$component->getId()] ?? '';
            $original_data = $_POST['old_scores'][$component->getId()] ?? '';

            $component_grade = $ta_graded_gradeable->getOrCreateGradedComponent($component, $grader, true);
            $component_grade->setGrader($grader);

            if ($component->isText()) {
                $component_grade->setComment($data);
            }
            else {
                // This catches both the not-set and blank-data case for numeric cells
                if ($data !== '') {
                    if (
                        !is_numeric($data)
                        || $data < 0
                    ) {
                        return JsonResponse::getFailResponse("Save error: score must be a positive number");
                    }
                    if ($component->getUpperClamp() < $data) {
                        return JsonResponse::getFailResponse("Save error: score must be a number less than the upper clamp");
                    }
                    $db_data = $component_grade->getTotalScore();
                    if ($original_data != $db_data) {
                        return JsonResponse::getFailResponse("Save error: displayed stale data (" . $original_data . ") does not match database (" . $db_data . ")");
                    }
                    $component_grade->setScore($data);
                }
                else {
                    continue;
                }
            }
            $component_grade->setGradeTime($this->core->getDateTimeNow());
            $return_data[$component->getId()] = $data;
        }

        $this->core->getQueries()->saveTaGradedGradeable($ta_graded_gradeable);

        $return_data['date'] = $this->core->getDateTimeNow()->format('c');

        return JsonResponse::getSuccessResponse($return_data);
    }

    /**
     * @param string $gradeable_id
     *
     * @return ResponseInterface
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/csv", methods: ["POST"])]
    public function UploadCSV($gradeable_id) {
        $users = $_POST['users'];

        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        }
        catch (\InvalidArgumentException $e) {
            return JsonResponse::getFailResponse("Invalid gradeable ID");
        }

        if ($gradeable->getType() !== GradeableType::NUMERIC_TEXT && $gradeable->getType() !== GradeableType::CHECKPOINTS) {
            return JsonResponse::getFailResponse('This gradeable is not a checkpoint or numeric text gradeable');
        }
        $grader = $this->core->getUser();

        if (!$this->core->getAccess()->canI("grading.simple.upload_csv", ["gradeable" => $gradeable])) {
            return JsonResponse::getFailResponse("You do not have permission to grade {$gradeable->getTitle()}");
        }

        $num_numeric = intval($_POST['num_numeric']);

        $csv_array = preg_split("/\r\n|\n|\r/", $_POST['big_file']);
        $arr_length = count($csv_array);
        $return_data = [];

        $data_array = [];
        for ($i = 0; $i < $arr_length; $i++) {
            $temp_array = explode(',', $csv_array[$i]);
            $data_array[] = $temp_array;
        }

        /** @var GradedGradeable $graded_gradeable */
        foreach ($this->core->getQueries()->getGradedGradeables([$gradeable], $users, null) as $graded_gradeable) {
            for ($j = 0; $j < $arr_length; $j++) {
                $username = $graded_gradeable->getSubmitter()->getId();
                if ($username !== $data_array[$j][0]) {
                    continue;
                }

                $temp_array = [];
                $temp_array['username'] = $username;
                $index1 = 0;
                $index2 = 3; //3 is the starting index of the grades in the csv

                // Get the user grade for this gradeable
                $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();

                //Makes an array with all the values and their status.
                foreach ($gradeable->getComponents() as $component) {
                    $component_grade = $ta_graded_gradeable->getOrCreateGradedComponent($component, $grader, true);
                    $component_grade->setGrader($grader);

                    $value_temp_str = "value_" . $index1;
                    $status_temp_str = "status_" . $index1;
                    if (isset($data_array[$j][$index2])) {
                        $component_data = $data_array[$j][$index2];
                        // text component
                        if ($component->isText()) {
                            $component_grade->setComment($component_data);
                            $component_grade->setGradeTime($this->core->getDateTimeNow());
                            $temp_array[$value_temp_str] = $component_data;
                            $temp_array[$status_temp_str] = "OK";
                        }
                        else {
                            // numeric component
                            // if the data is empty, we should just input 0. If it is not a number, we should fail.
                            if ($component_data !== '' && !is_numeric($component_data)) {
                                $temp_array[$value_temp_str] = $component_data;
                                $temp_array[$status_temp_str] = "ERROR";
                            }
                            else {
                                $component_data = floatval($component_data);
                                if ($component->getUpperClamp() < $component_data) {
                                    $temp_array[$value_temp_str] = $component_data;
                                    $temp_array[$status_temp_str] = "ERROR";
                                }
                                else {
                                    $component_grade->setScore($component_data);
                                    $component_grade->setGradeTime($this->core->getDateTimeNow());
                                    $temp_array[$value_temp_str] = $component_data;
                                    $temp_array[$status_temp_str] = "OK";
                                }
                            }
                        }
                    }
                    $index1++;
                    $index2++;
                    //skips the index of the total points in the csv file
                    if ($index1 === $num_numeric) {
                        $index2++;
                    }
                }

                // Reset the overall comment because we're overwriting the grade anyway
                $this->core->getQueries()->saveTaGradedGradeable($ta_graded_gradeable);

                $return_data[] = $temp_array;
                $j = $arr_length; //stops the for loop early to not waste resources
            }
        }

        return JsonResponse::getSuccessResponse($return_data);
    }
}

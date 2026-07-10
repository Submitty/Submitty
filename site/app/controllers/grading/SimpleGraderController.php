<?php

namespace app\controllers\grading;

use app\libraries\GradeableType;
use app\libraries\response\RedirectResponse;
use app\libraries\response\ResponseInterface;
use app\models\User;
use app\controllers\AbstractController;
use app\libraries\Utils;
use app\libraries\routers\AccessControl;
use app\libraries\response\JsonResponse;
use app\libraries\response\WebResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\socket\Client;
use app\libraries\response\DownloadResponse;
use WebSocket;

/**
 * Class SimpleGraderController
 * @package app\controllers\grading
 */
#[AccessControl(permission: "grading.simple")]
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
            $sort_by = "coalesce(u.user_preferred_givenname, u.user_givenname)";
        }
        else {
            $sort_by = "coalesce(u.user_preferred_familyname, u.user_familyname)";
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

        $this->core->authorizeWebSocketToken([
            'page' => 'grading',
            'gradeable_id' => $gradeable_id,
        ]);

        // sort makes sorting remain when clicking print lab or view all
        if ($sort === "id") {
            $sort_key = "u.user_id";
        }
        elseif ($sort === "first") {
            $sort_key = "coalesce(u.user_preferred_givenname, u.user_givenname)";
        }
        elseif ($sort === "last") {
            $sort_key = "coalesce(u.user_preferred_familyname, u.user_familyname)";
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

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/csv", methods:["GET"])]
    public function downloadNumericCsv(string $gradeable_id, string $sort = "section_subsection"): ResponseInterface {
        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        }
        catch (\InvalidArgumentException $e) {
            return new WebResponse('Error', 'noGradeable', $gradeable_id);
        }

        if ($gradeable->getType() !== GradeableType::NUMERIC_TEXT) {
            $this->core->addErrorMessage('This gradeable is not a numeric text gradeable');
            return new RedirectResponse($this->core->buildCourseUrl());
        }

        //If you can see the page, you can grade the page
        if (!$this->core->getAccess()->canI("grading.simple.grade", ["gradeable" => $gradeable])) {
            $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getTitle()}");
            return new RedirectResponse($this->core->buildCourseUrl());
        }
        if ($gradeable->isGradeByRegistration()) {
            $grading_count = count($this->core->getUser()->getGradingRegistrationSections());
        }
        else {
            $grading_count = count(
                $this->core->getQueries()->getRotatingSectionsForGradeableAndUser(
                    $gradeable->getId(),
                    $this->core->getUser()->getId()
                )
            );
        }

        $can_show_all = $this->core->getAccess()->canI("grading.simple.show_all");
        $show_all = $grading_count === 0 && $can_show_all;

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

        if ($gradeable->isGradeByRegistration()) {
            $section_key = "registration_section";
        }
        else {
            $section_key = "rotating_section";
        }
        //Sort the page:
        if ($sort === "id") {
            $sort_key = "u.user_id";
        }
        elseif ($sort === "first") {
            $sort_key = "coalesce(u.user_preferred_givenname, u.user_givenname)";
        }
        elseif ($sort === "last") {
            $sort_key = "coalesce(u.user_preferred_familyname, u.user_familyname)";
        }
        else {
            $sort_key = "u.registration_subsection";
        }

        $fp = fopen('php://temp', 'r+');

        $numeric_components = [];
        $text_components = [];

        foreach ($gradeable->getComponents() as $component) {
            if ($component->isText()) {
                $text_components[] = $component;
            }
            else {
                $numeric_components[] = $component;
            }
        }

        $header = [
            "User ID",
            "Given Name",
            "Family Name",
        ];

        foreach ($numeric_components as $component) {
            $header[] = $component->getTitle();
        }

        $header[] = "Total";

        foreach ($text_components as $component) {
            $header[] = $component->getTitle();
        }

        fputcsv($fp, $header);

        $rows = $this->core->getQueries()->getGradedGradeables(
            [$gradeable],
            $student_ids,
            null,
            [$section_key, $sort_key, "u.user_id"]
        );
        foreach ($rows as $row) {
            $user = $row->getSubmitter()->getUser();
            $csv_row = [
                $user->getId(),
                $user->getDisplayedGivenName(),
                $user->getDisplayedFamilyName(),
            ];

            $ta_grade = $row->getTaGradedGradeable();
            $total = 0;
            foreach ($numeric_components as $component) {
                $component_grade = $ta_grade !== null ? $ta_grade->getGradedComponent($component) : null;
                $component_score = $component_grade !== null ? $component_grade->getScore() : 0;
                $csv_row[] = $component_score;
                $total += $component_score;
            }

            $csv_row[] = $total;
            foreach ($text_components as $component) {
                $component_grade = $ta_grade !== null ? $ta_grade->getGradedComponent($component) : null;
                $csv_row[] = $component_grade !== null ? $component_grade->getComment() : "";
            }

            fputcsv($fp, $csv_row);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return DownloadResponse::getDownloadResponse(
            $csv,
            "{$gradeable_id}.csv",
            "application/csv"
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
        $anon_id = $_POST['anon_id'] ?? $user_id;

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
        $elem = isset($_POST['elem']) ? (int) $_POST['elem'] : null;
        $total = 0;
        $value = null;

        foreach ($gradeable->getComponents() as $index => $component) {
            if (!array_key_exists($component->getId(), $_POST['scores'])) {
                continue;
            }
            $data = $_POST['scores'][$component->getId()];
            if (!array_key_exists($component->getId(), $_POST['old_scores'])) {
                return JsonResponse::getFailResponse("Save error: old score data missing");
            }
            $original_data = $_POST['old_scores'][$component->getId()];
            $removing = $data === '' || (!$component->isText() && $data === '0');
            $time = $this->core->getDateTimeNow();

            if ($gradeable->getType() === GradeableType::CHECKPOINTS) {
                // Send websocket message for each checkpoint update
                $this->sendSocketMessage([
                    'type' => 'update_checkpoint',
                    'g_id' => $gradeable_id,
                    'user' => $anon_id,
                    'grader' => $removing ? "" : $grader->getId(),
                    'elem' => (string) $index,
                    'value' => $data,
                    'is_text' => $component->isText(),
                    'date' => $removing ? "" : $time->format('Y-m-d H:i:s')
                ]);
            }
            elseif ($index === $elem) {
                // Store the value of the updating component for the websocket message
                $value = $data;
            }

            if ($removing) {
                $ta_graded_gradeable->deleteGradedComponent($component);
                continue;
            }
            else {
                $component_grade = $ta_graded_gradeable->getOrCreateGradedComponent($component, $grader, true);
                $component_grade->setGrader($grader);
            }
            if ($component->isText()) {
                $component_grade->setComment($data);
            }
            else {
                // Numeric case
                if (!is_numeric($data) || $data < 0) {
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
                $total += $data;
            }

            $component_grade->setGradeTime($time);
            $return_data[$component->getId()] = $data;
        }

        $this->core->getQueries()->saveTaGradedGradeable($ta_graded_gradeable);

        $return_data['date'] = $this->core->getDateTimeNow()->format('Y-m-d H:i:s');

        if ($gradeable->getType() === GradeableType::NUMERIC_TEXT) {
            $this->sendSocketMessage([
                'type' => 'update_numeric',
                'g_id' => $gradeable_id,
                'user' => $anon_id,
                'elem' => $_POST['elem'] ?? '',
                'value' => $value,
                'total' => (float) $total,
            ]);
        }

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

        if ($gradeable->getType() !== GradeableType::NUMERIC_TEXT) {
            return JsonResponse::getFailResponse('This gradeable is not a numeric text gradeable');
        }
        $grader = $this->core->getUser();

        if (!$this->core->getAccess()->canI("grading.simple.upload_csv", ["gradeable" => $gradeable])) {
            return JsonResponse::getFailResponse("You do not have permission to grade {$gradeable->getTitle()}");
        }

        $csv_array = preg_split("/\r\n|\n|\r/", trim($_POST['big_file']));
        $arr_length = count($csv_array);
        $return_data = [];

        $data_array = [];
        for ($i = 0; $i < $arr_length; $i++) {
            $data_array[] = str_getcsv($csv_array[$i]);
        }

        if ($arr_length < 1) {
            $msg = "CSV file is empty.";
            $this->core->addErrorMessage($msg);
            return JsonResponse::getFailResponse($msg);
        }

        $column_titles = array_map('trim', $data_array[0]);
        if (!in_array('User ID', $column_titles, true)) {
            $msg = "CSV must include a header row with a \"User ID\" column.";
            $this->core->addErrorMessage($msg);
            return JsonResponse::getFailResponse($msg);
        }

        $col_index = array_flip($column_titles);

        $numeric_components = [];
        $text_components = [];
        foreach ($gradeable->getComponents() as $component) {
            if ($component->isText()) {
                $text_components[] = $component;
            }
            else {
                $numeric_components[] = $component;
            }
        }

        $present_numeric = [];
        foreach ($numeric_components as $component) {
            if (isset($col_index[$component->getTitle()])) {
                $present_numeric[] = $component;
            }
        }
        $present_text = [];
        foreach ($text_components as $component) {
            if (isset($col_index[$component->getTitle()])) {
                $present_text[] = $component;
            }
        }

        if (count($present_numeric) === 0 && count($present_text) === 0) {
            $msg = "CSV must include at least one recognized question column to update.";
            $this->core->addErrorMessage($msg);
            return JsonResponse::getFailResponse($msg);
        }


        for ($row_num = 1; $row_num < $arr_length; $row_num++) {
            $row = $data_array[$row_num];
            if (count($row) === 1 && trim($row[0]) === '') {
                continue;
            }

            $user_id = $row[$col_index['User ID']] ?? null;
            if ($user_id === null || trim($user_id) === '') {
                $msg = "Row " . ($row_num + 1) . ", Column \"User ID\" is required but was empty.";
                $this->core->addErrorMessage($msg);
                return JsonResponse::getFailResponse($msg);
            }

            $total = 0;
            $any_numeric_present_in_row = false;
            foreach ($present_numeric as $component) {
                $idx = $col_index[$component->getTitle()];
                $val = $row[$idx] ?? '';
                if ($val === '') {
                    continue;
                }
                if (!is_numeric($val)) {
                    $msg = "Row " . ($row_num + 1) . ", Column \"" . $component->getTitle() . "\" (column " . ($idx + 1) . ") should be a number. Found \"{$val}\".";
                    $this->core->addErrorMessage($msg);
                    return JsonResponse::getFailResponse($msg);
                }
                $total += floatval($val);
                $any_numeric_present_in_row = true;
            }
        }

        foreach ($this->core->getQueries()->getGradedGradeables([$gradeable], $users, null) as $graded_gradeable) {
            $username = $graded_gradeable->getSubmitter()->getId();
            $matched_row = null;
            for ($j = 1; $j < $arr_length; $j++) {
                if (($data_array[$j][$col_index['User ID']] ?? null) === $username) {
                    $matched_row = $data_array[$j];
                    break;
                }
            }
            if ($matched_row === null) {
                continue;
            }

            $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();
            $temp_array = ['username' => $username];
            $index1 = 0;

            foreach ($gradeable->getComponents() as $component) {
                $value_temp_str = "value_" . $index1;
                $status_temp_str = "status_" . $index1;

                if (!isset($col_index[$component->getTitle()])) {
                    // If column isn't included in the CSV, leave the grading untouched.
                    $index1++;
                    continue;
                }

                $idx = $col_index[$component->getTitle()];
                $component_data = $matched_row[$idx] ?? '';

                if ($component_data === '') {
                    $index1++;
                    continue;
                }

                $component_grade = $ta_graded_gradeable->getOrCreateGradedComponent($component, $grader, true);
                $component_grade->setGrader($grader);

                if ($component->isText()) {
                    $component_grade->setComment($component_data);
                    $component_grade->setGradeTime($this->core->getDateTimeNow());
                    $temp_array[$value_temp_str] = $component_data;
                    $temp_array[$status_temp_str] = "OK";
                }
                else {
                    $component_data = floatval($component_data);
                    if ($component->getUpperClamp() < $component_data) {
                        $msg = "User \"{$username}\", Column \"" . $component->getTitle() . "\" exceeds the maximum value of " . $component->getUpperClamp() . ". Found \"{$component_data}\".";
                        $this->core->addErrorMessage($msg);
                        return JsonResponse::getFailResponse($msg);
                    }
                    $component_grade->setScore($component_data);
                    $component_grade->setGradeTime($this->core->getDateTimeNow());
                    $temp_array[$value_temp_str] = $component_data;
                    $temp_array[$status_temp_str] = "OK";
                }
                $index1++;
            }

            $this->core->getQueries()->saveTaGradedGradeable($ta_graded_gradeable);
            $return_data[] = $temp_array;
        }

        $updated_columns = array_merge(
            array_map(fn($c) => $c->getTitle(), $present_numeric),
            array_map(fn($c) => $c->getTitle(), $present_text)
        );
        return JsonResponse::getSuccessResponse([
            'updated_students' => $return_data,
            'updated_columns' => $updated_columns,
        ]);
    }
    /**
     * this function opens a WebSocket client and sends a message with the corresponding update
     * @param array<mixed> $msg_array
     */
    private function sendSocketMessage(array $msg_array): void {
        $msg_array['user_id'] = $this->core->getUser()->getId();
        $params = [
            'page' => 'grading',
            'term' => $this->core->getConfig()->getTerm(),
            'course' => $this->core->getConfig()->getCourse(),
            'gradeable_id' => isset($msg_array['g_id']) ? strval($msg_array['g_id']) : null,
        ];
        $msg_array['page'] = Utils::buildWebSocketPageIdentifier($params);

        try {
            $client = new Client($this->core);
            $client->json_send($msg_array);
        }
        catch (WebSocket\ConnectionException $e) {
            $this->core->addNoticeMessage("WebSocket Server is down, page won't load dynamically.");
        }
    }
}

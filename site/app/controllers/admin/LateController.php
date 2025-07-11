<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\DateUtils;
use app\libraries\routers\AccessControl;
use app\libraries\response\RedirectResponse;
use app\libraries\response\MultiResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\WebResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\models\gradeable\LateDays;

/**
 * Class LateController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 */
class LateController extends AbstractController {
    /**
     * @return WebResponse
     */
    #[Route("/courses/{_semester}/{_course}/late_days")]
    public function viewLateDays() {
        return new WebResponse(
            ['admin', 'LateDay'],
            'displayLateDays',
            $this->core->getQueries()->getUsersWithLateDays(),
            $this->core->getQueries()->getAllUsers(),
            $this->core->getConfig()->getDefaultStudentLateDays()
        );
    }

    /**
     * @return WebResponse
     */
    #[Route("/courses/{_semester}/{_course}/extensions")]
    public function viewExtensions() {
        return new WebResponse(
            ['admin', 'Extensions'],
            'displayExtensions',
            $this->core->getQueries()->getAllElectronicGradeablesIds()
        );
    }

    /**
     * @return WebResponse
     */
    #[Route("/courses/{_semester}/{_course}/bulk_late_days")]
    public function viewLateDayCache() {
        return new WebResponse(
            ['admin', 'LateDay'],
            'displayLateDayCache',
            $this->core->getQueries()->getAllUsers(),
            $this->core->getConfig()->getDefaultStudentLateDays()
        );
    }

    /**
     * @return RedirectResponse
     */
    #[Route("/courses/{_semester}/{_course}/bulk_late_days/flush")]
    public function flushLateDayCache() {
        $this->core->getQueries()->flushAllLateDayCache();
        $this->core->addSuccessMessage("Late day cache flushed!");
        return new RedirectResponse($this->core->buildCourseUrl(['bulk_late_days']));
    }

    /**
     * @return RedirectResponse
     */
    #[Route("/courses/{_semester}/{_course}/bulk_late_days/calculate")]
    public function calculateLateDayCache() {
        $this->core->getQueries()->generateLateDayCacheForUsers();
        $this->core->addSuccessMessage("Late day cache calculated!");
        return new RedirectResponse($this->core->buildCourseUrl(['bulk_late_days']));
    }

    /**
     * @param string|null $csv_option string csv_option_overwrite_all or csv_option_preserve_higher
     *
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/late_days/update", methods: ["POST"])]
    public function updateLateDays($csv_option = null) {
        if (isset($_FILES['csv_upload']) && (file_exists($_FILES['csv_upload']['tmp_name']))) {
            $data = [];
            $result = $this->parseAndValidateCsv($_FILES['csv_upload']['tmp_name'], $data, "late");
            if (!$result['success']) {
                $error = "Something is wrong with the CSV you have chosen, error: {$result['error']}. Please try again.";
                $this->core->addErrorMessage($error);
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            else {
                for ($i = 0; $i < count($data); $i++) {
                    $this->core->getQueries()->updateLateDays($data[$i][0], $data[$i][1], $data[$i][2], $csv_option);
                }
                $this->core->addSuccessMessage("Late days have been updated");
                return $this->getLateDays();
            }
        }
        else {
            $user = current($this->core->getQueries()->getUsersById([$_POST['user_id']]));
            if (!$user) {
                $error = "Invalid Student ID";
                $this->core->addErrorMessage($error);
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }

            if (!isset($_POST['datestamp']) || (\DateTime::createFromFormat('Y-m-d', $_POST['datestamp']) === false)) {
                $error = "Datestamp must be Y-m-d H:i:s";
                $this->core->addErrorMessage($error);
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            if (((!isset($_POST['late_days'])) || $_POST['late_days'] == "" || (!ctype_digit($_POST['late_days'])))) {
                $error = "Late Days must be a nonnegative integer";
                $this->core->addErrorMessage($error);
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            if ($_POST['late_days'] > 2147483647) {
                $error = "Late Days must be within the range of integer values";
                $this->core->addErrorMessage($error);
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }

            $date_time = DateUtils::parseDateTime($_POST['datestamp'], $this->core->getUser()->getUsableTimeZone());

            $this->core->getQueries()->updateLateDays($_POST['user_id'], $date_time, $_POST['late_days']);
            $this->core->addSuccessMessage("Late days have been updated");
            return $this->getLateDays();
        }
    }

    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/late_days/delete", methods: ["POST"])]
    public function deleteLateDays() {
        $user = current($this->core->getQueries()->getUsersById([$_POST['user_id']]));
        if (!$user) {
            $error = "Invalid Student ID";
            $this->core->addErrorMessage($error);

            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse($error)
            );
        }
        if (!isset($_POST['datestamp'])) {
            $error = "Deleting late days requires a valid date (mm/dd/yy)";
            $this->core->addErrorMessage($error);

            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse($error)
            );
        }
        $this->core->getQueries()->deleteLateDays($_POST['user_id'], $_POST['datestamp']);
        $this->core->addSuccessMessage("Late days entry removed");

        return $this->getLateDays();
    }

    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/extensions/update", methods: ["POST"])]
    public function updateExtension() {
        if (isset($_FILES['csv_upload']) && (file_exists($_FILES['csv_upload']['tmp_name']))) {
            $data = [];
            $result = $this->parseAndValidateCsv($_FILES['csv_upload']['tmp_name'], $data, "extension");
            if (!$result['success']) {
                $error = "Something is wrong with the CSV you have chosen, error: {$result['error']}. Please try again.";
                $this->core->addErrorMessage($error);
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            else {
                for ($i = 0; $i < count($data); $i++) {
                    $this->core->getQueries()->updateExtensions($data[$i][0], $data[$i][1], $data[$i][2], $data[$i][3]);
                }
                return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse());
            }
        }
        else {
            if ((!isset($_POST['g_id']) || $_POST['g_id'] == "" )) {
                $error = "Please choose a gradeable_id";
                $this->core->addErrorMessage($error);
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            $user = current($this->core->getQueries()->getUsersById([$_POST['user_id']]));
            if (!$user) {
                $error = "Invalid Student ID";
                $this->core->addErrorMessage($error);
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            $late_days = null;
            if (isset($_POST['late_days'])) {
                $late_days = $_POST['late_days'];
                if (intval($late_days) < 0 || !ctype_digit($late_days)) {
                    $error = "Extensions must be a nonnegative integer";
                    $this->core->addErrorMessage($error);
                    return MultiResponse::JsonOnlyResponse(
                        JsonResponse::getFailResponse($error)
                    );
                }
            }
            else {
                $error = "You must specify a number of late days or a new due date for the student";
                $this->core->addErrorMessage($error);
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            $reason_for_exception = $_POST['reason_for_exception'] ?? 'unspecified';
            $users_with_exceptions = $this->core->getQueries()->getUsersWithExtensions($_POST['g_id']);
            $simple_late_user = null;
            $no_change = false;
            if (!$no_change) {
                foreach ($users_with_exceptions as $user) {
                    if ($user->getId() == $_POST['user_id']) {
                        $simple_late_user = $user;
                        $no_change = $simple_late_user->getLateDayExceptions() == $late_days;
                        break;
                    }
                }
            }
            if (($simple_late_user == null && intval($late_days) == 0) || $no_change) {
                $this->core->addNoticeMessage("User already has " . $late_days . " extensions; no changes made");
                return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse());
            }

            $team = $this->core->getQueries()->getTeamByGradeableAndUser($_POST['g_id'], $_POST['user_id']);
            //0 is for single submission, 1 is for team submission
            $option = isset($_POST['option']) ? $_POST['option'] : -1;
            if ($team != null && $team->getSize() > 1) {
                if ($option == 0) {
                    $this->core->getQueries()->updateExtensions($_POST['user_id'], $_POST['g_id'], $late_days, $reason_for_exception);
                    $this->core->addSuccessMessage("Extensions have been updated");
                    return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse());
                }
                elseif ($option == 1) {
                    $team_member_ids = explode(", ", $team->getMemberList());
                    for ($i = 0; $i < count($team_member_ids); $i++) {
                        $this->core->getQueries()->updateExtensions($team_member_ids[$i], $_POST['g_id'], $late_days, $reason_for_exception);
                    }
                    $this->core->addSuccessMessage("Extensions have been updated");
                    return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse());
                }
                else {
                    $team_member_ids = explode(", ", $team->getMemberList());
                    $team_members = [];
                    for ($i = 0; $i < count($team_member_ids); $i++) {
                        $team_members[$team_member_ids[$i]] = $this->core->getQueries()->getUserById($team_member_ids[$i])->getDisplayedGivenName() . " " .
                            $this->core->getQueries()->getUserById($team_member_ids[$i])->getDisplayedFamilyName();
                    }
                    $popup_html = $this->core->getOutput()->renderTwigTemplate(
                        "admin/users/MoreExtensions.twig",
                        ['g_id' => $_POST['g_id'], 'member_list' => $team_members]
                    );
                    return MultiResponse::JsonOnlyResponse(
                        JsonResponse::getSuccessResponse(['is_team' => true, 'popup' => $popup_html])
                    );
                }
            }
            else {
                $this->core->getQueries()->updateExtensions($_POST['user_id'], $_POST['g_id'], $late_days, $reason_for_exception);
                $this->core->addSuccessMessage("Extensions have been updated");
                return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse());
            }
        }
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     * @return RedirectResponse|WebResponse
     **/
    #[Route("/courses/{_semester}/{_course}/users/view_latedays", methods: ["GET"])]
    public function viewStudentLatedays() {
        if (!isset($_GET['student_id'])) {
            $this->core->addErrorMessage("No student ID provided");
            return new RedirectResponse($this->core->buildCourseUrl(['users']));
        }
        $student_id = $_GET['student_id'];
        $user = $this->core->getQueries()->getUserById($student_id);
        if ($user === null) {
            $this->core->addErrorMessage("Invalid Student ID \"" . $_GET['student_id'] . "\"");
            return new RedirectResponse($this->core->buildCourseUrl(['users']));
        }
        return new WebResponse(
            'LateDaysTable',
            'showLateTabletoInstructor',
            LateDays::fromUser($this->core, $user)
        );
    }

    /**
     * @return MultiResponse
     */
    private function getLateDays() {
        $users = $this->core->getQueries()->getUsersWithLateDays();
        $user_table = [];
        foreach ($users as $user) {
            $user_table[] = ['user_id' => $user->getId(),'user_givenname' => $user->getDisplayedGivenName(), 'user_familyname' => $user->getDisplayedFamilyName(), 'late_days' => $user->getAllowedLateDays(), 'datestamp' => $user->getSinceTimestamp(), 'late_day_exceptions' => $user->getLateDayExceptions()];
        }
        return MultiResponse::JsonOnlyResponse(
            JsonResponse::getSuccessResponse(['users' => $user_table])
        );
    }

    /**
     * Given a path to an uploaded CSV file, parse and validate it, creating an array of data from the CSV information.
     * The function returns an array with two keys:
     *      success: boolean, true if CSV was properly validated and parsed
     *      error: string, why the CSV failed to validate and parse
     * @param string $csv_file The path to the uploaded CSV file
     * @param array $data The array to fill with the parsed CSV data
     * @param-out ?array $data The array filled with the parsed CSV data
     * @param string $type The type of CSV being parsed, either "late" or
     */
    private function parseAndValidateCsv(string $csv_file, array &$data, string $type): array {
        //Validate file MIME type (needs to be "text/plain")
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $_FILES['csv_upload']['tmp_name']);
        finfo_close($file_info);
        //MIME type must be text, but all subtypes are acceptable.
        if (substr($mime_type, 0, 5) !== "text/") {
            $data = null;
            return [
                "success" => false,
                "error" => "Invalid mimetype, must start with 'text/', got '{$mime_type}'"
            ];
        }

        $file_content = file_get_contents($csv_file);
        if ($file_content === false) {
            $data = null;
            return [
                "success" => false,
                "error" => "Could not open CSV file for reading",
            ];
        }
        $file_content = preg_replace("/\r\n|\r/", "\n", $file_content);
        $rows = explode("\n", $file_content);
        $rows = array_filter($rows, 'strlen');
        foreach ($rows as $idx => $row) {
            $row_number = $idx + 1;
            $fields = explode(',', $row);
            //Remove any extraneous whitespace at beginning/end of all fields.
            $fields = array_map(function ($k) {
                return trim($k);
            }, $fields);

            //All types have 3 fields except for exceptions, which can have 3 or 4 rows.
            if (count($fields) !== 3 && !($type === 'extension' && count($fields) === 4)) {
                $data = null;
                return [
                    "success" => false,
                    "error" => "Row {$row_number} did not have 3 columns",
                ];
            }
            //$fields[0]: Verify student exists in class (check by student user ID)
            if ($this->core->getQueries()->getUserById($fields[0]) === null) {
                $data = null;
                return [
                    "success" => false,
                    "error" => "Could not find user for given id '{$fields[0]}' on row {$row_number}",
                ];
            }
            //$fields[1] represents timestamp in the format (MM/DD/YY) for late days
            //(MM/DD/YYYY), (MM-DD-YY), or (MM-DD-YYYY).
            if ($type === "late" && !DateUtils::validateTimestamp($fields[1])) {
                $data = null;
                return [
                    "success" => false,
                    "error" => "Could not validate timestamp '{$fields[1]}' on row {$row_number}",
                ];
            }
            //$fields[1] represents the gradeable id for extensions
            if ($type === "extension" && !$this->validateHomework($fields[1])) {
                $data = null;
                return [
                    "success" => false,
                    "error" => "Could not resolve gradeable ID '{$fields[1]}' on row {$row_number}",
                ];
            }
            //$fields[2]: Number of late days must be an integer >= 0
            if (!ctype_digit($fields[2]) && intval($fields[2]) < 0) {
                $data = null;
                return [
                    "success" => false,
                    "error" => "Third column must be an integer greater or equal to zero, got '{$fields[2]}' on row {$row_number}",
                ];
            }
            //$fields[3] added if not present to extension type. Allows for backwards compatibility.
            if ($type === "extension" && count($fields) === 3) {
                $fields[] = 'unspecified';
            }
            //Fields information seems okay.  Push fields onto data array.
            $data[] = $fields;
        }
        //Validation successful.
        return [
            "success" => true,
        ];
    }

    private function validateHomework(string $id): bool {
        $g_ids = $this->core->getQueries()->getAllElectronicGradeablesIds();
        foreach ($g_ids as $index => $value) {
            if ($id === $value['g_id']) {
                return true;
            }
        }
        return false;
    }
}

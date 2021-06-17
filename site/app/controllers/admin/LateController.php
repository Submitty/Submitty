<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\exceptions\DatabaseException;
use app\libraries\DateUtils;
use app\libraries\routers\AccessControl;
use app\libraries\response\MultiResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\WebResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class LateController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 */
class LateController extends AbstractController {
    /**
     * @Route("/courses/{_semester}/{_course}/late_days")
     * @return MultiResponse
     */
    public function viewLateDays() {
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                ['admin', 'LateDay'],
                'displayLateDays',
                $this->core->getQueries()->getUsersWithLateDays(),
                $this->core->getQueries()->getAllUsers()
            )
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/extensions")
     * @return MultiResponse
     */
    public function viewExtensions() {
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                ['admin', 'Extensions'],
                'displayExtensions',
                $this->core->getQueries()->getAllElectronicGradeablesIds()
            )
        );
    }

    /**
     * @param $csv_option string csv_option_overwrite_all or csv_option_preserve_higher
     *
     * @Route("/courses/{_semester}/{_course}/late_days/update", methods={"POST"})
     * @return MultiResponse
     */
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

            if (!isset($_POST['datestamp']) || (\DateTime::createFromFormat('Y-m-d H:i:s', $_POST['datestamp']) === false)) {
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

            $date_time = DateUtils::parseDateTime($_POST['datestamp'], $this->core->getUser()->getUsableTimeZone());

            $this->core->getQueries()->updateLateDays($_POST['user_id'], $date_time, $_POST['late_days']);
            $this->core->addSuccessMessage("Late days have been updated");
            return $this->getLateDays();
        }
    }

    /**
     * @Route("/courses/{_semester}/{_course}/late_days/delete", methods={"POST"})
     * @return MultiResponse
     */
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
     * @Route("/courses/{_semester}/{_course}/extensions/update", methods={"POST"})
     * @return MultiResponse
     */
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
                    $this->core->getQueries()->updateExtensions($data[$i][0], $data[$i][1], $data[$i][2]);
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
                    $this->core->getQueries()->updateExtensions($_POST['user_id'], $_POST['g_id'], $late_days);
                    $this->core->addSuccessMessage("Extensions have been updated");
                    return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse());
                }
                elseif ($option == 1) {
                    $team_member_ids = explode(", ", $team->getMemberList());
                    for ($i = 0; $i < count($team_member_ids); $i++) {
                        $this->core->getQueries()->updateExtensions($team_member_ids[$i], $_POST['g_id'], $late_days);
                    }
                    $this->core->addSuccessMessage("Extensions have been updated");
                    return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse());
                }
                else {
                    $team_member_ids = explode(", ", $team->getMemberList());
                    $team_members = [];
                    for ($i = 0; $i < count($team_member_ids); $i++) {
                        $team_members[$team_member_ids[$i]] = $this->core->getQueries()->getUserById($team_member_ids[$i])->getDisplayedFirstName() . " " .
                            $this->core->getQueries()->getUserById($team_member_ids[$i])->getDisplayedLastName();
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
                $this->core->getQueries()->updateExtensions($_POST['user_id'], $_POST['g_id'], $late_days);
                $this->core->addSuccessMessage("Extensions have been updated");
                return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse());
            }
        }
    }

    /**
     * @return MultiResponse
     */
    private function getLateDays() {
        $users = $this->core->getQueries()->getUsersWithLateDays();
        $user_table = [];
        foreach ($users as $user) {
            $user_table[] = ['user_id' => $user->getId(),'user_firstname' => $user->getDisplayedFirstName(), 'user_lastname' => $user->getDisplayedLastName(), 'late_days' => $user->getAllowedLateDays(), 'datestamp' => $user->getSinceTimestamp(), 'late_day_exceptions' => $user->getLateDayExceptions()];
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
        ini_set("auto_detect_line_endings", true);

        $rows = file($csv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($rows === false) {
            $data = null;
            return [
                "success" => false,
                "error" => "Could not open CSV file for reading",
            ];
        }
        foreach ($rows as $idx => $row) {
            $row_number = $idx + 1;
            $fields = explode(',', $row);
            //Remove any extraneous whitespace at beginning/end of all fields.
            $fields = array_map(function ($k) {
                return trim($k);
            }, $fields);

            //Each row has three fields
            if (count($fields) !== 3) {
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
                    "successs" => false,
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

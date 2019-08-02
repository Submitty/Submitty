<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\DateUtils;
use app\libraries\routers\AccessControl;
use app\libraries\response\Response;
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
     * @Route("/{_semester}/{_course}/late_days")
     * @return Response
     */
    public function viewLateDays() {
        return Response::WebOnlyResponse(
            new WebResponse(
                ['admin', 'LateDay'],
                'displayLateDays',
                $this->core->getQueries()->getUsersWithLateDays()
            )
        );
    }

    /**
     * @Route("/{_semester}/{_course}/extensions")
     * @return Response
     */
    public function viewExtensions() {
        return Response::WebOnlyResponse(
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
     * @Route("/{_semester}/{_course}/late_days/update", methods={"POST"})
     * @return Response
     */
    public function updateLateDays($csv_option = null) {
        if (isset($_FILES['csv_upload']) && (file_exists($_FILES['csv_upload']['tmp_name']))) {
            $data = array();
            if (!($this->parseAndValidateCsv($_FILES['csv_upload']['tmp_name'], $data, "late"))) {
                $error = "Something is wrong with the CSV you have chosen. Try again.";
                $this->core->addErrorMessage($error);
                return Response::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            else {
                for ($i = 0; $i < count($data); $i++){
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
                return Response::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }

            if (!isset($_POST['datestamp']) ||  (\DateTime::createFromFormat('Y-m-d', $_POST['datestamp']) === false)) {
                $error = "Datestamp must be Y-m-d";
                $this->core->addErrorMessage($error);
                return Response::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            if (((!isset($_POST['late_days'])) || $_POST['late_days'] == "" || (!ctype_digit($_POST['late_days'])))) {
                $error = "Late Days must be a nonnegative integer";
                $this->core->addErrorMessage($error);
                return Response::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            $this->core->getQueries()->updateLateDays($_POST['user_id'], $_POST['datestamp'], $_POST['late_days']);
            $this->core->addSuccessMessage("Late days have been updated");
            return $this->getLateDays();
        }
    }

    /**
     * @Route("/{_semester}/{_course}/late_days/delete", methods={"POST"})
     * @return Response
     */
    public function deleteLateDays() {
        $user = current($this->core->getQueries()->getUsersById([$_POST['user_id']]));
        if (!$user) {
            $error = "Invalid Student ID";
            $this->core->addErrorMessage($error);

            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse($error)
            );
        }
        if ((!isset($_POST['datestamp']) || !DateUtils::validateTimestamp($_POST['datestamp']))) {
            $error = "Datestamp must be mm/dd/yy";
            $this->core->addErrorMessage($error);

            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse($error)
            );
        }
        $this->core->getQueries()->deleteLateDays($_POST['user_id'], $_POST['datestamp']);
        $this->core->addSuccessMessage("Late days entry removed");

        return $this->getLateDays();
    }

    /**
     * @Route("/{_semester}/{_course}/extensions/update", methods={"POST"})
     * @return Response
     */
    public function updateExtension() {
        if (isset($_FILES['csv_upload']) && (file_exists($_FILES['csv_upload']['tmp_name']))) {
            $data = array();
            if (!($this->parseAndValidateCsv($_FILES['csv_upload']['tmp_name'], $data, "extension"))) {
                $error = "Something is wrong with the CSV you have chosen. Try again.";
                return Response::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            else {
                for ($i = 0; $i < count($data); $i++){
                    $this->core->getQueries()->updateExtensions($data[$i][0], $data[$i][1], $data[$i][2]);
                }

                return $this->getExtensions($data[0][1]);
            }
        }
        else {
            if ((!isset($_POST['g_id']) || $_POST['g_id'] == "" )) {
                $error = "Please choose a gradeable_id";
                return Response::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            $user = current($this->core->getQueries()->getUsersById([$_POST['user_id']]));
            if (!$user) {
                $error = "Invalid Student ID";
                return Response::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            if ((!isset($_POST['late_days'])) || $_POST['late_days'] == "" || (!ctype_digit($_POST['late_days']))) {
                $error = "Late Days must be a nonnegative integer";
                return Response::JsonOnlyResponse(
                    JsonResponse::getFailResponse($error)
                );
            }
            $team = $this->core->getQueries()->getTeamByGradeableAndUser($_POST['g_id'], $_POST['user_id']);
            //0 is for single submission, 1 is for team submission
            $option = isset($_POST['option']) ? $_POST['option'] : -1;
            if($team != NULL && $team->getSize() > 1){
                if($option == 0){
                    $this->core->getQueries()->updateExtensions($_POST['user_id'], $_POST['g_id'], $_POST['late_days']);
                    return $this->getExtensions($_POST['g_id']);
                } else if($option == 1){
                    $team_member_ids = explode(", ", $team->getMemberList());
                    for($i = 0; $i < count($team_member_ids); $i++){
                        $this->core->getQueries()->updateExtensions($team_member_ids[$i], $_POST['g_id'], $_POST['late_days']);
                    }
                    return $this->getExtensions($_POST['g_id']);
                } else {
                    $team_member_ids = explode(", ", $team->getMemberList());
                    $team_members = array();
                    for($i = 0; $i < count($team_member_ids); $i++){
                        $team_members[$team_member_ids[$i]] = $this->core->getQueries()->getUserById($team_member_ids[$i])->getDisplayedFirstName() . " " .
                            $this->core->getQueries()->getUserById($team_member_ids[$i])->getDisplayedLastName();
                    }
                    $popup_html = $this->core->getOutput()->renderTwigTemplate("admin/users/MoreExtensions.twig",
                        ['g_id' => $_POST['g_id'], 'member_list' => $team_members]);
                    return Response::JsonOnlyResponse(
                        JsonResponse::getSuccessResponse(['is_team' => true, 'popup' => $popup_html])
                    );
                }
            } else {
                $this->core->getQueries()->updateExtensions($_POST['user_id'], $_POST['g_id'], $_POST['late_days']);
                return $this->getExtensions($_POST['g_id']);
            }
        }
    }

    /**
     * @return Response
     */
    private function getLateDays(){
        $users = $this->core->getQueries()->getUsersWithLateDays();
        $user_table = array();
        foreach($users as $user){
            $user_table[] = array('user_id' => $user->getId(),'user_firstname' => $user->getDisplayedFirstName(), 'user_lastname' => $user->getDisplayedLastName(), 'late_days' => $user->getAllowedLateDays(), 'datestamp' => $user->getSinceTimestamp(), 'late_day_exceptions' => $user->getLateDayExceptions());
        }
        return Response::JsonOnlyResponse(
            JsonResponse::getSuccessResponse(['users' => $user_table])
        );
    }

    /**
     * @param $g_id
     *
     * @Route("/{_semester}/{_course}/extensions/{g_id}")
     * @return Response
     */
    public function getExtensions($g_id) {
        $users = $this->core->getQueries()->getUsersWithExtensions($g_id);
        $user_table = array();
        foreach($users as $user) {
            $user_table[] = array('user_id' => $user->getId(),'user_firstname' => $user->getDisplayedFirstName(), 'user_lastname' => $user->getDisplayedLastName(), 'late_day_exceptions' => $user->getLateDayExceptions());
        }
        return Response::JsonOnlyResponse(
            JsonResponse::getSuccessResponse([
                'gradeable_id' => $g_id,
                'users' => $user_table
            ])
        );
    }

    private function parseAndValidateCsv($csv_file, &$data, $type) {
    //IN:  * csv file name and path
    //     * (by reference) empty data array that will be filled.
    //OUT: TRUE should csv file be properly validated and data array filled.
    //     FALSE otherwise.
    //PURPOSE:  (1) validate uploaded csv file so it may be parsed.
    //          (2) create data array of csv information.

        //Validate file MIME type (needs to be "text/plain")
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $_FILES['csv_upload']['tmp_name']);
        finfo_close($file_info);
        //MIME type must be text, but all subtypes are acceptable.
        if (substr($mime_type, 0, 5) !== "text/") {
            $data = null;
            return false;
        }
        $rows = file($csv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($rows === false) {
            $data = null;
            return false;
        }
        foreach($rows as $row) {
            $fields = explode(',', $row);
            //Remove any extraneous whitespace at beginning/end of all fields.
            $fields = array_map(function($k) { return trim($k); }, $fields);
            //Each row has three fields
            if (count($fields) !== 3) {
                $data = null;
                return false;
            }
            //$fields[0]: Verify student exists in class (check by student user ID)
            if ($this->core->getQueries()->getUserById($fields[0]) === null) {
                $data = null;
                return false;
            }
            //$fields[1] represents timestamp in the format (MM/DD/YY) for late days
            //(MM/DD/YYYY), (MM-DD-YY), or (MM-DD-YYYY).
            if ($type == "late" && !DateUtils::validateTimestamp($fields[1])) {
                $data = null;
                return false;
            }
            //$fields[1] represents the gradeable id for extensions
            if ($type == "extension" && !$this->validateHomework($fields[1])) {
                $data = null;
                return false;
            }
            //$fields[2]: Number of late days must be an integer >= 0
            if (!ctype_digit($fields[2])) {
                $data = null;
                return false;
            }
            //Fields information seems okay.  Push fields onto data array.
            $data[] = $fields;
        }
        //Validation successful.
        return true;
    }

    private function validateHomework($id) {
        $g_ids = $this->core->getQueries()->getAllElectronicGradeablesIds();
        foreach($g_ids as $index => $value) {
            if ($id === $value['g_id']) {
                return true;
            }
        }
        return false;
    }
}

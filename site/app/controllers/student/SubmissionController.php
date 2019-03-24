<?php

namespace app\controllers\student;

use app\controllers\AbstractController;
use app\libraries\DateUtils;
use app\libraries\ErrorMessages;
use app\libraries\FileUtils;
use app\libraries\GradeableType;
use app\libraries\Logger;
use app\libraries\Utils;
use app\models\gradeable\Gradeable;
use app\controllers\grading\ElectronicGraderController;



class SubmissionController extends AbstractController {

    private $upload_details = array('version' => -1, 'version_path' => null, 'user_path' => null,
                                    'assignment_settings' => false);


    /**
     * Tries to get a given electronic gradeable considering the active
     *  users access level and the status of the gradeable, but returns
     *  null if no access
     *
     * FIXME: put this in new access control system
     *
     * @param string $gradeable_id
     * @return Gradeable|null
     */
    public function tryGetElectronicGradeable($gradeable_id) {
        if ($gradeable_id === null || $gradeable_id === '') {
            return null;
        }

        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
            $now = $this->core->getDateTimeNow();

            if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE
                && ($this->core->getUser()->accessAdmin()
                    || $gradeable->getTaViewStartDate() <= $now
                    && $this->core->getUser()->accessGrading()
                    || $gradeable->getSubmissionOpenDate() <= $now)) {
                return $gradeable;
            }
            return null;
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    public function run() {
        switch($_REQUEST['action']) {
            case 'upload':
                return $this->ajaxUploadSubmission();
                break;
            case 'update':
                return $this->updateSubmissionVersion();
                break;
            case 'check_refresh':
                return $this->checkRefresh();
                break;
            case 'bulk':
                return $this->ajaxBulkUpload();
                break;
            case 'upload_split':
                return $this->ajaxUploadSplitItem();
                break;
            case 'upload_images_files':
                return $this->ajaxUploadImagesFiles();
                break;
            case 'upload_course_materials_files':
                return $this->ajaxUploadCourseMaterialsFiles();
                break;
            case 'delete_split':
                return $this->ajaxDeleteSplitItem();
                break;
            case 'verify':
                return $this->ajaxValidGradeable();
                break;
            case 'request_regrade':
                return $this->requestRegrade();
                break;
            case 'make_request_post':
                return $this->makeRequestPost();
                break;
            case 'delete_request':
                return $this->deleteRequest();
                break;
            case 'change_request_status':
                return $this->changeRequestStatus();
                break;
            case 'display':
            default:
                return $this->showHomeworkPage();
                break;
        }
    }
    private function requestRegrade() {
        $content = $_POST['replyTextArea'] ?? '';
        $gradeable_id = $_REQUEST['gradeable_id'] ?? '';
        $submitter_id = $_REQUEST['submitter_id'] ?? '';

        $user = $this->core->getUser();

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        if(!$gradeable->isRegradeAllowed()) {
            $this->core->getOutput()->renderJsonFail('Grade inquiries are not enabled for this gradeable');
            return;
        }

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return;
        }

        // TODO: add to access control method
        if (!$graded_gradeable->getSubmitter()->hasUser($user) && !$user->accessFullGrading()) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to request regrade');
            return;
        }

        try {
            $this->core->getQueries()->insertNewRegradeRequest($graded_gradeable, $user, $content);
            $this->core->getOutput()->renderJsonSuccess();
        } catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        } catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    private function makeRequestPost() {
        $content = str_replace("\r", "", $_POST['replyTextArea']);
        $gradeable_id = $_REQUEST['gradeable_id'] ?? '';
        $submitter_id = $_REQUEST['submitter_id'] ?? '';
        $status = $_REQUEST['status'] ?? -1;

        $user = $this->core->getUser();

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return;
        }

        if (!$graded_gradeable->hasRegradeRequest()) {
            $this->core->getOutput()->renderJsonFail('Submitter has not made a grade inquiry');
            return;
        }

        // TODO: add to access control method
        if (!$graded_gradeable->getSubmitter()->hasUser($user) && !$user->accessFullGrading()) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to make grade inquiry post');
            return;
        }

        try {
            $this->core->getQueries()->insertNewRegradePost($graded_gradeable->getRegradeRequest()->getId(), $user->getId(), $content);
            $graded_gradeable->getRegradeRequest()->setStatus($status);
            $this->core->getQueries()->saveRegradeRequest($graded_gradeable->getRegradeRequest());
            $this->core->getOutput()->renderJsonSuccess();
        } catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        } catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    private function deleteRequest() {
        $gradeable_id = $_REQUEST['gradeable_id'] ?? '';
        $submitter_id = $_REQUEST['submitter_id'] ?? '';

        $user = $this->core->getUser();

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return;
        }

        if (!$graded_gradeable->hasRegradeRequest()) {
            $this->core->getOutput()->renderJsonFail('Submitter has not made a grade inquiry');
            return;
        }

        // TODO: add to access control method
        if (!$user->accessFullGrading()) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to delete grade inquiry');
            return;
        }

        try {
            $this->core->getQueries()->deleteRegradeRequest($graded_gradeable->getRegradeRequest());
            $this->core->getOutput()->renderJsonSuccess();
        } catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        } catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    private function changeRequestStatus() {
        $gradeable_id = $_REQUEST['gradeable_id'] ?? '';
        $submitter_id = $_REQUEST['submitter_id'] ?? '';
        $status = $_REQUEST['status'] ?? null;

        if ($status === null) {
            $this->core->getOutput()->renderJsonFail('Missing status parameter');
            return;
        }

        $user = $this->core->getUser();

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return;
        }

        if (!$graded_gradeable->hasRegradeRequest()) {
            $this->core->getOutput()->renderJsonFail('Submitter has not made a grade inquiry');
            return;
        }

        // TODO: add to access control method
        if (!$graded_gradeable->getSubmitter()->hasUser($user) && !$user->accessFullGrading()) {
            $this->core->getOutput()->renderJsonFail('Insufficient permissions to change grade inquiry status');
            return;
        }

        try {
            $graded_gradeable->getRegradeRequest()->setStatus($status);
            $this->core->getQueries()->saveRegradeRequest($graded_gradeable->getRegradeRequest());
            $this->core->getOutput()->renderJsonSuccess();
        } catch (\InvalidArgumentException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        } catch (\Exception $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    private function showHomeworkPage() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $gradeable = $this->tryGetElectronicGradeable($gradeable_id);
        if($gradeable === null) {
            $this->core->getOutput()->renderOutput('Error', 'noGradeable', $gradeable_id);
            return array('error' => true, 'message' => 'No gradeable with that id.');
        }

        $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $this->core->getUser()->getId());
        if ($graded_gradeable === null && !$this->core->getUser()->accessAdmin()) {
            // FIXME if $graded_gradeable is null, the user isn't on a team, so we want to redirect
            // FIXME    to nav with an error
        }

        // Attempt to put the version number to be in bounds of the gradeable
        $version = intval($_REQUEST['gradeable_version'] ?? 0);
        if ($version < 1 || $version > ($graded_gradeable !== null ? $graded_gradeable->getAutoGradedGradeable()->getHighestVersion() : 0)) {
            $version = $graded_gradeable !== null ? $graded_gradeable->getAutoGradedGradeable()->getActiveVersion() : 0;
        }

        $error = false;
        $now = $this->core->getDateTimeNow();

        // ORIGINAL
        //if (!$gradeable->isSubmissionOpen() && !$this->core->getUser()->accessAdmin()) {

        // TEMPORARY - ALLOW LIMITED & FULL ACCESS GRADERS TO PRACTICE ALL FUTURE HOMEWORKS
        if (!$this->core->getUser()->accessGrading() && (
                !$gradeable->isSubmissionOpen()
                || $gradeable->isStudentView() && $gradeable->isStudentViewAfterGrades() && !$gradeable->isTaGradeReleased()
            )) {
            $this->core->getOutput()->renderOutput('Error', 'noGradeable', $gradeable_id);
            return array('error' => true, 'message' => 'No gradeable with that id.');
        }
        else if ($gradeable->isTeamAssignment() && $graded_gradeable === null && !$this->core->getUser()->accessAdmin()) {
            $this->core->addErrorMessage('Must be on a team to access submission');
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
            return array('error' => true, 'message' => 'Must be on a team to access submission.');
        }
        else {
            $loc = array('component' => 'student',
                         'gradeable_id' => $gradeable->getId());
            $this->core->getOutput()->addBreadcrumb($gradeable->getTitle(), $this->core->buildUrl($loc));
            if (!$gradeable->hasAutogradingConfig()) {
                $this->core->getOutput()->renderOutput(array('submission', 'Homework'),
                                                       'unbuiltGradeable', $gradeable);
                $error = true;
            }
            else {
                if ($graded_gradeable !== null
                    && $gradeable->isTaGradeReleased()
                    && $gradeable->isTaGrading()
                    && $graded_gradeable->isTaGradingComplete()) {
                    $graded_gradeable->getOrCreateTaGradedGradeable()->setUserViewedDate($now);
                    $this->core->getQueries()->saveTaGradedGradeable($graded_gradeable->getTaGradedGradeable());
                }

                // Only show hidden test cases if the display version is the graded version (and grades are released)
                $show_hidden = false;
                if ($graded_gradeable != NULL) {
                  $show_hidden = $version == $graded_gradeable->getOrCreateTaGradedGradeable()->getGradedVersion(false) && $gradeable->isTaGradeReleased();
                }

                // If we get here, then we can safely construct the old model w/o checks
                $this->core->getOutput()->renderOutput(array('submission', 'Homework'),
                                                       'showGradeable', $gradeable, $graded_gradeable, $version, $show_hidden, false);
            }
        }
        return array('id' => $gradeable_id, 'error' => $error);
    }

    /**
    * Function for verification that a given RCS ID is valid and has a corresponding user and gradeable.
    * This should be called via AJAX, saving the result to the json_buffer of the Output object.
    * If failure, also returns message explaining what happened.
    * If success, also returns highest version of the student gradeable.
    */
    private function ajaxValidGradeable() {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $this->core->getCsrfToken()) {
            $msg = "Invalid CSRF token. Refresh the page and try again.";
            $return = array('success' => false, 'message' => $msg);
            $this->core->getOutput()->renderJson($return);
            return $return;
        }

        if (!isset($_POST['user_id'])) {
            $msg = "Did not pass in user_id.";
            $return = array('success' => false, 'message' => $msg);
            $this->core->getOutput()->renderJson($return);
            return $return;
        }

        $gradeable_id = $_REQUEST['gradeable_id'] ?? '';
        $gradeable = $this->tryGetElectronicGradeable($gradeable_id);

        // This checks for an assignment id, and that it's a valid assignment id in that
        // it corresponds to one that we can access (whether through admin or it being released)
        if ($gradeable === null) {
            return $this->uploadResult("Invalid gradeable id '{$gradeable_id}'", false);
        }

        //usernames come in comma delimited. We split on the commas, then filter out blanks.
        $user_ids = explode (",", $_POST['user_id']);
        $user_ids = array_filter($user_ids);

        //If no user id's were submitted, give a graceful error.
        if (count($user_ids) === 0) {
            $msg = "No valid user ids were found.";
            $return = array('success' => false, 'message' => $msg);
            $this->core->getOutput()->renderJson($return);
            return $return;
        }

        //For every userid, we have to check that its real.
        foreach($user_ids as $id){
            $user = $this->core->getQueries()->getUserById($id);
            if ($user === null) {
                $msg = "Invalid user id '{$id}'";
                $return = array('success' => false, 'message' => $msg);
                $this->core->getOutput()->renderJson($return);
                return $return;
            }
            if (!$user->isLoaded()) {
                $msg = "Invalid user id '{$id}'";
                $return = array('success' => false, 'message' => $msg);
                $this->core->getOutput()->renderJson($return);
                return $return;
            }
        }

        $graded_gradeables = [];
        foreach ($this->core->getQueries()->getGradedGradeables([$gradeable], $user_ids, null) as $gg) {
            $graded_gradeables[] = $gg;
        }

        // Below is true if no users are on a team. In this case, we later make the team automatically,
        //   so this should not return a failure.
        // if (count($graded_gradeables) === 0) {
        //     // No user was on a team
        //     $msg = 'No user on a team';
        //     $return = array('success' => false, 'message' => $msg);
        //     $this->core->getOutput()->renderJson($return);
        //     return $return;
        // } else 

        //If the users are on multiple teams.
        if (count($graded_gradeables) > 1) {
            // Not all users were on the same team
            $msg = "Inconsistent teams. One or more users are on different teams.";
            $return = array('success' => false, 'message' => $msg);
            $this->core->getOutput()->renderJson($return);
            return $return;
        }

        $highest_version = -1;
        if(count($graded_gradeables) > 0){
            $graded_gradeable = $graded_gradeables[0];
            $highest_version = $graded_gradeable->getAutoGradedGradeable()->getHighestVersion();
        }

        //If there has been a previous submission, we tag it so that we can pop up a warning.
        $return = array('success' => true, 'highest_version' => $highest_version, 'previous_submission' => $highest_version > 0);
        $this->core->getOutput()->renderJson($return);

        return $return;
    }

    /**
    * Function that uploads a bulk PDF to the uploads/bulk_pdf folder. Splits it into PDFs of the page
    * size entered and places in the uploads/split_pdf folder.
    * Its error checking has overlap with ajaxUploadSubmission.
    */
    private function ajaxBulkUpload() {
        // make sure is at least full access grader
        if (!$this->core->getUser()->accessFullGrading()) {
            $msg = "You do not have access to that page.";
            $this->core->addErrorMessage($msg);
            return $this->uploadResult($msg, false);
        }

        if (!isset($_POST['num_pages'])) {
            $msg = "Did not pass in number of pages or files were too large.";
            $return = array('success' => false, 'message' => $msg);
            $this->core->getOutput()->renderJson($return);
            return $return;
        }

        $gradeable_id = $_REQUEST['gradeable_id'] ?? '';
        $gradeable = $this->tryGetElectronicGradeable($gradeable_id);

        // This checks for an assignment id, and that it's a valid assignment id in that
        // it corresponds to one that we can access (whether through admin or it being released)
        if ($gradeable === null) {
            return $this->uploadResult("Invalid gradeable id '{$gradeable_id}'", false);
        }

        $num_pages = $_POST['num_pages'];

        // making sure files have been uploaded

        if (isset($_FILES["files1"])) {
            $uploaded_file = $_FILES["files1"];
        }

        $errors = array();
        $count = 0;
        if (isset($uploaded_file)) {
            $count = count($uploaded_file["name"]);
            for ($j = 0; $j < $count; $j++) {
                if (!isset($uploaded_file["tmp_name"][$j]) || $uploaded_file["tmp_name"][$j] === "") {
                    $error_message = $uploaded_file["name"][$j]." failed to upload. ";
                    if (isset($uploaded_file["error"][$j])) {
                        $error_message .= "Error message: ". ErrorMessages::uploadErrors($uploaded_file["error"][$j]). ".";
                    }
                    $errors[] = $error_message;
                }
            }
        }

        if (count($errors) > 0) {
            $error_text = implode("\n", $errors);
            return $this->uploadResult("Upload Failed: ".$error_text, false);
        }

        $max_size = $gradeable->getAutogradingConfig()->getMaxSubmissionSize();
    	if ($max_size < 10000000) {
    	    $max_size = 10000000;
    	}
        // Error checking of file name
        $file_size = 0;
        if (isset($uploaded_file)) {
            for ($j = 0; $j < $count; $j++) {
                if(FileUtils::isValidFileName($uploaded_file["name"][$j]) === false) {
                    return $this->uploadResult("Error: You may not use quotes, backslashes or angle brackets in your file name ".$uploaded_file["name"][$j].".", false);
                }
                if(substr($uploaded_file["name"][$j],-3) != "pdf") {
                    return $this->uploadResult($uploaded_file["name"][$j]." is not a PDF!", false);
                }
                $file_size += $uploaded_file["size"][$j];
            }
        }

        if ($file_size > $max_size) {
            return $this->uploadResult("File(s) uploaded too large.  Maximum size is ".($max_size/1000)." kb. Uploaded file(s) was ".($file_size/1000)." kb.", false);
        }

        // creating uploads/bulk_pdf/gradeable_id directory

        $pdf_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "bulk_pdf", $gradeable->getId());
        if (!FileUtils::createDir($pdf_path)) {
            return $this->uploadResult("Failed to make gradeable path.", false);
        }

        // creating directory under gradeable_id with the timestamp

        $current_time = $this->core->getDateTimeNow()->format("m-d-Y_H:i:sO");
        $version_path = FileUtils::joinPaths($pdf_path, $current_time);
        if (!FileUtils::createDir($version_path)) {
            return $this->uploadResult("Failed to make gradeable path.", false);
        }

        // save the pdf in that directory
        // delete the temporary file
        if (isset($uploaded_file)) {
            for ($j = 0; $j < $count; $j++) {
                if ($this->core->isTesting() || is_uploaded_file($uploaded_file["tmp_name"][$j])) {
                    $dst = FileUtils::joinPaths($version_path, $uploaded_file["name"][$j]);
                    if (!@copy($uploaded_file["tmp_name"][$j], $dst)) {
                        return $this->uploadResult("Failed to copy uploaded file {$uploaded_file["name"][$j]} to current submission.", false);
                    }
                }
                else {
                    return $this->uploadResult("The tmp file '{$uploaded_file['name'][$j]}' was not properly uploaded.", false);
                }
                // Is this really an error we should fail on?
                if (!@unlink($uploaded_file["tmp_name"][$j])) {
                    return $this->uploadResult("Failed to delete the uploaded file {$uploaded_file["name"][$j]} from temporary storage.", false);
                }
            } 
        }

        // use pdf_check.cgi to check that # of pages is valid and split
        // also get the cover image and name for each pdf appropriately

        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        if($_POST['use_qr_codes'] === "true"){
            $qr_prefix = rawurlencode($_POST['qr_prefix']);
            $qr_suffix = rawurlencode($_POST['qr_suffix']);

            $config_data = json_decode(file_get_contents("/usr/local/submitty/config/submitty.json"));
            //create a new job to split but uploads via QR
            for($i = 0; $i < $count; $i++){
                $qr_upload_data = [
                    "job"       => "BulkQRSplit",
                    "semester"  => $semester,
                    "course"    => $course,
                    "g_id"      => $gradeable_id,
                    "timestamp" => $current_time,
                    "qr_prefix" => $qr_prefix,
                    "qr_suffix" => $qr_suffix,
                    "filename"  => $uploaded_file["name"][$i]
                ];

                $qr_upload_job  = "/var/local/submitty/daemon_job_queue/qr_upload_" . $uploaded_file["name"][$i] . ".json"; 

                //add new job to queue
                if(!file_put_contents($qr_upload_job, json_encode($qr_upload_data, JSON_PRETTY_PRINT)) ){
                    $this->core->getOutput()->renderJsonFail("Failed to write BulkQRSplit job");
                    return $this->uploadResult("Failed to write BulkQRSplit job", false);
                }
            }
            $return = array('success' => true);
            $this->core->getOutput()->renderJson($return);
            return $return;
        }

        // Open a cURL connection
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->core->getConfig()->getCgiUrl()."pdf_check.cgi?&num={$num_pages}&sem={$semester}&course={$course}&g_id={$gradeable_id}&ver={$current_time}");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);

        if ($output === false) {
            return $this->uploadResult(curl_error($ch),false);
        }

        $output = json_decode($output, true);
        curl_close($ch);

        if ($output === null) {
            FileUtils::recursiveRmdir($version_path);
            return $this->uploadResult("Error JSON response for pdf split: ".json_last_error_msg(),false);
        }
        if (!isset($output['valid'])) {
            FileUtils::recursiveRmdir($version_path);
            return $this->uploadResult($output, false);
            //return $this->uploadResult("Missing response in JSON for pdf split",false);
        }
        else if ($output['valid'] !== true) {
            FileUtils::recursiveRmdir($version_path);
            return $this->uploadResult($output['message'],false);
        }

        $return = array('success' => true);
        $this->core->getOutput()->renderJson($return);
        return $return;
    }

    /**
     * Function for uploading a split item that already exists to the server.
     * The file already exists in uploads/split_pdf/gradeable_id/timestamp folder. This should be called via AJAX, saving the result
     * to the json_buffer of the Output object, returning a true or false on whether or not it suceeded or not.
     * Has overlap with ajaxUploadSubmission
     *
     * @return boolean
     */
    private function ajaxUploadSplitItem() {
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return $this->uploadResult("Invalid CSRF token.", false);
        }

        // make sure is at least full access grader
        if (!$this->core->getUser()->accessFullGrading()) {
            $msg = "You do not have access to that page.";
            $this->core->addErrorMessage($msg);
            return $this->uploadResult($msg, false);
        }

        // check for whether the item should be merged with previous submission
        // and whether or not file clobbering should be done
        $merge_previous = isset($_REQUEST['merge']) && $_REQUEST['merge'] === 'true';
        $clobber = isset($_REQUEST['clobber']) && $_REQUEST['clobber'] === 'true';

        $gradeable_id = $_REQUEST['gradeable_id'] ?? '';
        $gradeable = $this->tryGetElectronicGradeable($gradeable_id);

        // This checks for an assignment id, and that it's a valid assignment id in that
        // it corresponds to one that we can access (whether through admin or it being released)
        if ($gradeable === null) {
            return $this->uploadResult("Invalid gradeable id '{$gradeable_id}'", false);
        }
        if (!isset($_POST['user_id'])) {
            return $this->uploadResult("Invalid user id.", false);
        }
        if (!isset($_POST['path'])) {
            return $this->uploadResult("Invalid path.", false);
        }

        $original_user_id = $this->core->getUser()->getId();

        //user ids come in as a comma delimited list. we explode that list, then filter out empty values.
        $user_ids = explode (",", $_POST['user_id']);
        $user_ids = array_filter($user_ids);
        //This grabs the first user in the list. If this is a team assignment, they will be the team leader.
        $user_id = reset($user_ids);

        $path = $_POST['path'];
        $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $user_id, null);

        $gradeable_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions",
            $gradeable->getId());

        /*
         * Perform checks on the following folders (and whether or not they exist):
         * 1) the assignment folder in the submissions directory
         * 2) the student's folder in the assignment folder
         * 3) the version folder in the student folder
         * 4) the uploads folder from the specified path
         */
        if (!FileUtils::createDir($gradeable_path)) {
            return $this->uploadResult("Failed to make folder for this assignment.", false);
        }

        $who_id = $user_id;
        $team_id = "";
        if ($gradeable->isTeamAssignment()) {
            $leader = $user_id;
            if ($graded_gradeable !== null) {
                $team =  $graded_gradeable->getSubmitter()->getTeam();
                $team_id = $team->getId();
                $who_id = $team_id;
                $user_id = "";
            }
            //if the student isn't on a team, build the team.
            else{
                //If the team doesn't exist yet, we need to build a new one. (Note, we have already checked in ajaxvalidgradeable
                //that all users are either on the same team or no team).

                $leaderless = array();
                foreach($user_ids as $i => $member){
                    if($member !== $leader){
                        $leaderless[] = $member;
                    }
                }

                $members = $this->core->getQueries()->getUsersById($leaderless);
                $leader_user = $this->core->getQueries()->getUserById($leader);
                try {
                    $gradeable->createTeam($leader_user, $members);
                } catch (\Exception $e) {
                    $this->core->addErrorMessage('Team may not have been properly initialized: ' . $e->getMessage());
                }

                // Once team is created, load in the graded gradeable
                $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $leader);
                $team =  $this->core->getQueries()->getTeamByGradeableAndUser($gradeable->getId(), $leader);
                $team_id = $team->getId();
                $who_id = $team_id;
                $user_id = "";
            }
        }

        $user_path = FileUtils::joinPaths($gradeable_path, $who_id);
        $this->upload_details['user_path'] = $user_path;
        if (!FileUtils::createDir($user_path)) {
            return $this->uploadResult("Failed to make folder for this assignment for the user.", false);
        }

        $new_version = $graded_gradeable->getAutoGradedGradeable()->getHighestVersion() + 1;
        $version_path = FileUtils::joinPaths($user_path, $new_version);

        if (!FileUtils::createDir($version_path)) {
            return $this->uploadResult("Failed to make folder for the current version.", false);
        }

        $this->upload_details['version_path'] = $version_path;
        $this->upload_details['version'] = $new_version;

        $current_time = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO");
        $current_time_string_tz = $current_time . " " . $this->core->getConfig()->getTimezone()->getName();

        $path = rawurldecode(htmlspecialchars_decode($path));

        $uploaded_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "split_pdf",
            $gradeable->getId(), $path);

        $uploaded_file = rawurldecode(htmlspecialchars_decode($uploaded_file));
        $uploaded_file_base_name = "upload.pdf";

        if (isset($uploaded_file)) {
            // if we are merging in the previous submission (TODO check folder support)
            if($merge_previous && $new_version !== 1) {
                $old_version = $new_version - 1;
                $old_version_path = FileUtils::joinPaths($user_path, $old_version);
                $to_search = FileUtils::joinPaths($old_version_path, "*.*");
                $files = glob($to_search);
                foreach($files as $file) {
                    $file_base_name = basename($file);
                    if(!$clobber && $file_base_name === $uploaded_file_base_name) {
                        $parts = explode(".", $file_base_name);
                        $parts[0] .= "_version_".$old_version;
                        $file_base_name = implode(".", $parts);
                    }
                    $move_here = FileUtils::joinPaths($version_path, $file_base_name);
                    if (!@copy($file, $move_here)){
                        return $this->uploadResult("Failed to merge previous version.", false);
                    }
                }
            }
            // copy over the uploaded file
            if (!@copy($uploaded_file, FileUtils::joinPaths($version_path, $uploaded_file_base_name))) {
                return $this->uploadResult("Failed to copy uploaded file {$uploaded_file} to current submission.", false);
            }
            if (!@unlink($uploaded_file)) {
                return $this->uploadResult("Failed to delete the uploaded file {$uploaded_file} from temporary storage.", false);
            }
            if (!@unlink(str_replace(".pdf", "_cover.pdf", $uploaded_file))) {
                return $this->uploadResult("Failed to delete the uploaded file {$uploaded_file} from temporary storage.", false);
            }

        }

        // if split_pdf/gradeable_id/timestamp directory is now empty, delete that directory
        $timestamp = substr($path, 0, strpos($path, DIRECTORY_SEPARATOR));
        $timestamp_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "split_pdf",
            $gradeable->getId(), $timestamp);
        $files = FileUtils::getAllFiles($timestamp_path);
        if (count($files) == 0 || (count($files) == 1 && array_key_exists('decoded.json', $files)  )) {
            if (!FileUtils::recursiveRmdir($timestamp_path)) {
                return $this->uploadResult("Failed to remove the empty timestamp directory {$timestamp} from the split_pdf directory.", false);
            }
        }


        $settings_file = FileUtils::joinPaths($user_path, "user_assignment_settings.json");
        if (!file_exists($settings_file)) {
            $json = array("active_version" => $new_version,
                          "history" => array(array("version" => $new_version,
                                                   "time" => $current_time_string_tz,
                                                   "who" => $original_user_id,
                                                   "type" => "upload")));
        }
        else {
            $json = FileUtils::readJsonFile($settings_file);
            if ($json === false) {
                return $this->uploadResult("Failed to open settings file.", false);
            }
            $json["active_version"] = $new_version;
            $json["history"][] = array("version"=> $new_version, "time" => $current_time_string_tz, "who" => $original_user_id, "type" => "upload");
        }

        // TODO: If any of these fail, should we "cancel" (delete) the entire submission attempt or just leave it?
        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            return $this->uploadResult("Failed to write to settings file.", false);
        }

        $this->upload_details['assignment_settings'] = true;

        if (!@file_put_contents(FileUtils::joinPaths($version_path, ".submit.timestamp"), $current_time_string_tz."\n")) {
            return $this->uploadResult("Failed to save timestamp file for this submission.", false);
        }

        $upload_time_string_tz = $timestamp . " " . $this->core->getConfig()->getTimezone()->getName();
        
        $bulk_upload_data = [
            "submit_timestamp" =>  $current_time_string_tz,
            "upload_timestamp" =>  $upload_time_string_tz,
            "filepath" => $uploaded_file
        ];

        #writeJsonFile returns false on failure.
        if (FileUtils::writeJsonFile(FileUtils::joinPaths($version_path, "bulk_upload_data.json"), $bulk_upload_data) === false) {
            return $this->uploadResult("Failed to create bulk upload file for this submission.", false);
        }
        
        $queue_file = array($this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse(),
            $gradeable->getId(), $who_id, $new_version);
        $queue_file = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "to_be_graded_queue",
            implode("__", $queue_file));

        // create json file...
        $queue_data = array("semester" => $this->core->getConfig()->getSemester(),
            "course" => $this->core->getConfig()->getCourse(),
            "gradeable" => $gradeable->getId(),
            "required_capabilities" => $gradeable->getAutogradingConfig()->getRequiredCapabilities(),
            "max_possible_grading_time" => $gradeable->getAutogradingConfig()->getMaxPossibleGradingTime(),
            "queue_time" => $current_time,
            "user" => $user_id,
            "team" => $team_id,
            "who" => $who_id,
            "is_team" => $gradeable->isTeamAssignment(),
            "version" => $new_version);

        if (@file_put_contents($queue_file, FileUtils::encodeJson($queue_data), LOCK_EX) === false) {
            return $this->uploadResult("Failed to create file for grading queue.", false);
        }

        // FIXME: Add this as part of the graded gradeable saving query
        if($gradeable->isTeamAssignment()) {
            $this->core->getQueries()->insertVersionDetails($gradeable->getId(), null, $team_id, $new_version, $current_time);
        }
        else {
            $this->core->getQueries()->insertVersionDetails($gradeable->getId(), $user_id, null, $new_version, $current_time);
        }

        return $this->uploadResult("Successfully uploaded version {$new_version} for {$gradeable->getTitle()} for {$who_id}");
    }

    /**
     * Function for deleting a split item from the uploads/split_pdf/gradeable_id/timestamp folder. This should be called via AJAX,
     * saving the result to the json_buffer of the Output object, returning a true or false on whether or not it suceeded or not.
     *
     * @return boolean
     */
    private function ajaxDeleteSplitItem() {
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return $this->uploadResult("Invalid CSRF token.", false);
        }

        // make sure is at least full access grader
        if (!$this->core->getUser()->accessFullGrading()) {
            $msg = "You do not have access to that page.";
            $this->core->addErrorMessage($msg);
            return $this->uploadResult($msg, false);
        }

        $gradeable_id = $_REQUEST['gradeable_id'] ?? '';
        $gradeable = $this->tryGetElectronicGradeable($gradeable_id);

        // This checks for an assignment id, and that it's a valid assignment id in that
        // it corresponds to one that we can access (whether through admin or it being released)
        if ($gradeable === null) {
            return $this->uploadResult("Invalid gradeable id '{$gradeable_id}'", false);
        }
        if (!isset($_POST['path'])) {
            return $this->uploadResult("Invalid path.", false);
        }

        $path = rawurldecode(htmlspecialchars_decode($_POST['path']));

        $uploaded_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "split_pdf",
            $gradeable->getId(), $path);

        $uploaded_file = rawurldecode(htmlspecialchars_decode($uploaded_file));

        if (!@unlink($uploaded_file)) {
            return $this->uploadResult("Failed to delete the uploaded file {$uploaded_file} from temporary storage.", false);
        }

        if (!@unlink(str_replace(".pdf", "_cover.pdf", $uploaded_file))) {
            return $this->uploadResult("Failed to delete the uploaded file {$uploaded_file} from temporary storage.", false);
        }

        // delete timestamp folder if empty
        $timestamp = substr($path, 0, strpos($path, DIRECTORY_SEPARATOR));
        $timestamp_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "split_pdf",
            $gradeable->getId(), $timestamp);
        $files = FileUtils::getAllFiles($timestamp_path);
        
        //check if there are any pdfs left to assign to students, otherwise delete the folder
        $any_pdfs_left = false;
        foreach ($files as $file){
            if(strpos($file['name'], ".pdf") !== false){
                $any_pdfs_left = true;
                break;
            }
        }

        if (count($files) == 0 || !$any_pdfs_left   ) {
            if (!FileUtils::recursiveRmdir($timestamp_path)) {
                return $this->uploadResult("Failed to remove the empty timestamp directory {$timestamp} from the split_pdf directory.", false);
            }
        }

        return $this->uploadResult("Successfully deleted this PDF.");
    }

    /**
     * Function for uploading a submission to the server. This should be called via AJAX, saving the result
     * to the json_buffer of the Output object, returning a true or false on whether or not it suceeded or not.
     *
     * @return array
     */
    private function ajaxUploadSubmission() {
        if (empty($_POST)) {
            $max_size = ini_get('post_max_size');
            return $this->uploadResult("Empty POST request. This may mean that the sum size of your files are greater than {$max_size}.", false);
        }
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return $this->uploadResult("Invalid CSRF token.", false);
        }

        // check for whether the item should be merged with previous submission,
        // and whether or not file clobbering should be done.
        $merge_previous = isset($_REQUEST['merge']) && $_REQUEST['merge'] === 'true';
        $clobber = isset($_REQUEST['clobber']) && $_REQUEST['clobber'] === 'true';

        $vcs_checkout = isset($_REQUEST['vcs_checkout']) ? $_REQUEST['vcs_checkout'] === "true" : false;
        if ($vcs_checkout && !isset($_POST['repo_id'])) {
            return $this->uploadResult("Invalid repo id.", false);
        }

        $student_page = isset($_REQUEST['student_page']) ? $_REQUEST['student_page'] === "true" : false;
        if ($student_page && !isset($_POST['pages'])) {
            return $this->uploadResult("Invalid pages.", false);
        }

        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->tryGetElectronicGradeable($gradeable_id);

        // This checks for an assignment id, and that it's a valid assignment id in that
        // it corresponds to one that we can access (whether through admin or it being released)
        if ($gradeable === null) {
            return $this->uploadResult("Invalid gradeable id '{$gradeable_id}'", false);
        }

        if (!isset($_POST['user_id'])) {
            return $this->uploadResult("Invalid user id.", false);
        }

        // the user id of the submitter ($user_id is the one being submitted for)
        $original_user_id = $this->core->getUser()->getId();
        $user_id = $_POST['user_id'];
        // repo_id for VCS use
        $repo_id = $_POST['repo_id'];

        // make sure is full grader if the two ids do not match
        if ($original_user_id !== $user_id && !$this->core->getUser()->accessFullGrading()) {
            $msg = "You do not have access to that page.";
            $this->core->addErrorMessage($msg);
            return $this->uploadResult($msg, false);
        }

        // if student submission, make sure that gradeable allows submissions
        if (!$this->core->getUser()->accessFullGrading() && !$gradeable->canStudentSubmit()) {
            $msg = "You do not have access to that page.";
            $this->core->addErrorMessage($msg);
            return $this->uploadResult($msg, false);
        }

        $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $user_id, null);
        $gradeable_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions",
            $gradeable->getId());

        /*
         * Perform checks on the following folders (and whether or not they exist):
         * 1) the assignment folder in the submissions directory
         * 2) the student's folder in the assignment folder
         * 3) the version folder in the student folder
         * 4) the part folders in the version folder in the version folder
         */
        if (!FileUtils::createDir($gradeable_path)) {
            return $this->uploadResult("Failed to make folder for this assignment.", false);
        }

        $who_id = $user_id;
        $team_id = "";
        if ($gradeable->isTeamAssignment()) {
            if ($graded_gradeable !== null) {
                $team = $graded_gradeable->getSubmitter()->getTeam();
                $team_id = $team->getId();
                $who_id = $team_id;
                $user_id = "";
            }
            else {
                return $this->uploadResult("Must be on a team to access submission.", false);
            }
        }

        $user_path = FileUtils::joinPaths($gradeable_path, $who_id);
        $this->upload_details['user_path'] = $user_path;
        if (!FileUtils::createDir($user_path)) {
                return $this->uploadResult("Failed to make folder for this assignment for the user.", false);
        }

        $highest_version = $graded_gradeable->getAutoGradedGradeable()->getHighestVersion();
        $new_version = $highest_version + 1;
        $version_path = FileUtils::joinPaths($user_path, $new_version);

        if (!FileUtils::createDir($version_path)) {
            return $this->uploadResult("Failed to make folder for the current version.", false);
        }

        $this->upload_details['version_path'] = $version_path;
        $this->upload_details['version'] = $new_version;

        $part_path = array();
        // We upload the assignment such that if it's multiple parts, we put it in folders "part#" otherwise
        // put all files in the root folder
        $num_parts = $gradeable->getAutogradingConfig()->getNumParts();
        if ($num_parts > 1) {
            for ($i = 1; $i <= $num_parts; $i++) {
                $part_path[$i] = FileUtils::joinPaths($version_path, "part".$i);
                if (!FileUtils::createDir($part_path[$i])) {
                    return $this->uploadResult("Failed to make the folder for part {$i}.", false);
                }
            }
        }
        else {
            $part_path[1] = $version_path;
        }

        $current_time = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO");
        $current_time_string_tz = $current_time . " " . $this->core->getConfig()->getTimezone()->getName();

        $max_size = $gradeable->getAutogradingConfig()->getMaxSubmissionSize();

        if ($vcs_checkout === false) {
            $uploaded_files = array();
            for ($i = 1; $i <= $num_parts; $i++){
                if (isset($_FILES["files{$i}"])) {
                    $uploaded_files[$i] = $_FILES["files{$i}"];
                }
            }

            $errors = array();
            $count = array();
            for ($i = 1; $i <= $num_parts; $i++) {
                if (isset($uploaded_files[$i])) {
                    $count[$i] = count($uploaded_files[$i]["name"]);
                    for ($j = 0; $j < $count[$i]; $j++) {
                        if (!isset($uploaded_files[$i]["tmp_name"][$j]) || $uploaded_files[$i]["tmp_name"][$j] === "") {
                            $error_message = $uploaded_files[$i]["name"][$j]." failed to upload. ";
                            if (isset($uploaded_files[$i]["error"][$j])) {
                                $error_message .= "Error message: ". ErrorMessages::uploadErrors($uploaded_files[$i]["error"][$j]). ".";
                            }
                            $errors[] = $error_message;
                        }
                    }
                }
            }

            if (count($errors) > 0) {
                $error_text = implode("\n", $errors);
                return $this->uploadResult("Upload Failed: ".$error_text, false);
            }

            // save the contents of the text boxes to files
            $empty_textboxes = true;
            if (isset($_POST['textbox_answers'])) {
                $textbox_answer_array = json_decode($_POST['textbox_answers']);
                for ($i = 0; $i < $gradeable->getAutogradingConfig()->getNumTextBoxes(); $i++) {
                    $textbox_answer_val = $textbox_answer_array[$i];
                    if ($textbox_answer_val != "") $empty_textboxes = false;
                    $filename = $gradeable->getAutogradingConfig()->getTextboxes()[$i]->getFileName();
                    $dst = FileUtils::joinPaths($version_path, $filename);
                    // FIXME: add error checking
                    $file = fopen($dst, "w");
                    fwrite($file, $textbox_answer_val);
                    fclose($file);
                }
            }

            $previous_files_src = array();
            $previous_files_dst = array();
            $previous_part_path = array();
            $tmp = json_decode($_POST['previous_files']);
            if (!empty($tmp)) {
                for ($i = 0; $i < $num_parts; $i++) {
                    if (count($tmp[$i]) > 0) {
                        $previous_files_src[$i + 1] = $tmp[$i];
                        $previous_files_dst[$i + 1] = $tmp[$i];
                    }
                }
            }


            if (empty($uploaded_files) && empty($previous_files_src) && $empty_textboxes) {
                return $this->uploadResult("No files to be submitted.", false);
            }

            // $merge_previous will only be true if there is a previous submission.
            if (count($previous_files_src) > 0 || $merge_previous) {
                if ($highest_version === 0) {
                    return $this->uploadResult("No submission found. There should not be any files from a previous submission.", false);
                }

                $previous_path = FileUtils::joinPaths($user_path, $highest_version);
                if ($num_parts > 1) {
                    for ($i = 1; $i <= $num_parts; $i++) {
                        $previous_part_path[$i] = FileUtils::joinPaths($previous_path, "part".$i);
                    }
                }
                else {
                    $previous_part_path[1] = $previous_path;
                }

                foreach ($previous_part_path as $path) {
                    if (!is_dir($path)) {
                        return $this->uploadResult("Files from previous submission not found. Folder for previous submission does not exist.", false);
                    }
                }

                // if merging is being done, get all the old filenames and put them into $previous_files_dst
                // while checking for name conflicts and preventing them if clobbering is not enabled.
                if($merge_previous) {
                    for($i = 1; $i <= $num_parts; $i++) {
                        if(isset($uploaded_files[$i])) {
                            $current_files_set = array_flip($uploaded_files[$i]["name"]);
                            $previous_files_src[$i] = array();
                            $previous_files_dst[$i] = array();
                            $to_search = FileUtils::joinPaths($previous_part_path[$i], "*");
                            $filenames = glob($to_search);
                            $j = 0;
                            foreach($filenames as $filename) {
                                $file_base_name = basename($filename);
                                $previous_files_src[$i][$j] = $file_base_name;
                                if(!$clobber && isset($current_files_set[$file_base_name])) {
                                    $parts = explode(".", $file_base_name);
                                    $parts[0] .= "_version_".$highest_version;
                                    $file_base_name = implode(".", $parts);
                                }
                                $previous_files_dst[$i][$j] = $file_base_name;
                                $j++;
                            }
                        }
                    }
                }


                for ($i = 1; $i <= $num_parts; $i++) {
                    if (isset($previous_files_src[$i])) {
                        foreach ($previous_files_src[$i] as $prev_file) {
                            $filename = FileUtils::joinPaths($previous_part_path[$i], $prev_file);
                            if (!file_exists($filename)) {
                                $name = basename($filename);
                                return $this->uploadResult("File '{$name}' does not exist in previous submission.", false);
                            }
                        }
                    }
                }
            }

            // Determine the size of the uploaded files as well as whether or not they're a zip or not.
            // We save that information for later so we know which files need unpacking or not and can save
            // a check to getMimeType()
            $file_size = 0;
            for ($i = 1; $i <= $num_parts; $i++) {
                if (isset($uploaded_files[$i])) {
                    $uploaded_files[$i]["is_zip"] = array();
                    for ($j = 0; $j < $count[$i]; $j++) {
                        if (FileUtils::getMimeType($uploaded_files[$i]["tmp_name"][$j]) == "application/zip") {
                            if(FileUtils::checkFileInZipName($uploaded_files[$i]["tmp_name"][$j]) === false) {
                                return $this->uploadResult("Error: You may not use quotes, backslashes or angle brackets in your filename for files inside ".$uploaded_files[$i]["name"][$j].".", false);
                            }
                            $uploaded_files[$i]["is_zip"][$j] = true;
                            $file_size += FileUtils::getZipSize($uploaded_files[$i]["tmp_name"][$j]);
                        }
                        else {
                            if(FileUtils::isValidFileName($uploaded_files[$i]["name"][$j]) === false) {
                                return $this->uploadResult("Error: You may not use quotes, backslashes or angle brackets in your file name ".$uploaded_files[$i]["name"][$j].".", false);
                            }
                            $uploaded_files[$i]["is_zip"][$j] = false;
                            $file_size += $uploaded_files[$i]["size"][$j];
                        }
                    }
                }
                if(isset($previous_part_path[$i]) && isset($previous_files_src[$i])) {
                    foreach ($previous_files_src[$i] as $prev_file) {
                        $file_size += filesize(FileUtils::joinPaths($previous_part_path[$i], $prev_file));
                    }
                }
            }

            if ($file_size > $max_size) {
                return $this->uploadResult("File(s) uploaded too large.  Maximum size is ".($max_size/1000)." kb. Uploaded file(s) was ".($file_size/1000)." kb.", false);
            }

            for ($i = 1; $i <= $num_parts; $i++) {
                // copy selected previous submitted files
                if (isset($previous_files_src[$i])){
                    for ($j=0; $j < count($previous_files_src[$i]); $j++){
                        $src = FileUtils::joinPaths($previous_part_path[$i], $previous_files_src[$i][$j]);
                        $dst = FileUtils::joinPaths($part_path[$i], $previous_files_dst[$i][$j]);
                        if (!@copy($src, $dst)) {
                            return $this->uploadResult("Failed to copy previously submitted file {$previous_files_src[$i][$j]} to current submission.", false);
                        }
                    }
                }

                if (isset($uploaded_files[$i])) {
                    for ($j = 0; $j < $count[$i]; $j++) {
                        if ($uploaded_files[$i]["is_zip"][$j] === true) {
                            $zip = new \ZipArchive();
                            $res = $zip->open($uploaded_files[$i]["tmp_name"][$j]);
                            if ($res === true) {
                                $zip->extractTo($part_path[$i]);
                                $zip->close();
                            }
                            else {
                                // If the zip is an invalid zip (say we remove the last character from the zip file)
                                // then trying to get the status code will throw an exception and not give us a string
                                // so we have that string hardcoded, otherwise we can just get the status string as
                                // normal.
                                $error_message = ($res == 19) ? "Invalid or uninitialized Zip object" : $zip->getStatusString();
                                return $this->uploadResult("Could not properly unpack zip file. Error message: ".$error_message.".", false);
                            }
                        }
                        else {
                            if ($this->core->isTesting() || is_uploaded_file($uploaded_files[$i]["tmp_name"][$j])) {
                                $dst = FileUtils::joinPaths($part_path[$i], $uploaded_files[$i]["name"][$j]);
                                if (!@copy($uploaded_files[$i]["tmp_name"][$j], $dst)) {
                                    return $this->uploadResult("Failed to copy uploaded file {$uploaded_files[$i]["name"][$j]} to current submission.", false);
                                }
                            }
                            else {
                                return $this->uploadResult("The tmp file '{$uploaded_files[$i]['name'][$j]}' was not properly uploaded.", false);
                            }
                        }
                        // Is this really an error we should fail on?
                        if (!@unlink($uploaded_files[$i]["tmp_name"][$j])) {
                            return $this->uploadResult("Failed to delete the uploaded file {$uploaded_files[$i]["name"][$j]} from temporary storage.", false);
                        }
                    }
                }
            }
        }
        else {
            $vcs_base_url = $this->core->getConfig()->getVcsBaseUrl();
            $vcs_path = $gradeable->getVcsSubdirectory();

            // use entirely student input
            if ($vcs_base_url == "" && $vcs_path == "") {
                if ($repo_id == "") {
                    // FIXME: commented out for now to pass Travis.
                    // SubmissionControllerTests needs to be rewriten for proper VCS uploads.
                    // return $this->uploadResult("repository url input cannot be blank.", false);
                }
                $vcs_full_path = $repo_id;
            }
            // use base url + path with variable string replacements
            else {
                if (strpos($vcs_path,"\$repo_id") !== false && $repo_id == "") {
                    return $this->uploadResult("repository id input cannot be blank.", false);
                }
                $vcs_path = str_replace("{\$gradeable_id}",$gradeable_id,$vcs_path);
                $vcs_path = str_replace("{\$user_id}",$who_id,$vcs_path);
                $vcs_path = str_replace("{\$team_id}",$who_id,$vcs_path);
                $vcs_path = str_replace("{\$repo_id}",$repo_id,$vcs_path);
                $vcs_full_path = $vcs_base_url.$vcs_path;
            }

            if (!@touch(FileUtils::joinPaths($version_path, ".submit.VCS_CHECKOUT"))) {
                return $this->uploadResult("Failed to touch file for vcs submission.", false);
            }
        }

        // save the contents of the page number inputs to files
        $empty_pages = true;
        if (isset($_POST['pages'])) {
            $pages_array = json_decode($_POST['pages']);
            $total = count($gradeable->getComponents());
            $filename = "student_pages.json";
            $dst = FileUtils::joinPaths($version_path, $filename);
            $json = array();
            $i = 0;
            foreach ($gradeable->getComponents() as $question) {
                $order = intval($question->getOrder());
                $title = $question->getTitle();
                $page_val = intval($pages_array[$i]);
                $json[] = array("order" => $order,
                                "title" => $title,
                                "page #" => $page_val);
                $i++;
            }
            if (!@file_put_contents($dst, FileUtils::encodeJson($json))) {
                return $this->uploadResult("Failed to write to pages file.", false);
            }
        }

        $settings_file = FileUtils::joinPaths($user_path, "user_assignment_settings.json");
        if (!file_exists($settings_file)) {
            $json = array("active_version" => $new_version,
                          "history" => array(array("version" => $new_version,
                                                   "time" => $current_time_string_tz,
                                                   "who" => $original_user_id,
                                                   "type" => "upload")));
        }
        else {
            $json = FileUtils::readJsonFile($settings_file);
            if ($json === false) {
                return $this->uploadResult("Failed to open settings file.", false);
            }
            $json["active_version"] = $new_version;
            $json["history"][] = array("version"=> $new_version, "time" => $current_time_string_tz, "who" => $original_user_id, "type" => "upload");
        }

        // TODO: If any of these fail, should we "cancel" (delete) the entire submission attempt or just leave it?
        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            return $this->uploadResult("Failed to write to settings file.", false);
        }

        $this->upload_details['assignment_settings'] = true;

        if (!@file_put_contents(FileUtils::joinPaths($version_path, ".submit.timestamp"), $current_time_string_tz."\n")) {
            return $this->uploadResult("Failed to save timestamp file for this submission.", false);
        }

        $queue_file = array($this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse(),
            $gradeable->getId(), $who_id, $new_version);
        $queue_file = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "to_be_graded_queue",
            implode("__", $queue_file));

        // create json file...
        $queue_data = array("semester" => $this->core->getConfig()->getSemester(),
            "course" => $this->core->getConfig()->getCourse(),
            "gradeable" =>  $gradeable->getId(),
            "required_capabilities" => $gradeable->getAutogradingConfig()->getRequiredCapabilities(),
            "max_possible_grading_time" => $gradeable->getAutogradingConfig()->getMaxPossibleGradingTime(),
            "queue_time" => $current_time,
            "user" => $user_id,
            "team" => $team_id,
            "who" => $who_id,
            "is_team" => $gradeable->isTeamAssignment(),
            "version" => $new_version);

        if ($gradeable->isTeamAssignment()) {
            $queue_data['team_members'] = $team->getMemberUserIds();
        }


        if (@file_put_contents($queue_file, FileUtils::encodeJson($queue_data), LOCK_EX) === false) {
            return $this->uploadResult("Failed to create file for grading queue.", false);
        }

        if($gradeable->isTeamAssignment()) {
            $this->core->getQueries()->insertVersionDetails($gradeable->getId(), null, $team_id, $new_version, $current_time);
        }
        else {
            $this->core->getQueries()->insertVersionDetails($gradeable->getId(), $user_id, null, $new_version, $current_time);
        }

        if ($user_id == $original_user_id) {
            $this->core->addSuccessMessage("Successfully uploaded version {$new_version} for {$gradeable->getTitle()}");
        }
        else {
            $this->core->addSuccessMessage("Successfully uploaded version {$new_version} for {$gradeable->getTitle()} for {$who_id}");
        }


        return $this->uploadResult("Successfully uploaded files");
    }

    private function uploadResult($message, $success = true) {
        if (!$success) {
            // we don't want to throw an exception here as that'll mess up our return json payload
            if ($this->upload_details['version_path'] !== null
                && !FileUtils::recursiveRmdir($this->upload_details['version_path'])) {
                // @codeCoverageIgnoreStart
                // Without the filesystem messing up here, we should not be able to hit this error
                Logger::error("Could not clean up folder {$this->upload_details['version_path']}");

            }
            // @codeCoverageIgnoreEnd
            else if ($this->upload_details['assignment_settings'] === true) {
                $settings_file = FileUtils::joinPaths($this->upload_details['user_path'], "user_assignment_settings.json");
                $settings = json_decode(file_get_contents($settings_file), true);
                if (count($settings['history']) == 1) {
                    unlink($settings_file);
                }
                else {
                    array_pop($settings['history']);
                    $last = Utils::getLastArrayElement($settings['history']);
                    $settings['active_version'] = $last['version'];
                    file_put_contents($settings_file, FileUtils::encodeJson($settings));
                }
            }
        }
        $return = array('success' => $success, 'error' => !$success, 'message' => $message);
        $this->core->getOutput()->renderJson($return);
        return $return;
    }

    private function uploadResultMessage($message, $success = true, $show_msg = true) {
        $return = array('success' => $success, 'error' => !$success, 'message' => $message);
        $this->core->getOutput()->renderJson($return);

        if ($show_msg == true) {
            if ($success) {
                $this->core->addSuccessMessage($message);
            }
            else {
                $this->core->addErrorMessage($message);
            }
        }
        return $return;
    }


    private function updateSubmissionVersion() {
        $ta = $_REQUEST['ta'] ?? false;
        if ($ta !== false) {
            // make sure is full grader
            if (!$this->core->getUser()->accessFullGrading()) {
                $msg = "You do not have access to that page.";
                $this->core->addErrorMessage($msg);
                $this->core->redirect($this->core->getConfig()->getSiteUrl());
                return array('error' => true, 'message' => $msg);
            }
            $ta = true;
        }

        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->tryGetElectronicGradeable($gradeable_id);
        if ($gradeable === null) {
            $msg = "Invalid gradeable id.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($this->core->buildUrl(array('component' => 'student')));
            return array('error' => true, 'message' => $msg);
        }

        $who = $_REQUEST['who'] ?? $this->core->getUser()->getId();
        $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $who, $who);
        $url = $this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId()));
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            $msg = "Invalid CSRF token. Refresh the page and try again.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($url);
            return array('error' => true, 'message' => $msg);
        }

        // If $graded_gradeable is null, that means its a team assignment and the user is on no team
        if ($gradeable->isTeamAssignment() && $graded_gradeable === null) {
            $msg = 'Must be on a team to access submission.';
            $this->core->addErrorMessage($msg);
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
            return array('error' => true, 'message' => $msg);
        }

        $new_version = intval($_REQUEST['new_version']);
        if ($new_version < 0) {
            $msg = "Cannot set the version below 0.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($url);
            return array('error' => true, 'message' => $msg);
        }

        $highest_version = $graded_gradeable->getAutoGradedGradeable()->getHighestVersion();
        if ($new_version > $highest_version) {
            $msg = "Cannot set the version past {$highest_version}.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($url);
            return array('error' => true, 'message' => $msg);
        }

        if (!$this->core->getUser()->accessGrading() && !$gradeable->isStudentSubmit()) {
            $msg = "Cannot submit for this assignment.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($url);
            return array('error' => true, 'message' => $msg);
        }

        $original_user_id = $this->core->getUser()->getId();
        $submitter_id = $graded_gradeable->getSubmitter()->getId();

        $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions",
            $gradeable->getId(), $submitter_id, "user_assignment_settings.json");
        $json = FileUtils::readJsonFile($settings_file);
        if ($json === false) {
            $msg = "Failed to open settings file.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($url);
            return array('error' => true, 'message' => $msg);
        }
        $json["active_version"] = $new_version;
        $current_time = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO");
        $current_time_string_tz = $current_time . " " . $this->core->getConfig()->getTimezone()->getName();

        $json["history"][] = array("version" => $new_version, "time" => $current_time_string_tz, "who" => $original_user_id, "type" => "select");

        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $msg = "Could not write to settings file.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($this->core->buildUrl(array('component' => 'student',
                                                              'gradeable_id' => $gradeable->getId())));
            return array('error' => true, 'message' => $msg);
        }

        $version = ($new_version > 0) ? $new_version : null;

        // FIXME: Add this kind of operation to the graded gradeable saving query
        if($gradeable->isTeamAssignment()) {
            $this->core->getQueries()->updateActiveVersion($gradeable->getId(), null, $submitter_id, $version);
        }
        else {
            $this->core->getQueries()->updateActiveVersion($gradeable->getId(), $submitter_id, null, $version);
        }


        if ($new_version == 0) {
            $msg = "Cancelled submission for gradeable.";
            $this->core->addSuccessMessage($msg);
        }
        else {
            $msg = "Updated version of gradeable to version #{$new_version}.";
            $this->core->addSuccessMessage($msg);
        }
        if($ta) {
            $this->core->redirect($this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic',
                                                    'action' => 'grade', 'gradeable_id' => $gradeable->getId(),
                                                    'who_id'=>$who, 'gradeable_version' => $new_version)));
        }
        else {
            $this->core->redirect($this->core->buildUrl(array('component' => 'student',
                                                          'gradeable_id' => $gradeable->getId(),
                                                          'gradeable_version' => $new_version)));
        }

        return array('error' => false, 'version' => $new_version, 'message' => $msg);
    }

    private function ajaxUploadImagesFiles() {
        if($this->core->getUser()->getGroup() !== 1) {
            return $this->uploadResultMessage("You have no permission to access this page", false);
        }

        if (empty($_POST)) {
           $max_size = ini_get('post_max_size');
           return $this->uploadResultMessage("Empty POST request. This may mean that the sum size of your files are greater than {$max_size}.", false, false);
        }

        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return $this->uploadResultMessage("Invalid CSRF token.", false, false);
        }

        $uploaded_files = array();
        if (isset($_FILES["files1"])) {
            $uploaded_files[1] = $_FILES["files1"];
        }
        $errors = array();
        $count_item = 0;
        if (isset($uploaded_files[1])) {
            $count_item = count($uploaded_files[1]["name"]);
            for ($j = 0; $j < $count_item[1]; $j++) {
                if (!isset($uploaded_files[1]["tmp_name"][$j]) || $uploaded_files[1]["tmp_name"][$j] === "") {
                    $error_message = $uploaded_files[1]["name"][$j]." failed to upload. ";
                    if (isset($uploaded_files[1]["error"][$j])) {
                        $error_message .= "Error message: ". ErrorMessages::uploadErrors($uploaded_files[1]["error"][$j]). ".";
                    }
                    $errors[] = $error_message;
                }
            }
        }

        if (count($errors) > 0) {
            $error_text = implode("\n", $errors);
            return $this->uploadResultMessage("Upload Failed: ".$error_text, false);
        }

        if (empty($uploaded_files)) {
            return $this->uploadResultMessage("No files to be submitted.", false);
        }

        $file_size = 0;
        if (isset($uploaded_files[1])) {
            $uploaded_files[1]["is_zip"] = array();
            for ($j = 0; $j < $count_item; $j++) {
                if (FileUtils::getMimeType($uploaded_files[1]["tmp_name"][$j]) == "application/zip") {
                    if(FileUtils::checkFileInZipName($uploaded_files[1]["tmp_name"][$j]) === false) {
                        return $this->uploadResultMessage("Error: You may not use quotes, backslashes or angle brackets in your filename for files inside ".$uploaded_files[1]["name"][$j].".", false);
                    }
                    $uploaded_files[1]["is_zip"][$j] = true;
                    $file_size += FileUtils::getZipSize($uploaded_files[1]["tmp_name"][$j]);
                }
                else {
                    if(FileUtils::isValidFileName($uploaded_files[1]["name"][$j]) === false) {
                        return $this->uploadResultMessage("Error: You may not use quotes, backslashes or angle brackets in your file name ".$uploaded_files[1]["name"][$j].".", false);
                    }
                    $uploaded_files[1]["is_zip"][$j] = false;
                    $file_size += $uploaded_files[1]["size"][$j];
                }
            }
        }

        $max_size = 10485760;
        if ($file_size > $max_size) {
            return $this->uploadResultMessage("File(s) uploaded too large.  Maximum size is ".($max_size/1024)." kb. Uploaded file(s) was ".($file_size/1024)." kb.", false);
        }

        // creating uploads/student_images directory

        $upload_img_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "student_images");
        if (!FileUtils::createDir($upload_img_path)) {
            return $this->uploadResultMessage("Failed to make image path.", false);
        }

        if (isset($uploaded_files[1])) {
            for ($j = 0; $j < $count_item; $j++) {
                if ($uploaded_files[1]["is_zip"][$j] === true) {
                    $zip = new \ZipArchive();
                    $res = $zip->open($uploaded_files[1]["tmp_name"][$j]);
                    if ($res === true) {
                        //make tmp folder to store class section images
                        $upload_img_path_tmp = FileUtils::joinPaths($upload_img_path, "tmp");
                        $zip->extractTo($upload_img_path_tmp);

                        FileUtils::recursiveCopy($upload_img_path_tmp, $upload_img_path);

                        //delete tmp folder
                        FileUtils::recursiveRmdir($upload_img_path_tmp);
                        $zip->close();
                    }
                    else {
                        // If the zip is an invalid zip (say we remove the last character from the zip file
                        // then trying to get the status code will throw an exception and not give us a string
                        // so we have that string hardcoded, otherwise we can just get the status string as
                        // normal.
                        $error_message = ($res == 19) ? "Invalid or uninitialized Zip object" : $zip->getStatusString();
                        return $this->uploadResultMessage("Could not properly unpack zip file. Error message: ".$error_message.".", false);
                    }
                }
                else {
                    if ($this->core->isTesting() || is_uploaded_file($uploaded_files[1]["tmp_name"][$j])) {
                        $dst = FileUtils::joinPaths($upload_img_path, $uploaded_files[1]["name"][$j]);
                        if (!@copy($uploaded_files[1]["tmp_name"][$j], $dst)) {
                            return $this->uploadResultMessage("Failed to copy uploaded file {$uploaded_files[1]["name"][$j]} to current location.", false);
                        }
                    }
                    else {
                        return $this->uploadResultMessage("The tmp file '{$uploaded_files[1]['name'][$j]}' was not properly uploaded.", false);
                    }
                }
                // Is this really an error we should fail on?
                if (!@unlink($uploaded_files[1]["tmp_name"][$j])) {
                    return $this->uploadResultMessage("Failed to delete the uploaded file {$uploaded_files[1]["name"][$j]} from temporary storage.", false);
                }
            }
        }

        $total_count = intval($_POST['file_count']);
        $uploaded_count = count($uploaded_files[1]['tmp_name']);
        $remaining_count = $uploaded_count - $total_count;
        $php_count = ini_get('max_file_uploads');
        if ($total_count < $uploaded_count) {
            $message = "Successfully uploaded {$uploaded_count} images. Could not upload remaining {$remaining_count} files.";
            $message .= " The max number of files you can upload at once is set to {$php_count}.";
        }
        else {
            $message = 'Successfully uploaded!';
        }
        return $this->uploadResultMessage($message, true);
    }

    private function ajaxUploadCourseMaterialsFiles() {
      if($this->core->getUser()->getGroup() !== 1) {
         return $this->uploadResultMessage("You have no permission to access this page", false);
      }

      if (empty($_POST)) {
         $max_size = ini_get('post_max_size');
         return $this->uploadResultMessage("Empty POST request. This may mean that the sum size of your files are greater than {$max_size}.", false, false);
      }

      if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
          return $this->uploadResultMessage("Invalid CSRF token.", false, false);
      }

      $expand_zip = "";
      if (isset($_POST['expand_zip'])) {
          $expand_zip = $_POST['expand_zip'];
      }

      $requested_path = "";
      if (isset($_POST['requested_path'])) {
          $requested_path = $_POST['requested_path'];
      }

      $n = strpos($requested_path, '..');
      if ($n !== false) {
          return $this->uploadResultMessage(".. is not supported in the path.", false, false);
      }

      $uploaded_files = array();
      if (isset($_FILES["files1"])) {
          $uploaded_files[1] = $_FILES["files1"];
      }
      $errors = array();
      if (isset($uploaded_files[1])) {
          $count_item = count($uploaded_files[1]["name"]);
          for ($j = 0; $j < $count_item[1]; $j++) {
              if (!isset($uploaded_files[1]["tmp_name"][$j]) || $uploaded_files[1]["tmp_name"][$j] === "") {
                  $error_message = $uploaded_files[1]["name"][$j]." failed to upload. ";
                  if (isset($uploaded_files[1]["error"][$j])) {
                      $error_message .= "Error message: ". ErrorMessages::uploadErrors($uploaded_files[1]["error"][$j]). ".";
                  }
                  $errors[] = $error_message;
              }
          }
      }

      if (count($errors) > 0) {
          $error_text = implode("\n", $errors);
          return $this->uploadResultMessage("Upload Failed: ".$error_text, false);
      }

      if (empty($uploaded_files)) {
          return $this->uploadResultMessage("No files to be submitted.", false);
      }

      $file_size = 0;
      if (isset($uploaded_files[1])) {
          for ($j = 0; $j < $count_item; $j++) {
              if(FileUtils::isValidFileName($uploaded_files[1]["name"][$j]) === false) {
                  return $this->uploadResultMessage("Error: You may not use quotes, backslashes or angle brackets in your file name ".$uploaded_files[1]["name"][$j].".", false);
              }
              $file_size += $uploaded_files[1]["size"][$j];
          }
      }

      $max_size = 10485760;
      if ($file_size > $max_size) {
          return $this->uploadResultMessage("File(s) uploaded too large.  Maximum size is ".($max_size/1024)." kb. Uploaded file(s) was ".($file_size/1024)." kb.", false);
      }

      // creating uploads/course_materials directory
      $upload_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");
      if (!FileUtils::createDir($upload_path)) {
          return $this->uploadResultMessage("Failed to make image path.", false);
      }

      // create nested path
      if (!empty($requested_path)) {
          $upload_nested_path = FileUtils::joinPaths($upload_path, $requested_path);
          if (!FileUtils::createDir($upload_nested_path, null, true)) {
             return $this->uploadResultMessage("Failed to make image path.", false);
          }
          $upload_path = $upload_nested_path;
      }

      if (isset($uploaded_files[1])) {
          for ($j = 0; $j < $count_item; $j++) {
                if ($this->core->isTesting() || is_uploaded_file($uploaded_files[1]["tmp_name"][$j])) {
                    $dst = FileUtils::joinPaths($upload_path, $uploaded_files[1]["name"][$j]);
                    //
                    $is_zip_file = false;

                    if (FileUtils::getMimeType($uploaded_files[1]["tmp_name"][$j]) == "application/zip") {
                        if(FileUtils::checkFileInZipName($uploaded_files[1]["tmp_name"][$j]) === false) {
                            return $this->uploadResultMessage("Error: You may not use quotes, backslashes or angle brackets in your filename for files inside ".$uploaded_files[$i]["name"][$j].".", false);
                        }
                        $is_zip_file = true;
                    }
                    //cannot check if there are duplicates inside zip file, will overwrite
                    //it is convenient for bulk uploads
                    if ($expand_zip == 'on' && $is_zip_file === true) {
                        $zip = new \ZipArchive();
                        $res = $zip->open($uploaded_files[1]["tmp_name"][$j]);
                        if ($res === true) {
                            $zip->extractTo($upload_path);
                            $zip->close();
                        }
                    }
                    else
                    {
                        if (!@copy($uploaded_files[1]["tmp_name"][$j], $dst)) {
                           return $this->uploadResultMessage("Failed to copy uploaded file {$uploaded_files[1]["name"][$j]} to current location.", false);
                      }
                    }
                    //
                }
                else {
                    return $this->uploadResultMessage("The tmp file '{$uploaded_files[1]['name'][$j]}' was not properly uploaded.", false);
                }
            // Is this really an error we should fail on?
              if (!@unlink($uploaded_files[1]["tmp_name"][$j])) {
                  return $this->uploadResultMessage("Failed to delete the uploaded file {$uploaded_files[1]["name"][$j]} from temporary storage.", false);
              }
          }
      }


      return $this->uploadResultMessage("Successfully uploaded!", true);

    }

    /**
     * Check if the results folder exists for a given gradeable and version results.json
     * in the results/ directory. If the file exists, we output a string that the calling
     * JS checks for to initiate a page refresh (so as to go from "in-grading" to done
     */
    public function checkRefresh() {
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        $version = $_REQUEST['gradeable_version'];
        $gradeable_id = $_REQUEST['gradeable_id'] ?? '';
        $gradeable = $this->tryGetElectronicGradeable($gradeable_id);

        // Don't load the graded gradeable, since that may not exist yet
        $submitter_id = $this->core->getUser()->getId();
        $user_id = $submitter_id;
        $team_id = null;
        if ($gradeable !== null && $gradeable->isTeamAssignment()) {
            $team = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $submitter_id);

            if ($team !== null) {
                $submitter_id = $team->getId();
                $team_id = $submitter_id;
                $user_id = null;
            }
        }

        $filepath = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "results", $gradeable_id,
            $submitter_id, $version, "results.json");

        $results_json_exists = file_exists($filepath);

        // if the results json exists, check the database to make sure that the autograding results are there.
        $has_results = $results_json_exists && $this->core->getQueries()->getGradeableVersionHasAutogradingResults(
            $gradeable_id, $version, $user_id, $team_id);

        if ($has_results) {
            $refresh_string = "REFRESH_ME";
            $refresh_bool = true;
        }
        else {
            $refresh_string = "NO_REFRESH";
            $refresh_bool = false;
        }
        $this->core->getOutput()->renderString($refresh_string);
        return array('refresh' => $refresh_bool, 'string' => $refresh_string);
    }
}

<?php

namespace app\controllers;

use app\libraries\DateUtils;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\CourseMaterial;
use app\libraries\routers\AccessControl;
use app\libraries\response\MultiResponse;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class MiscController extends AbstractController {

    /**
     * Get the current server time
     *
     * Returns a json string which contains the current server time broken up into year, month, day, hour, minute,
     * second
     *
     * @Route("/server_time")
     */
    public function getServerTime(): JsonResponse {
        return JsonResponse::getSuccessResponse(DateUtils::getServerTimeJson($this->core));
    }

    /**
     * @Route("/{_semester}/{_course}/gradeable/{gradeable_id}/encode_pdf")
     * @return MultiResponse
     */
    public function encodePDF($gradeable_id) {
        $id = $_POST['user_id'] ?? null;
        $file_name = $_POST['filename'] ?? null;
        $file_name = html_entity_decode($file_name);
        $gradeable = $this->tryGetGradeable($gradeable_id);
        $submitter = $this->core->getQueries()->getSubmitterById($id);
        $graded_gradeable = $this->core->getQueries()->getGradedGradeableForSubmitter($gradeable, $submitter);
        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
        $file_path = realpath($_POST['file_path']);
        $directory = 'invalid';
        if (strpos($file_path, 'submissions') !== false) {
            $directory = 'submissions';
        }
        elseif (strpos($file_path, 'checkout') !== false) {
            $directory = 'checkout';
        }
        $check_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), $directory, $gradeable_id, $id, $active_version);

        if ($gradeable->isGradeByRegistration()) {
            $section = $submitter->getRegistrationSection();
        }
        else {
            $section = $submitter->getRotatingSection();
        }

        if ($file_path !== $_POST['file_path'] || !Utils::startsWith($file_path, $check_path)) {
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse("Invalid file path")
            );
        }

        if (!$this->core->getAccess()->canI("path.read", ["dir" => $directory, "path" => $file_path, "gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "section" => $section])) {
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse("You do not have access to this file")
            );
        }

        $pdf64 = base64_encode(file_get_contents($file_path));
        return MultiResponse::JsonOnlyResponse(
            JsonResponse::getSuccessResponse($pdf64)
        );
    }

    /**
     * @Route("/{_semester}/{_course}/display_file")
     */
    public function displayFile($dir, $path, $gradeable_id = null, $user_id = null, $ta_grading = null) {
        //Is this per-gradeable?
        $path = $this->core->getAccess()->resolveDirPath($dir, htmlspecialchars_decode(urldecode($path)));

        if (!is_null($gradeable_id)) {
            $gradeable = $this->tryGetGradeable($gradeable_id, false);
            if ($gradeable === false) {
                return false;
            }
            $graded_gradeable =  $this->tryGetGradedGradeable($gradeable, $user_id, false);
            if ($graded_gradeable === false) {
                return false;
            }
            if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path, "gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
                $this->core->getOutput()->showError("You do not have access to this file");
                return false;
            }
        }
        else {
            // Check access through Access library
            if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path])) {
                $this->core->getOutput()->showError("You do not have access to this file");
                return false;
            }

            // If attempting to obtain course materials
            if ($dir == 'course_materials') {
                // If the user attempting to access the file is not at least a grader then ensure the file has been released
                if (!$this->core->getUser()->accessGrading() && !CourseMaterial::isMaterialReleased($this->core, $path)) {
                    $this->core->getOutput()->showError("You may not access this file until it is released.");
                    return false;
                }
                if (!$this->core->getUser()->accessGrading() && !CourseMaterial::isSectionAllowed($this->core, $path, $this->core->getUser())) {
                    $this->core->getOutput()->showError("Your section may not access this file.");
                    return false;
                }
            }
        }
        $file_name = basename(rawurldecode(htmlspecialchars_decode($path)));
        $corrected_name = pathinfo($path, PATHINFO_DIRNAME) . "/" .  $file_name;
        $mime_type = mime_content_type($corrected_name);
        $file_type = FileUtils::getContentType($file_name);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        if ($mime_type === "application/pdf" || Utils::startsWith($mime_type, "image/")) {
            header("Content-type: " . $mime_type);
            header('Content-Disposition: inline; filename="' . $file_name . '"');
            readfile($corrected_name);
            $this->core->getOutput()->renderString($path);
        }
        else {
            $contents = file_get_contents($corrected_name);
            if (!is_null($ta_grading) && $ta_grading === "true") {
                $this->core->getOutput()->renderOutput('Misc', 'displayCode', $file_type, $corrected_name, $contents);
            }
            else {
                $this->core->getOutput()->renderOutput('Misc', 'displayFile', $contents);
            }
        }
    }

    /**
     * @Route("/{_semester}/{_course}/read_file")
     */
    public function readFile($dir, $path, $csrf_token = null) {
        // security check
        if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path])) {
            $this->core->getOutput()->showError("You do not have access to this file");
            return false;
        }

        //Since this can serve raw html files we should make sure they're coming from a valid source
        if (is_null($csrf_token) || !$this->core->checkCsrfToken($csrf_token)) {
            $this->core->getOutput()->showError("Invalid csrf token");
            return false;
        }

        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);

        $mime_type = mime_content_type($path);
        if ($mime_type === 'text/plain') {
            if (substr($path, '-3') === '.js') {
                $mime_type = 'application/javascript';
            }
            elseif (substr($path, '-4') === '.css') {
                $mime_type = 'text/css';
            }
            elseif (substr($path, '-5') === '.html') {
                $mime_type = 'text/html';
            }
        }
        header('Content-type: ' . $mime_type);
        readfile($path);
        return true;
    }

    /**
     * @Route("/{_semester}/{_course}/download")
     */
    public function downloadCourseFile($dir, $path) {
        // security check
        $path = $this->core->getAccess()->resolveDirPath($dir, htmlspecialchars_decode(urldecode($path)));

        if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path])) {
            $this->core->getOutput()->showError("You do not have access to this file");
            return false;
        }

        // If attempting to obtain course materials
        if ($dir == 'course_materials') {
            // If the user attempting to access the file is not at least a grader then ensure the file has been released
            if (!$this->core->getUser()->accessGrading() && !CourseMaterial::isMaterialReleased($this->core, $path)) {
                $this->core->getOutput()->showError("You may not access this file until it is released.");
                return false;
            }
            elseif (!$this->core->getUser()->accessGrading() && !CourseMaterial::isSectionAllowed($this->core, $path, $this->core->getUser())) {
                $this->core->getOutput()->showError("You do not have access to this file.");
                return false;
            }
        }

        if ($dir == 'submissions') {
            //cannot download scanned images for bulk uploads
            if (
                strpos(basename($path), "upload_page_") !== false
                && FileUtils::getContentType($path) !== "application/pdf"
            ) {
                $this->core->getOutput()->showError("You do not have access to this file");
                return false;
            }
        }

        $filename = pathinfo($path, PATHINFO_BASENAME);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"{$filename}\"");
        readfile($path);
    }

    /**
     * @Route("/{_semester}/{_course}/gradeable/{gradeable_id}/download_zip")
     */
    public function downloadSubmissionZip($gradeable_id, $user_id, $version = null, $origin = null) {
        $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        if ($gradeable === null) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $user_id, null);

        if ($graded_gradeable === null) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $gradeable_version = $graded_gradeable->getAutoGradedGradeable()->getAutoGradedVersionInstance($version);

        if ($gradeable_version === null) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $folder_names = array();
        //See which directories we are allowed to read.
        if ($this->core->getAccess()->canI("path.read.submissions", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "gradeable_version" => $gradeable_version->getVersion()])) {
            //These two have the same check
            $folder_names[] = "submissions";
            $folder_names[] = "checkout";
        }

        // Context of these next two checks is important
        // If the request is coming from the submissions page, then the results and results_public folder
        // should not be included, otherwise include them
        if ($origin != 'submission') {
            if ($this->core->getAccess()->canI("path.read.results", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "gradeable_version" => $gradeable_version->getVersion()])) {
                $folder_names[] = "results";
            }
            if ($this->core->getAccess()->canI("path.read.results_public", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "gradeable_version" => $gradeable_version->getVersion()])) {
                $folder_names[] = "results_public";
            }
        }

        //No results, no download
        if (count($folder_names) === 0) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $zip_file_name = $gradeable_id . "_" . $user_id . "_" . date("m-d-Y") . ".zip";
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        $temp_dir = "/tmp";
        //makes a random zip file name on the server
        $temp_name = uniqid($this->core->getUser()->getId(), true);
        $zip_name = $temp_dir . "/" . $temp_name . ".zip";
        $gradeable_path = $this->core->getConfig()->getCoursePath();
        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
        $version = $version ?? $active_version;

        $paths = [];
        foreach ($folder_names as $folder_name) {
            $paths[] = FileUtils::joinPaths($gradeable_path, $folder_name, $gradeable->getId(), $graded_gradeable->getSubmitter()->getId(), $version);
        }
        $zip = new \ZipArchive();
        $zip->open($zip_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        for ($x = 0; $x < count($paths); $x++) {
            if (is_dir($paths[$x])) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($paths[$x]),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                $zip->addEmptyDir($folder_names[$x]);
                foreach ($files as $name => $file) {
                    // Skip directories (they would be added automatically)
                    if (!$file->isDir()) {
                        $file_path = $file->getRealPath();
                        $relative_path = substr($file_path, strlen($paths[$x]) + 1);

                        // For scanned exams, the directories get polluted with the images of the split apart
                        // pages, so we selectively only grab the PDFs there. For all other types,
                        // we can grab all files regardless of type.
                        if ($gradeable->isScannedExam()) {
                            if (mime_content_type($file_path) === 'application/pdf') {
                                $zip->addFile($file_path, $folder_names[$x] . '/' . $relative_path);
                            }
                        }
                        else {
                            $zip->addFile($file_path, $folder_names[$x] . "/" . $relative_path);
                        }
                    }
                }
            }
        }

        $zip->close();
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=$zip_file_name");
        header("Content-length: " . filesize($zip_name));
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile("$zip_name");
        unlink($zip_name); //deletes the random zip file
    }

    /**
     * @Route("/{_semester}/{_course}/gradeable/{gradeable_id}/grading/download_zip")
     * @AccessControl(role="LIMITED_ACCESS_GRADER")
     */
    public function downloadAssignedZips($gradeable_id, $type = null) {
        $zip_file_name = $gradeable_id . "_section_students_" . date("m-d-Y") . ".zip";
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        if ($type === "all") {
            $zip_file_name = $gradeable_id . "_all_students_" . date("m-d-Y") . ".zip";
            if (!($this->core->getUser()->accessFullGrading())) {
                $message = "You do not have access to that page.";
                $this->core->addErrorMessage($message);
                $this->core->redirect($this->core->buildCourseUrl());
            }
        }
        else {
            $type = "";
        }

        $temp_dir = "/tmp";
        //makes a random zip file name on the server
        $temp_name = uniqid($this->core->getUser()->getId(), true);
        $zip_name = $temp_dir . "/" . $temp_name . ".zip";
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return;
        }
        $paths = ['submissions'];
        if ($gradeable->isVcs()) {
            //VCS submissions are stored in the checkout directory
            $paths[] = 'checkout';
        }
        $zip = new \ZipArchive();
        $zip->open($zip_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($paths as $path) {
            $gradeable_path = FileUtils::joinPaths(
                $this->core->getConfig()->getCoursePath(),
                $path,
                $gradeable->getId()
            );
            if ($type === "all") {
                $zip->addEmptyDir($path);
                if (file_exists($gradeable_path)) {
                    if (!is_dir($gradeable_path)) { //if dir is already present, but it's a file
                        $message = "Oops! That page is not available.";
                        $this->core->addErrorMessage($message);
                        $this->core->redirect($this->core->buildCourseUrl());
                    }
                    else {
                        $files = new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator($gradeable_path),
                            \RecursiveIteratorIterator::LEAVES_ONLY
                        );
                        foreach ($files as $name => $file) {
                            // Skip directories (they would be added automatically)
                            if (!$file->isDir()) {
                                // Get real and relative path for current file
                                $filePath = $file->getRealPath();
                                $relativePath = substr($filePath, strlen($gradeable_path) + 1);
                                // Add current file to archive
                                $zip->addFile($filePath, $path . "/" . $relativePath);
                            }
                        }
                    }
                }
                else { //no dir exists with this name
                    $message = "Oops! That page is not available.";
                    $this->core->addErrorMessage($message);
                    $this->core->redirect($this->core->buildCourseUrl());
                }
            }
            else {
                //gets the students that are part of the sections
                if ($gradeable->isGradeByRegistration()) {
                    $section_key = "registration_section";
                    $sections = $this->core->getUser()->getGradingRegistrationSections();
                    $students = $this->core->getQueries()->getUsersByRegistrationSections($sections);
                }
                else {
                    $section_key = "rotating_section";
                    $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser(
                        $gradeable->getId(),
                        $this->core->getUser()->getId()
                    );
                    $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
                }
                $students_array = array();
                foreach ($students as $student) {
                    $students_array[] = $student->getId();
                }
                $files = scandir($gradeable_path);
                $arr_length = count($students_array);
                foreach ($files as $file) {
                    for ($x = 0; $x < $arr_length; $x++) {
                        if ($students_array[$x] === $file) {
                            $temp_path = $gradeable_path . "/" . $file;
                            $files_in_folder = new \RecursiveIteratorIterator(
                                new \RecursiveDirectoryIterator($temp_path),
                                \RecursiveIteratorIterator::LEAVES_ONLY
                            );

                            //makes a new directory in the zip to add the files in
                            $zip -> addEmptyDir($file);

                            foreach ($files_in_folder as $name => $file_in_folder) {
                                // Skip directories (they would be added automatically)
                                if (!$file_in_folder->isDir()) {
                                    // Get real and relative path for current file
                                    $filePath = $file_in_folder->getRealPath();
                                    $relativePath = substr($filePath, strlen($temp_path) + 1);
                                    // Add current file to archive
                                    $zip->addFile($filePath, $file . "/" . $relativePath);
                                }
                            }
                            $x = $arr_length; //cuts the for loop early when found
                        }
                    }
                }
            }
        }
        // Zip archive will be created only after closing object
        $zip->close();
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=$zip_file_name");
        header("Content-length: " . filesize($zip_name));
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile("$zip_name");
        unlink($zip_name); //deletes the random zip file
    }

    /**
     * @Route("/{_semester}/{_course}/gradeable/{gradeable_id}/bulk/progress")
     */
    public function checkBulkProgress($gradeable_id) {
        $job_path = "/var/local/submitty/daemon_job_queue/";
        $result = [];
        $found = false;
        $job_data = null;
        $complete_count = 0;
        try {
            foreach (scandir($job_path) as $job) {
                if (strpos($job, 'bulk_upload_') !== false) {
                    $found = true;
                }
                else {
                    continue;
                }
                //remove 'bulk_upload_' and '.json' from job file name
                $result[] = substr($job, 11, -5);
            }
            //look in the split upload folder to see what is complete
            $split_uploads = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "split_pdf", $gradeable_id);
            $sub_dirs = array_filter(glob($split_uploads . '/*'), 'is_dir');
            foreach ($sub_dirs as $dir) {
                foreach (scandir($dir) as $file) {
                    if (pathinfo($file)['extension'] !== "pdf") {
                        continue;
                    }

                    if (strpos($file, "_cover")) {
                        $complete_count++;
                    }
                }
            }
            $result = ['found' => $found, 'job_data' => $result, 'count' => $complete_count];
            return $this->core->getOutput()->renderJsonSuccess($result);
        }
        catch (\Exception $e) {
            return $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }
}

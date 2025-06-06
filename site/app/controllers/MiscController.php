<?php

namespace app\controllers;

use app\entities\course\CourseMaterial;
use app\libraries\CodeMirrorUtils;
use app\libraries\CourseMaterialsUtils;
use app\libraries\DateUtils;
use app\libraries\FileUtils;
use app\libraries\NotebookUtils;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
use app\libraries\response\MultiResponse;
use app\libraries\response\JsonResponse;
use app\models\gradeable\Gradeable;
use app\models\User;
use app\views\MiscView;
use Symfony\Component\Routing\Annotation\Route;

class MiscController extends AbstractController {
    const GENERIC_NO_ACCESS_MSG = 'You do not have access to this file';

    private function convertToBytes(string $value): int {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $num = (int) $value;

        switch ($unit) {
            case 'g':
                return $num * 1024 * 1024 * 1024;
            case 'm':
                return $num * 1024 * 1024;
            case 'k':
                return $num * 1024;
            default:
                return (int) $value;
        }
    }

    /**
     * Get the current server time
     *
     * Returns a json string which contains the current server time broken up into year, month, day, hour, minute,
     * second
     */
    #[Route("/server_time")]
    public function getServerTime(): JsonResponse {
        return JsonResponse::getSuccessResponse(DateUtils::getServerTimeJson($this->core));
    }

    /**
     * Given a path that may or may not contain the anon_id instead of the user_id return the path containing the user_id
     */
    public function decodeAnonPath($path, $g_id = null) {
        $exploded_path = explode("/", $path);
        if (count($exploded_path) < 10) {
            return $path;
        }
        $anon_id = explode("/", $path)[9];
        $correct_user_id = $this->core->getQueries()->getSubmitterIdFromAnonId($anon_id, $g_id);
        if ($correct_user_id !== null) {
            $path = str_replace($anon_id, $correct_user_id, $path);
        }
        return $path;
    }

    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/encode_pdf")]
    public function encodePDF($gradeable_id) {
        $id = $_POST['user_id'] ?? null;
        $file_name = $_POST['filename'] ?? null;
        $file_name = html_entity_decode($file_name);
        $gradeable = $this->tryGetGradeable($gradeable_id);
        $submitter = $this->core->getQueries()->getSubmitterById($id);
        $graded_gradeable = $this->core->getQueries()->getGradedGradeableForSubmitter($gradeable, $submitter);
        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
        $file_path = $this->decodeAnonPath(urldecode($_POST['file_path']), $gradeable_id);
        $directory = 'invalid';
        if (strpos($file_path, 'submissions') !== false) {
            $directory = 'submissions';
        }
        elseif (strpos($file_path, 'checkout') !== false) {
            $directory = 'checkout';
        }
        $check_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), $directory, $gradeable_id, $id);

        if ($gradeable->isGradeByRegistration()) {
            $section = $submitter->getRegistrationSection();
        }
        else {
            $section = $submitter->getRotatingSection();
        }

        if (!str_starts_with($file_path, $check_path)) {
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse("Invalid file path")
            );
        }

        if (!$this->core->getAccess()->canI("path.read", ["dir" => $directory, "path" => $file_path, "gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "section" => $section])) {
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse(self::GENERIC_NO_ACCESS_MSG)
            );
        }

        $max_size = $this->convertToBytes(ini_get('memory_limit')) / 5;

        if (filesize($file_path) > $max_size && $max_size >= 0) {
            return new MultiResponse(JsonResponse::getFailResponse(
                "This PDF is too large to be viewed online. Please download it instead."
            ));
        }


        $pdf64 = base64_encode(file_get_contents($file_path));
        return MultiResponse::JsonOnlyResponse(
            JsonResponse::getSuccessResponse($pdf64)
        );
    }

    #[Route("/courses/{_semester}/{_course}/display_file")]
    public function displayFile($dir = null, $path = null, $gradeable_id = null, $user_id = null, $ta_grading = null, $course_material_id = null) {
        $cm = null;
        //Is this per-gradeable?
        if ($course_material_id === null && ($dir !== null && $path !== null)) {
            $res_path = $this->core->getAccess()->resolveDirPath($dir, htmlspecialchars_decode(rawurldecode($path)));
            if ($res_path === false) {
                $this->core->getOutput()->showError(self::GENERIC_NO_ACCESS_MSG);
                return false;
            }
            $path = $this->decodeAnonPath($res_path, $gradeable_id);
            if ($dir === 'course_materials') {
                $cm = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
                    ->findOneBy(['path' => $path]);
            }
        }
        else {
            $cm = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
                ->findOneBy(['id' => $course_material_id]);
            if ($cm !== null) {
                $path = $cm->getPath();
                $dir = 'course_materials';
            }
            else {
                $path = null;
            }
        }

        if (!is_null($gradeable_id) && !is_null($user_id)) {
            $gradeable = $this->tryGetGradeable($gradeable_id, false);
            if ($gradeable === false) {
                return false;
            }
            $graded_gradeable =  $this->tryGetGradedGradeable($gradeable, $user_id, false);
            if ($graded_gradeable === false) {
                return false;
            }
            if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path, "gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
                $this->core->getOutput()->showError(self::GENERIC_NO_ACCESS_MSG);
                return false;
            }
        }
        else {
            // Check access through Access library
            if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path])) {
                $this->core->getOutput()->showError(self::GENERIC_NO_ACCESS_MSG);
                return false;
            }

            if ($dir == 'course_materials' && !$this->core->getUser()->accessGrading()) {
                if ($cm === null) {
                    $access_failure = 'Course material not found';
                }
                else {
                    $access_failure = CourseMaterialsUtils::finalAccessCourseMaterialCheck($this->core, $cm);
                }
                if ($access_failure) {
                    $this->core->getOutput()->showError($access_failure);
                    return false;
                }
            }

            if ($dir === "course_materials") {
                CourseMaterialsUtils::insertCourseMaterialAccess($this->core, $path);
            }
        }

        $file_name = basename($path);
        $corrected_name = pathinfo($path, PATHINFO_DIRNAME) . "/" .  $file_name;
        $mime_type = mime_content_type($corrected_name);
        $file_type = FileUtils::getContentType($file_name);
        if ($mime_type === "application/pdf" || (str_starts_with($mime_type, "image/") && $mime_type !== "image/svg+xml")) {
            $this->core->getOutput()->useHeader(false);
            $this->core->getOutput()->useFooter(false);
            header("Content-type: " . $mime_type);
            header('Content-Disposition: inline; filename="' . $file_name . '"');
            readfile($corrected_name);
            $this->core->getOutput()->renderString($path);
        }
        elseif (pathinfo($path, PATHINFO_EXTENSION) === 'ipynb') { // TODO: Do this "properly" by determining a better MIME type via FileUtils::getContentType()
            $this->core->getOutput()->setContentOnly(true);
            CodeMirrorUtils::loadDefaultDependencies($this->core);
            $this->core->getOutput()->addInternalJs('gradeable-notebook.js');
            $this->core->getOutput()->renderString(
                $this->core->getOutput()->renderTwigTemplate(
                    "notebook/Notebook.twig",
                    [
                        'notebook' => NotebookUtils::jupyterToSubmittyNotebook($path),
                        'student_id' => $user_id,
                        'is_timed' => false,
                        'allowed_minutes' => 0,
                        'old_files' => [],
                        'is_grader_view' => true,
                        'testcase_messages' => [],
                        'viewing_inactive_version' => false,
                        'highest_version' => 0,
                    ]
                )
            );
        }
        else {
            $contents = file_get_contents($corrected_name);
            if (!is_null($ta_grading) && $ta_grading === "true") {
                $newlines = substr_count($contents, "\n");
                $carriage_returns = substr_count($contents, "\r");
                if ($newlines + $carriage_returns > 2000) {
                    return new WebResponse(MiscView::class, 'tooLarge');
                }
                $this->core->getOutput()->renderOutput('Misc', 'displayCode', $file_type, $corrected_name, $contents);
            }
            else {
                $this->core->getOutput()->renderOutput('Misc', 'displayFile', $contents);
            }
        }
    }

    #[Route("/courses/{_semester}/{_course}/read_file")]
    public function readFile($dir, $path, $csrf_token = null) {
        // security check
        if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path])) {
            $this->core->getOutput()->showError(self::GENERIC_NO_ACCESS_MSG);
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
            if (str_ends_with($path, '.js')) {
                $mime_type = 'application/javascript';
            }
            elseif (str_ends_with($path, '.css')) {
                $mime_type = 'text/css';
            }
            elseif (str_ends_with($path, '.html')) {
                $mime_type = 'text/html';
            }
        }
        header('Content-type: ' . $mime_type);
        readfile($path);
        return true;
    }

    #[Route("/courses/{_semester}/{_course}/download")]
    public function downloadCourseFile($dir = null, $path = null, $course_material_id = null, $gradeable_id = null) {
        // security check
        $cm = null;
        if ($course_material_id === null && ($dir !== null && $path !== null)) {
            $res_path = $this->core->getAccess()->resolveDirPath($dir, htmlspecialchars_decode(rawurldecode($path)));
            if ($res_path === false) {
                $this->core->getOutput()->showError(self::GENERIC_NO_ACCESS_MSG);
                return false;
            }
            $path = $this->decodeAnonPath($res_path, $gradeable_id);
        }
        elseif ($course_material_id !== null) {
            $cm = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
                ->findOneBy(['id' => $course_material_id]);
            if ($cm !== null) {
                $dir = 'course_materials';
                $path = $cm->getPath();
                $title = $cm->getTitle();
            }
        }

        if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path])) {
            $this->core->getOutput()->showError(self::GENERIC_NO_ACCESS_MSG);
            return false;
        }

        if ($dir == 'course_materials' && !$this->core->getUser()->accessGrading()) {
            if ($cm === null) {
                $access_failure = 'Course material not found';
            }
            else {
                $access_failure = CourseMaterialsUtils::finalAccessCourseMaterialCheck($this->core, $cm);
            }
            if ($access_failure) {
                $this->core->getOutput()->showError($access_failure);
                return false;
            }
        }

        if ($dir === "course_materials") {
            CourseMaterialsUtils::insertCourseMaterialAccess($this->core, $path);
        }

        if (isset($title) && $title !== "") {
            $filename = $title;
        }
        else {
            $filename = pathinfo($path, PATHINFO_BASENAME);
        }
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"{$filename}\"");
        readfile($path);
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/downloadTestCaseResult")]
    public function downloadTestCaseResult(string $gradeable_id, int $version, int $test_case, string $file_name, string $user_id) {
        $gradeable = $this->tryGetGradeable($gradeable_id);
        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id, false);
        if ($user_id !== $this->core->getUser()->getId()) {
            if (!$this->core->getAccess()->canI("grading.electronic.grade_autograding", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
                $this->core->addErrorMessage("You do not have permission to download this file!");
                return new RedirectResponse($this->core->buildCourseUrl(['gradeable', $gradeable_id]));
            }
        }
        $autograde = $graded_gradeable->getAutoGradedGradeable()->getAutoGradedVersionInstance($version);
        $file_path = null;
        $testcase = $autograde->getTestcases()[$test_case - 1];
        if ((!$testcase->getTestcase()->isHidden() || $this->core->getAccess()->canI("grading.electronic.grade_autograding", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) && $testcase->hasAutochecks()) {
            foreach ($testcase->getAutochecks() as $autocheck) {
                $path = explode('/', $autocheck->getDiffViewer()->getActualFilename());
                $actual_file_name = array_pop($path);
                if ($file_name === $actual_file_name) {
                    $file_path = $autocheck->getDiffViewer()->getActualFilename();
                    break;
                }
            }
        }
        if ($file_path !== null) {
            if (file_exists($file_path)) {
                $this->core->getOutput()->useHeader(false);
                $this->core->getOutput()->useFooter(false);
                header('Content-Type: application/octet-stream');
                header("Content-Transfer-Encoding: Binary");
                header("Content-disposition: attachment; filename=\"{$file_name}\"");
                readfile($file_path);
            }
            else {
                $this->core->addErrorMessage("That file does not seem to exist");
                return new RedirectResponse($this->core->buildCourseUrl(['gradeable', $gradeable_id]));
            }
        }
        else {
            $this->core->addErrorMessage("That file does not seem to exist");
            return new RedirectResponse($this->core->buildCourseUrl(['gradeable', $gradeable_id]));
        }
    }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/download_zip")]
    public function downloadSubmissionZip($gradeable_id, $submitter_id, $version, $is_anon, $origin = null) {

        $anon_id = $submitter_id;

        $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        if ($gradeable === null) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $peer = $gradeable->hasPeerComponent() && $this->core->getUser()->getGroup() === User::GROUP_STUDENT;
        $blind_grading = ($peer && $gradeable->getPeerBlind() !== Gradeable::UNBLIND_GRADING) || ($gradeable->getLimitedAccessBlind() === Gradeable::SINGLE_BLIND_GRADING && $this->core->getUser()->getGroup() === User::GROUP_LIMITED_ACCESS_GRADER);
        if ($blind_grading || $is_anon === "true") {
            $submitter_id = $this->core->getQueries()->getSubmitterIdFromAnonId($anon_id, $gradeable_id);
        }
        if ($submitter_id === null) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $is_team = $gradeable->isTeamAssignment();

        $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $is_team ? null : $submitter_id, $is_team ? $submitter_id : null);

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

        $folder_names = [];
        $access_args = ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "gradeable_version" => $gradeable_version->getVersion()];
        if ($gradeable->getGraderAssignmentMethod() === Gradeable::ROTATING_SECTION) {
            $access_args["section"] = $graded_gradeable->getSubmitter()->getRotatingSection();
        }
        elseif ($gradeable->getGraderAssignmentMethod() === Gradeable::REGISTRATION_SECTION) {
            $access_args["section"] = $graded_gradeable->getSubmitter()->getRegistrationSection();
        }
        //See which directories we are allowed to read.
        if ($this->core->getAccess()->canI("path.read.submissions", $access_args)) {
            //These two have the same check
            $folder_names[] = "submissions";
            $folder_names[] = "submissions_processed";
            $folder_names[] = "checkout";
        }

        // Context of these next two checks is important
        // If the request is coming from the submissions page, then the results and results_public folder
        // should not be included, otherwise include them
        if ($origin != 'submission') {
            if ($this->core->getAccess()->canI("path.read.results", $access_args)) {
                $folder_names[] = "results";
            }
            if ($this->core->getAccess()->canI("path.read.results_public", $access_args)) {
                $folder_names[] = "results_public";
            }
        }

        //No results, no download
        if (count($folder_names) === 0) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildCourseUrl());
        }

        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        $gradeable_path = $this->core->getConfig()->getCoursePath();
        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
        $version = $version ?? $active_version;

        // TODO: Zip file anonymization is currently done based on access level (students==peers)
        // When single/double blind grading is merged, this will need to be updated.
        $zip_file_name = $gradeable_id . "_" . $anon_id . "_v" . $version . ".zip";

        // create a new zipstream object
        $zip_stream = new \ZipStream\ZipStream(
            outputName: $zip_file_name,
            sendHttpHeaders: true,
            enableZip64: false,
        );
        foreach ($folder_names as $folder_name) {
            $path = FileUtils::joinPaths($gradeable_path, $folder_name, $gradeable->getId(), $graded_gradeable->getSubmitter()->getId(), $version);
            if (is_dir($path)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                $zip_stream->addFile($folder_name . "/", "");
                foreach ($files as $name => $file) {
                    // Skip directories (they are added automatically)
                    if ($file->isDir()) {
                        continue;
                    }
                    $file_path = $file->getRealPath();
                    $relative_path = substr($file_path, strlen($path) + 1);
                    if (
                        $this->core->getAccess()->canI(
                            "path.read",
                            [
                                "dir" => $folder_name,
                                "path" => $file_path,
                                "gradeable" => $gradeable,
                                "graded_gradeable" => $graded_gradeable,
                                "gradeable_version" => $gradeable_version->getVersion(),
                                "root_path" => $path
                            ]
                        )
                    ) {
                        $zip_stream->addFileFromPath(FileUtils::joinPaths($folder_name, $relative_path), $file_path);
                    }
                }
            }
        }
        $zip_stream->finish();
    }

    /**
     * @AccessControl(role="LIMITED_ACCESS_GRADER")
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/download_zip")]
    public function downloadAssignedZips($gradeable_id, $type = null) {
        $zip_file_name = $gradeable_id . "_section_students_" . date("m-d-Y") . ".zip";
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        if (in_array($type, ["all", "results", "active", "both"])) {
            $zip_file_name = $gradeable_id . "_all_students_" . date("m-d-Y") . ".zip";
            if (!($this->core->getUser()->accessFullGrading())) {
                $message = "You do not have access to that page.";
                $this->core->addErrorMessage($message);
                $this->core->redirect($this->core->buildCourseUrl());
            }
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
        if (in_array($type, ["results", "both", "limited_results", "limited_both"])) {
            $paths[] = 'results';
        }

        // create a new zipstream object
        $zip_stream = new \ZipStream\ZipStream(
            outputName: $zip_file_name,
            sendHttpHeaders: true,
            enableZip64: false,
        );

        foreach ($paths as $path) {
            $gradeable_path = FileUtils::joinPaths(
                $this->core->getConfig()->getCoursePath(),
                $path,
                $gradeable->getId()
            );
            if (in_array($type, ["all", "results"])) {
                $zip_stream->addFile($path . "/", "");
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
                            if (!$file->isDir() && !$file->isLink()) {
                                // Get real and relative path for current file
                                $filePath = $file->getRealPath();
                                $relativePath = substr($filePath, strlen($gradeable_path) + 1);
                                // Add current file to archive
                                $zip_stream->addFileFromPath($path . "/" . $relativePath, $filePath);
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
            elseif (in_array($type, ["active", "both"])) {
                $zip_stream->addFile($path . "/", "");
                if (file_exists($gradeable_path)) {
                    if (!is_dir($gradeable_path)) { //if dir is already present, but it's a file
                        $message = "Oops! That page is not available.";
                        $this->core->addErrorMessage($message);
                        $this->core->redirect($this->core->buildCourseUrl());
                    }
                    else {
                        $graded_gradeables = $this->core->getQueries()->getGradedGradeables([$gradeable]);

                        foreach ($graded_gradeables as $gg) { //get each graded gradeable
                            $user = $gg->getSubmitter()->getId();
                            $version = $gg->getAutoGradedGradeable()->getActiveVersion();
                            if ($version <= 0) { //if no active version exitsts, continue
                                continue;
                            }
                            $gradeable_path = FileUtils::joinPaths(
                                $this->core->getConfig()->getCoursePath(),
                                $path,
                                $gradeable->getId(),
                                $user,
                                $version
                            );

                            $files = new \RecursiveIteratorIterator(
                                new \RecursiveDirectoryIterator($gradeable_path),
                                \RecursiveIteratorIterator::LEAVES_ONLY
                            );

                            foreach ($files as $name => $file) {
                                // Skip directories (they would be added automatically)
                                if (!$file->isDir()) {
                                    // Get real and relative path for current file
                                    $filePath = $file->getRealPath();
                                    $relativePath = $user . "/" . substr($filePath, strlen($gradeable_path) + 1);

                                    // Add current file to archive
                                    $zip_stream->addFileFromPath($path . "/" . $relativePath, $filePath);
                                }
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
                $students_array = [];
                foreach ($students as $student) {
                    $students_array[] = $student->getId();
                }
                $files = scandir($gradeable_path);
                $arr_length = count($students_array);
                foreach ($files as $file) {
                    for ($x = 0; $x < $arr_length; $x++) {
                        if ($students_array[$x] === $file) {
                            if (in_array($type, ["limited_active", "limited_both"])) {
                                $gg = $this->core->getQueries()->getGradedGradeable($gradeable, $students_array[$x], null);
                                $version = $gg->getAutoGradedGradeable()->getActiveVersion();
                                if ($version <= 0) { //if no active version exitsts, continue
                                    continue;
                                }
                                $temp_path = $gradeable_path . "/" . $file . "/" . $version;
                            }
                            else {
                                $temp_path = $gradeable_path . "/" . $file;
                            }
                            $files_in_folder = new \RecursiveIteratorIterator(
                                new \RecursiveDirectoryIterator($temp_path),
                                \RecursiveIteratorIterator::LEAVES_ONLY
                            );

                            //makes a new directory in the zip to add the files in
                            $zip_stream->addFile($file . "/", "");

                            foreach ($files_in_folder as $name => $file_in_folder) {
                                // Skip directories (they would be added automatically)
                                if (!$file_in_folder->isDir()) {
                                    // Get real and relative path for current file
                                    $filePath = $file_in_folder->getRealPath();
                                    $relativePath = substr($filePath, strlen($temp_path) + 1);

                                    // Add current file to archive
                                    $zip_stream->addFileFromPath($file . "/" . $relativePath, $filePath);
                                }
                            }
                            $x = $arr_length; //cuts the for loop early when found
                        }
                    }
                }
            }
        }
        // Zip archive will be created only after closing object
        $zip_stream->finish();
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=$zip_file_name");
        header("Content-length: " . filesize($zip_name));
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile("$zip_name");
        unlink($zip_name); //deletes the random zip file
    }

    /**
     * @AccessControl(role="FULL_ACCESS_GRADER")
     */
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/bulk/progress")]
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

<?php

namespace app\controllers\course;

use app\controllers\AbstractController;
use app\controllers\MiscController;
use app\entities\course\CourseMaterialAccess;
use app\entities\course\CourseMaterialSection;
use app\libraries\CourseMaterialsUtils;
use app\libraries\DateUtils;
use app\libraries\FileUtils;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\libraries\Utils;
use app\entities\course\CourseMaterial;
use app\repositories\course\CourseMaterialRepository;
use app\views\course\CourseMaterialsView;
use app\views\ErrorView;
use app\views\MiscView;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\routers\AccessControl;

class CourseMaterialsController extends AbstractController {
    /**
     * @Route("/courses/{_semester}/{_course}/course_materials")
     */
    public function viewCourseMaterialsPage(): WebResponse {
        $repo = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class);
        /** @var CourseMaterialRepository $repo */
        $course_materials = $repo->getCourseMaterials();
        return new WebResponse(
            CourseMaterialsView::class,
            'listCourseMaterials',
            $course_materials
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/course_material/{path}", requirements={"path"=".+"})
     */
    public function viewCourseMaterial(string $path) {
        $full_path = $this->core->getConfig()->getCoursePath() . "/uploads/course_materials/" . $path;
        $cm = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
            ->findOneBy(['path' => $full_path]);
        if ($cm === null || !$this->core->getAccess()->canI("path.read", ["dir" => "course_materials", "path" => $full_path])) {
            return new WebResponse(
                ErrorView::class,
                "errorPage",
                MiscController::GENERIC_NO_ACCESS_MSG
            );
        }
        if (!$this->core->getUser()->accessGrading()) {
            $access_failure = CourseMaterialsUtils::finalAccessCourseMaterialCheck($this->core, $cm);
            if ($access_failure) {
                return new WebResponse(
                    ErrorView::class,
                    "errorPage",
                    $access_failure
                );
            }
        }
        CourseMaterialsUtils::insertCourseMaterialAccess($this->core, $full_path);
        $file_name = basename($full_path);
        $corrected_name = pathinfo($full_path, PATHINFO_DIRNAME) . "/" .  $file_name;
        $mime_type = mime_content_type($corrected_name);
        $file_type = FileUtils::getContentType($file_name);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);

        if ($mime_type === "application/pdf" || (str_starts_with($mime_type, "image/") && $mime_type !== "image/svg+xml")) {
            header("Content-type: " . $mime_type);
            header('Content-Disposition: inline; filename="' . $file_name . '"');
            readfile($corrected_name);
            $this->core->getOutput()->renderString($full_path);
        }
        else {
            $contents = file_get_contents($corrected_name);
            return new WebResponse(
                MiscView::class,
                "displayFile",
                $contents
            );
        }
    }

    /**
     * @Route("/courses/{_semester}/{_course}/course_materials/view", methods={"POST"})
     */
    public function markViewed(): JsonResponse {
        $ids = $_POST['ids'];
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $cms = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
            ->findBy(['id' => $ids]);
        foreach ($cms as $cm) {
            $cm_access = new CourseMaterialAccess($cm, $this->core->getUser()->getId(), $this->core->getDateTimeNow());
            $cm->addAccess($cm_access);
        }
        $this->core->getCourseEntityManager()->flush();
        return JsonResponse::getSuccessResponse();
    }

    /**
     * @Route("/courses/{_semester}/{_course}/course_materials/delete")
     */
    public function deleteCourseMaterial($id) {
        $cm = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
            ->findOneBy(['id' => $id]);
        if ($cm === null) {
            $this->core->addErrorMessage("Failed to delete course material");
            return new RedirectResponse($this->core->buildCourseUrl(['course_materials']));
        }
        // security check
        $dir = "course_materials";
        $path = $this->core->getAccess()->resolveDirPath($dir, htmlspecialchars_decode(rawurldecode($cm->getPath())));

        if (!$this->core->getAccess()->canI("path.write", ["path" => $path, "dir" => $dir])) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            return new RedirectResponse($this->core->buildCourseUrl(['course_materials']));
        }

        $all_files = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)->findAll();

        foreach ($all_files as $file) {
            if (str_starts_with($file->getPath(), $path)) {
                $this->core->getCourseEntityManager()->remove($file);
            }
        }
        $this->core->getCourseEntityManager()->flush();
        $success = false;
        if (is_dir($path)) {
            $success = FileUtils::recursiveRmdir($path);
        }
        else {
            $success = unlink($path);
        }

        if ($success) {
            $this->core->addSuccessMessage(basename($path) . " has been successfully removed.");
        }
        else {
            $this->core->addErrorMessage("Failed to remove " . basename($path));
        }

        return new RedirectResponse($this->core->buildCourseUrl(['course_materials']));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/course_materials/download_zip")
     */
    public function downloadCourseMaterialZip($course_material_id) {
        $cm = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
            ->findOneBy(['id' => $course_material_id]);
        if ($cm === null) {
            $this->core->addErrorMessage("Invalid course material ID");
            return new RedirectResponse($this->core->buildCourseUrl(['course_materials']));
        }
        $root_path = $cm->getPath();
        $dir_name = explode("/", $root_path);
        $dir_name = array_pop($dir_name);

        // check if the user has access to course materials
        if (!$this->core->getAccess()->canI("path.read", ["dir" => 'course_materials', "path" => $root_path])) {
            $this->core->getOutput()->showError("You do not have access to this folder");
            return false;
        }

        $zip_file_name = preg_replace('/\s+/', '_', $dir_name) . ".zip";

        $isFolderEmptyForMe = true;
        // iterate over the files inside the requested directory
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root_path),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $options = new \ZipStream\Option\Archive();
        $options->setSendHttpHeaders(true);
        $options->setEnableZip64(false);

        // create a new zipstream object
        $zip_stream = new \ZipStream\ZipStream($zip_file_name, $options);

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $course_material = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
                    ->findOneBy(['path' => $file_path]);
                if ($course_material !== null) {
                    if (!$this->core->getUser()->accessGrading()) {
                        // only add the file if the section of student is allowed and course material is released!
                        if ($course_material->isSectionAllowed($this->core->getUser()->getRegistrationSection()) && $course_material->getReleaseDate() < $this->core->getDateTimeNow()) {
                            $relativePath = substr($file_path, strlen($root_path) + 1);
                            $isFolderEmptyForMe = false;
                            $zip_stream->addFileFromPath($relativePath, $file_path);
                            $course_material_access = new CourseMaterialAccess($course_material, $this->core->getUser()->getId(), $this->core->getDateTimeNow());
                            $course_material->addAccess($course_material_access);
                        }
                    }
                    else {
                        // For graders and instructors, download the course-material unconditionally!
                        $relativePath = substr($file_path, strlen($root_path) + 1);
                        $isFolderEmptyForMe = false;
                        $zip_stream->addFileFromPath($relativePath, $file_path);
                        $course_material_access = new CourseMaterialAccess($course_material, $this->core->getUser()->getId(), $this->core->getDateTimeNow());
                        $course_material->addAccess($course_material_access);
                    }
                }
            }
        }

        // If the Course Material Folder Does not contain anything for current user display an error message.
        if ($isFolderEmptyForMe) {
            $this->core->getOutput()->showError("You do not have access to this folder");
            return false;
        }
        $this->core->getCourseEntityManager()->flush();
        $zip_stream->finish();
    }

    /**
     * @Route("/courses/{_semester}/{_course}/course_materials/release_all")
     * @return JsonResponse
     */
    public function setReleaseAll(): JsonResponse {
        $newdatetime = $_POST['newdatatime'];
        $newdatetime = htmlspecialchars($newdatetime);
        $new_date_time = DateUtils::parseDateTime($newdatetime, $this->core->getDateTimeNow()->getTimezone());

        $course_materials = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
            ->findAll();
        foreach ($course_materials as $course_material) {
            if (!$course_material->isDir()) {
                $course_material->setReleaseDate($new_date_time);
            }
        }
        $this->core->getCourseEntityManager()->flush();
        return JsonResponse::getSuccessResponse();
    }

    private function setFileTimeStamp(CourseMaterial $courseMaterial, array $courseMaterials, \DateTime $dateTime) {
        if ($courseMaterial->isDir()) {
            foreach ($courseMaterials as $cm) {
                if (str_starts_with($cm->getPath(), $courseMaterial->getPath()) && $cm->getPath() !== $courseMaterial->getPath()) {
                    $this->setFileTimeStamp($cm, $courseMaterials, $dateTime);
                }
            }
        }
        else {
            $courseMaterial->setReleaseDate($dateTime);
        }
    }

    /**
     * @Route("/courses/{_semester}/{_course}/course_materials/modify_timestamp")
     * @AccessControl(role="INSTRUCTOR")
     */
    public function modifyCourseMaterialsFileTimeStamp($newdatatime): JsonResponse {
        if (!isset($_POST['id'])) {
            return JsonResponse::getErrorResponse("You must specify an ID");
        }
        $id = $_POST['id'];

        if (!isset($newdatatime)) {
            $this->core->redirect($this->core->buildCourseUrl(['course_materials']));
        }

        $new_data_time = htmlspecialchars($newdatatime);
        $new_data_time = DateUtils::parseDateTime($new_data_time, $this->core->getDateTimeNow()->getTimezone());

        $has_error = false;
        $success = false;

        $courseMaterial = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
            ->findOneBy(['id' => $id]);
        $courseMaterials = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
            ->findAll();

        if ($courseMaterial === null || $courseMaterials === null) {
            $has_error = true;
        }
        else {
            $this->setFileTimeStamp($courseMaterial, $courseMaterials, $new_data_time);
            $this->core->getCourseEntityManager()->flush();
        }

        if ($has_error) {
            return JsonResponse::getErrorResponse("Failed to find one of the course materials.");
        }
        return JsonResponse::getSuccessResponse("Time successfully set.");
    }

    private function recursiveEditFolder(array $course_materials, CourseMaterial $main_course_material) {
        foreach ($course_materials as $course_material) {
            if (
                str_starts_with($course_material->getPath(), $main_course_material->getPath())
                && $course_material->getPath() != $main_course_material->getPath()
            ) {
                if ($course_material->isDir()) {
                    $this->recursiveEditFolder($course_materials, $course_material);
                }
                else {
                    $_POST['requested_path'] = $course_material->getPath();
                    $this->ajaxEditCourseMaterialsFiles(false);
                }
            }
        }
    }

    /**
     * @Route("/courses/{_semester}/{_course}/course_materials/edit", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function ajaxEditCourseMaterialsFiles(bool $flush = true): JsonResponse {
        $id = $_POST['id'] ?? '';
        if ($id === '') {
            return JsonResponse::getErrorResponse("Id cannot be empty");
        }
        /** @var CourseMaterial $course_material */
        $course_material = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
            ->findOneBy(['id' => $id]);
        if ($course_material == null) {
            return JsonResponse::getErrorResponse("Course material not found");
        }

        if ($course_material->isDir()) {
            if (isset($_POST['sort_priority'])) {
                $course_material->setPriority($_POST['sort_priority']);
                unset($_POST['sort_priority']);
            }
            if (
                (isset($_POST['sections_lock'])
                || isset($_POST['hide_from_students'])
                || isset($_POST['release_time']))
                && isset($_POST['folder_update'])
                && $_POST['folder_update'] == 'true'
            ) {
                $course_materials = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
                    ->findAll();
                $this->recursiveEditFolder($course_materials, $course_material);
            }
            $this->core->getCourseEntityManager()->flush();
            return JsonResponse::getSuccessResponse("Success");
        }

        //handle sections here

        if (isset($_POST['sections_lock']) && $_POST['sections_lock'] == "true") {
            if ($_POST['sections'] === "") {
                $sections = null;
            }
            else {
                $sections = explode(",", $_POST['sections']);
            }
            if ($sections != null) {
                $keep_ids = [];

                foreach ($sections as $section) {
                    $keep_ids[] = $section;
                    $found = false;
                    foreach ($course_material->getSections() as $course_section) {
                        if ($section === $course_section->getSectionId()) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $course_material_section = new CourseMaterialSection($section, $course_material);
                        $course_material->addSection($course_material_section);
                    }
                }

                foreach ($course_material->getSections() as $section) {
                    if (!in_array($section->getSectionId(), $keep_ids)) {
                        $course_material->removeSection($section);
                    }
                }
            }
        }
        elseif ($_POST['sections_lock'] == "false") {
            $course_material->getSections()->clear();
        }
        if (isset($_POST['hide_from_students'])) {
            $course_material->setHiddenFromStudents($_POST['hide_from_students'] == 'on');
        }
        if (isset($_POST['sort_priority'])) {
            $course_material->setPriority($_POST['sort_priority']);
        }

        if (isset($_POST['release_time']) && $_POST['release_time'] != '') {
            $date_time = DateUtils::parseDateTime($_POST['release_time'], $this->core->getDateTimeNow()->getTimezone());
            $course_material->setReleaseDate($date_time);
        }

        if (isset($_POST['file_path'])) {
            $path = $course_material->getPath();
            $upload_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");
            $requested_path = $_POST['file_path'];
            $new_path = $upload_path . "/" . $requested_path;
            if ($course_material->isFile() && $path !== $new_path ) {
                if (!$this->checkValidPath($requested_path, $upload_path)) {
                    return JsonResponse::getErrorResponse("Invalid requested path");
                }
                $new_path = FileUtils::joinPaths($upload_path, $requested_path);
                // check valid directory
                $requested_path = explode("/", $requested_path);
                if (count($requested_path) > 1){
                    $dir = explode("/", $new_path);
                    array_pop($dir);
                    $dir = implode("/", $dir);                
                    $cm = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
                    ->findOneBy(['path' => $dir]);
                    if ($cm == null) {
                        return JsonResponse::getErrorResponse("The specified path does not exits. Please create folder(s) or move into existing folder");
                    }
                }
                $tmp_course_material = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
                        ->findOneBy(['path' => $new_path]);
                if ($tmp_course_material !== null) {
                    return JsonResponse::getErrorResponse("File already exists in current directory. Please rename file or put under different directory");
                }
                FileUtils::writeFile($new_path, "");
                rename($course_material->getPath(), $new_path);
                $course_material->setPath($new_path);
            }
            else if ($course_material->isLink()){
                $course_material->setUrl($_POST['file_path']);
            }
        }

        if (isset($_POST['display_name'])){
            $display_name = $_POST['display_name']; 
            $path = $course_material->getPath();
            $dirs = explode("/", $path);
            array_pop($dirs);
            $path = implode("/", $dirs);
            if ($course_material->isLink() && $display_name!== $course_material->getDisplayName() ){
                $path = FileUtils::joinPaths($path, urlencode("link-" . $display_name));
                $tmp_course_material = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
                    ->findOneBy(['path' => $path]);
                if ($tmp_course_material !== null) {
                    return JsonResponse::getErrorResponse("Link already exists with that title in that directory.");
                }
                FileUtils::writeFile($path, "");
                unlink($course_material->getPath());
                $course_material->setPath($path);
            } else if ($course_material->isFile()&& $display_name!== $course_material->getDisplayName()){
                $files = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
                    ->findDisplayName($path, $display_name);
                if (count($files) > 0) {
                    return JsonResponse::getErrorResponse("Display name already used in specified directory. Please rename or put under different directory");
                }

            }
            $course_material->setDisplayName($display_name);
        }

        if ($flush) {
            $this->core->getCourseEntityManager()->flush();
        }

        return JsonResponse::getSuccessResponse("Successfully uploaded!");
    }

    /**
     * @Route("/courses/{_semester}/{_course}/course_materials/upload", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function ajaxUploadCourseMaterialsFiles(): JsonResponse {
        $details = [];

        $expand_zip = "";
        if (isset($_POST['expand_zip'])) {
            $expand_zip = $_POST['expand_zip'];
        }

        $upload_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");

        $requested_path = "";
        if (!empty($_POST['requested_path'])) {
            $requested_path = $_POST['requested_path']; 
            if ( !$this->checkValidPath($_POST['requested_path'], $upload_path) ){
                return JsonResponse::getErrorResponse("Invalid requested path");
            }
        }

        $details['path'][0] = $requested_path;

        if (isset($_POST['release_time'])) {
            $details['release_date'] = $_POST['release_time'];
        }

        $sections_lock = false;
        if (isset($_POST['sections_lock'])) {
            $sections_lock = $_POST['sections_lock'] == "true";
        }
        $details['section_lock'] = $sections_lock;

        if (isset($_POST['sections']) && $sections_lock) {
            $sections = $_POST['sections'];
            $sections_exploded = @explode(",", $sections);
            $details['sections'] = $sections_exploded;
        }
        else {
            $details['sections'] = null;
        }

        if (isset($_POST['hide_from_students'])) {
            $details['hidden_from_students'] = $_POST['hide_from_students'] == "on";
        }

        if (isset($_POST['sort_priority'])) {
            $details['priority'] = $_POST['sort_priority'];
        }

        $n = strpos($requested_path, '..');
        if ($n !== false) {
            return JsonResponse::getErrorResponse("Invalid filepath.");
        }

        $url_title = null;
        if (isset($_POST['url_title'])) {
            $url_title = $_POST['url_title'];
        }

        $dirs_to_make = [];

        $url_url = null;
        if (isset($_POST['url_url'])) {
            if (!filter_var($_POST['url_url'], FILTER_VALIDATE_URL)) {
                return JsonResponse::getErrorResponse("Invalid url");
            }
            $url_url = $_POST['url_url'];
            if ($requested_path !== "") {
                $this->addDirs($requested_path, $upload_path, $dirs_to_make);
            }
        }

        if (isset($url_title) && isset($url_url)) {
            $details['type'][0] = CourseMaterial::LINK;
            $final_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");
            if (!empty($requested_path)) {
                $final_path = FileUtils::joinPaths($final_path, $requested_path);
                if (!FileUtils::createDir($final_path)) {
                    return JsonResponse::getErrorResponse("Failed to make path.");
                }
            }
            $details['path'][0] = FileUtils::joinPaths($final_path, urlencode("link-" . $url_title));
            if (file_exists($details['path'][0])) {
                return JsonResponse::getErrorResponse("Link with title already exists in this location. Please pick a different title.");
            }
            FileUtils::writeFile($details['path'][0], "");
        }
        else {
            $uploaded_files = [];
            if (isset($_FILES["files1"])) {
                $uploaded_files[1] = $_FILES["files1"];
            }

            if (empty($uploaded_files) && !(isset($url_url) && isset($url_title))) {
                return JsonResponse::getErrorResponse("No files were submitted.");
            }

            $status = FileUtils::validateUploadedFiles($_FILES["files1"]);
            if (array_key_exists("failed", $status)) {
                return JsonResponse::getErrorResponse("Failed to validate uploads " . $status['failed']);
            }

            $file_size = 0;
            foreach ($status as $stat) {
                $file_size += $stat['size'];
                if ($stat['success'] === false) {
                    return JsonResponse::getErrorResponse($stat['error']);
                }
            }

            $max_size = Utils::returnBytes(ini_get('upload_max_filesize'));
            if ($file_size > $max_size) {
                return JsonResponse::getErrorResponse("File(s) uploaded too large. Maximum size is " . ($max_size / 1024) . " kb. Uploaded file(s) was " . ($file_size / 1024) . " kb.");
            }

            if (!FileUtils::createDir($upload_path)) {
                return JsonResponse::getErrorResponse("Failed to make image path.");
            }
            // create nested path
            if (!empty($requested_path)) {
                $upload_nested_path = FileUtils::joinPaths($upload_path, $requested_path);
                if (!FileUtils::createDir($upload_nested_path, true)) {
                    return JsonResponse::getErrorResponse("Failed to make image path.");
                }
                $dirs_to_make = [];
                $this->addDirs($requested_path, $upload_path, $dirs_to_make);
                $upload_path = $upload_nested_path;
            }

            $count_item = count($status);
            if (isset($uploaded_files[1])) {
                $index = 0;
                for ($j = 0; $j < $count_item; $j++) {
                    if (is_uploaded_file($uploaded_files[1]["tmp_name"][$j])) {
                        $dst = FileUtils::joinPaths($upload_path, $uploaded_files[1]["name"][$j]);

                        $cm = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
                            ->findOneBy(['path' => $dst]);
                        if ($cm != null) {
                            return JsonResponse::getErrorResponse("A file already exists with path " .
                                $dst . ". Please delete the current file if you would like to use this path.");
                        }

                        if (strlen($dst) > 255) {
                            return JsonResponse::getErrorResponse("Path cannot have a string length of more than 255 chars.");
                        }

                        $is_zip_file = false;

                        if (mime_content_type($uploaded_files[1]["tmp_name"][$j]) == "application/zip") {
                            if (FileUtils::checkFileInZipName($uploaded_files[1]["tmp_name"][$j]) === false) {
                                return JsonResponse::getErrorResponse("You may not use quotes, backslashes, or angle brackets in your filename for files inside " . $uploaded_files[1]['name'][$j] . ".");
                            }
                            $is_zip_file = true;
                        }
                        //cannot check if there are duplicates inside zip file, will overwrite
                        //it is convenient for bulk uploads
                        if ($expand_zip == 'on' && $is_zip_file === true) {
                            //get the file names inside the zip to write to the JSON file

                            $zip = new \ZipArchive();
                            $res = $zip->open($uploaded_files[1]["tmp_name"][$j]);

                            if (!$res) {
                                return JsonResponse::getErrorResponse("Failed to open zip archive");
                            }

                            $entries = [];
                            $disallowed_folders = [".svn", ".git", ".idea", "__macosx"];
                            $disallowed_files = ['.ds_store'];
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $entries[] = $zip->getNameIndex($i);
                            }
                            $entries = array_filter($entries, function ($entry) use ($disallowed_folders, $disallowed_files) {
                                $name = strtolower($entry);
                                foreach ($disallowed_folders as $folder) {
                                    if (str_starts_with($folder, $name)) {
                                        return false;
                                    }
                                }
                                if (substr($name, -1) !== '/') {
                                    foreach ($disallowed_files as $file) {
                                        if (basename($name) === $file) {
                                            return false;
                                        }
                                    }
                                }
                                return true;
                            });
                            $zfiles = array_filter($entries, function ($entry) {
                                return substr($entry, -1) !== '/';
                            });

                            foreach ($zfiles as $zfile) {
                                $path = FileUtils::joinPaths($upload_path, $zfile);
                                $cm = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
                                    ->findOneBy(['path' => $path]);
                                if ($cm != null) {
                                    return JsonResponse::getErrorResponse("A file already exists with path " .
                                        $path . ". Please delete the current file if you would like to use this path.");
                                }
                            }

                            $zip->extractTo($upload_path, $entries);

                            foreach ($zfiles as $zfile) {
                                $path = FileUtils::joinPaths($upload_path, $zfile);
                                $details['type'][$index] = CourseMaterial::FILE;
                                $details['path'][$index] = $path;
                                if ($dirs_to_make == null) {
                                    $dirs_to_make = [];
                                }
                                $dirs = explode('/', $zfile);
                                array_pop($dirs);
                                $j = count($dirs);
                                $count = count($dirs_to_make);
                                foreach ($dirs as $dir) {
                                    for ($i = $count; $i < $j + $count; $i++) {
                                        if (!isset($dirs_to_make[$i])) {
                                            $dirs_to_make[$i] = $upload_path . '/' . $dir;
                                        }
                                        else {
                                            $dirs_to_make[$i] .= '/' . $dir;
                                        }
                                    }
                                    $j--;
                                }
                                $index++;
                            }
                        }
                        else {
                            if (!@copy($uploaded_files[1]["tmp_name"][$j], $dst)) {
                                return JsonResponse::getErrorResponse("Failed to copy uploaded file {$uploaded_files[1]['name'][$j]} to current location.");
                            }
                            else {
                                $details['type'][$index] = CourseMaterial::FILE;
                                $details['path'][$index] = $dst;
                                $index++;
                            }
                        }
                    }
                    else {
                        return JsonResponse::getErrorResponse("The tmp file '{$uploaded_files[1]['name'][$j]}' was not properly uploaded.");
                    }
                    // Is this really an error we should fail on?
                    if (!@unlink($uploaded_files[1]["tmp_name"][$j])) {
                        return JsonResponse::getErrorResponse("Failed to delete the uploaded file {$uploaded_files[1]['name'][$j]} from temporary storage.");
                    }
                }
            }
        }

        if ($dirs_to_make != null) {
            $i = -1;
            $new_paths = [];
            foreach ($dirs_to_make as $dir) {
                $cm = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)->findOneBy(
                    ['path' => $dir]
                );
                if ($cm == null && !in_array($dir, $new_paths)) {
                    $details['type'][$i] = CourseMaterial::DIR;
                    $details['path'][$i] = $dir;
                    $i--;
                    $new_paths[] = $dir;
                }
            }
        }

        foreach ($details['type'] as $key => $value) {
            $course_material = new CourseMaterial(
                $value,
                $details['path'][$key],
                DateUtils::parseDateTime($details['release_date'], $this->core->getDateTimeNow()->getTimezone()),
                $details['hidden_from_students'],
                $details['priority'],
                $value === CourseMaterial::LINK ? $url_url : null,
                $value === CourseMaterial::LINK ? $url_title : null
            );
            $this->core->getCourseEntityManager()->persist($course_material);
            if ($details['section_lock']) {
                foreach ($details['sections'] as $section) {
                    $course_material_section = new CourseMaterialSection($section, $course_material);
                    $course_material->addSection($course_material_section);
                }
            }
        }
        $this->core->getCourseEntityManager()->flush();
        return JsonResponse::getSuccessResponse("Successfully uploaded!");
    }

    private function addDirs(string $requested_path, string $upload_path, array &$dirs_to_make): void {
        $dirs = explode('/', $requested_path);
        $j = count($dirs);
        foreach ($dirs as $dir) {
            for ($i = 0; $i < $j; $i++) {
                if (!isset($dirs_to_make[$i])) {
                    $dirs_to_make[$i] = $upload_path . '/' . $dir;
                }
                else {
                    $dirs_to_make[$i] .= '/' . $dir;
                }
            }
            $j--;
        }
    }

    private function checkValidPath(string $requested_path, string $upload_path): bool {
        $tmp_path = $upload_path . "/" . $requested_path;
        $dirs = explode("/", $tmp_path);
        for ($i = 1; $i < count($dirs); $i++) {
            if ($dirs[$i] === "") {
                return false;
            }
        }
        return true;
    }
}

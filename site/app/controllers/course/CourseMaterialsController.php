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

const DIR = 2;

class CourseMaterialsController extends AbstractController {
    #[Route("/courses/{_semester}/{_course}/course_materials")]
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

    #[Route("/courses/{_semester}/{_course}/course_material/{path}", requirements: ["path" => ".+"])]
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

    #[Route("/courses/{_semester}/{_course}/course_materials/view", methods: ["POST"])]
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

    #[Route("/courses/{_semester}/{_course}/course_materials/viewAll", methods: ["POST"])]
    public function setAllViewed(): JsonResponse {
        $cms = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
            ->findAll();
        foreach ($cms as $cm) {
            if ($cm->isHiddenFromStudents() || $cm->getReleaseDate() > $this->core->getDateTimeNow() || $cm->isDir()) {
                continue;
            }
            $cm_access = new CourseMaterialAccess($cm, $this->core->getUser()->getId(), $this->core->getDateTimeNow());
            $cm->addAccess($cm_access);
        }
        $this->core->getCourseEntityManager()->flush();
        return JsonResponse::getSuccessResponse();
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/course_materials/delete")]
    public function deleteCourseMaterial($id) {
        $cm = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)
            ->findOneBy(['id' => $id]);
        if ($cm === null) {
            $this->core->addErrorMessage("Failed to delete course material");
            return new RedirectResponse($this->core->buildCourseUrl(['course_materials']));
        }
        // security check
        $dir = "course_materials";
        $path = $this->core->getAccess()->resolveDirPath($dir, $cm->getPath());
        if ($path === false) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            return new RedirectResponse($this->core->buildCourseUrl(['course_materials']));
        }
        // check to prevent the deletion of course_materials folder
        if ($path === FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials")) {
            $this->core->addErrorMessage(basename($path) . " can't be removed.");
            return new RedirectResponse($this->core->buildCourseUrl(['course_materials']));
        }
        if (!$this->core->getAccess()->canI("path.write", ["path" => $path, "dir" => $dir])) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            return new RedirectResponse($this->core->buildCourseUrl(['course_materials']));
        }

        if ($cm->getType() === DIR) {
            $all_files = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)->findAll();
            foreach ($all_files as $file) {
                if (str_starts_with(pathinfo($file->getPath(), PATHINFO_DIRNAME), $path) || ($file->getPath() === $path)) {
                    $this->core->getCourseEntityManager()->remove($file);
                }
            }
        }
        else {
            $this->core->getCourseEntityManager()->remove($cm);
        }
        $success = false;
        if (is_dir($path)) {
            $success = FileUtils::recursiveRmdir($path);
        }
        else {
            $success = unlink($path);
        }
        $base_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");
        // delete the topmost parent folder that's empty (contains no files)
        if (pathinfo($path, PATHINFO_DIRNAME) !== $base_path) {
            $empty_folders = [];
            FileUtils::getTopEmptyDir($path, $base_path, $empty_folders);
            if (count($empty_folders) > 0) {
                $path = $empty_folders[0];
                $success = $success && FileUtils::recursiveRmdir($path);
                if (!isset($all_files)) {
                    $all_files = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)->findAll();
                }
                foreach ($all_files as $file) {
                    if (str_starts_with($file->getPath(), $path)) {
                        $this->core->getCourseEntityManager()->remove($file);
                    }
                }
            }
        }
        $this->core->getCourseEntityManager()->flush();
        if ($success) {
            $this->core->addSuccessMessage(basename($path) . " has been successfully removed.");
        }
        else {
            $this->core->addErrorMessage("Failed to remove " . basename($path));
        }

        return new RedirectResponse($this->core->buildCourseUrl(['course_materials']));
    }

    #[Route("/courses/{_semester}/{_course}/course_materials/download_zip")]
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

        // create a new zipstream object
        $zip_stream = new \ZipStream\ZipStream(
            outputName: $zip_file_name,
            sendHttpHeaders: true,
            enableZip64: false,
        );

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
     * @AccessControl(role="INSTRUCTOR")
     * @return JsonResponse
     */
    #[Route("/courses/{_semester}/{_course}/course_materials/release_all")]
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
                if (str_starts_with(pathinfo($cm->getPath(), PATHINFO_DIRNAME), $courseMaterial->getPath()) && $cm->getPath() !== $courseMaterial->getPath()) {
                    $this->setFileTimeStamp($cm, $courseMaterials, $dateTime);
                }
            }
        }
        else {
            $courseMaterial->setReleaseDate($dateTime);
        }
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/course_materials/modify_timestamp")]
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

        if ($courseMaterial === null || empty($courseMaterials)) {
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
        $main_path =  $main_course_material->getPath();

        foreach ($course_materials as $course_material) {
            $course_material_path = $course_material->getPath();
            $course_material_dir = pathinfo($course_material->getPath(), PATHINFO_DIRNAME);

            $same_start = str_starts_with($course_material_dir, $main_path);
            $not_same_file = $course_material_path !== $main_path;

            // Third condition prevents cases where two folders are "name" and "name_plus_more_text".
            if ($same_start && $not_same_file && $course_material_path[strlen($main_path)] === '/') {
                if ($course_material->isDir()) {
                    $this->recursiveEditFolder($course_materials, $course_material);
                }
                else {
                    $_POST['id'] = $course_material->getId();
                    $this->ajaxEditCourseMaterialsFiles(false);
                }
            }
        }
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/course_materials/edit", methods: ["POST"])]
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

        //* if the course material is a directory
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

        $this->handleSectionLock($course_material, $_POST);


        $this->updateCourseMaterial($course_material, $_POST['hide_from_students'], $_POST['sort_priority'], $_POST['release_time']);


        $course_material->setLastEditBy($this->core->getUser()->getId());
        $course_material->setLastEditDate(DateUtils::parseDateTime($this->core->getDateTimeNow(), $this->core->getDateTimeNow()->getTimezone()));

        if (isset($_POST['file_path']) || isset($_POST['title'])) {
            $path = $course_material->getPath();
            $upload_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");
            $requested_path = $_POST['file_path'];
            $new_path = FileUtils::joinPaths($upload_path, $requested_path);

            if (isset($_POST['title'])) {
                $file_name = $_POST['title'];
                $directory = dirname($new_path);
                $new_path = FileUtils::joinPaths($directory, $file_name);
            }
            else {
                $file_name = basename($new_path);
            }

            
            if ($path !== $new_path) {
                if (!FileUtils::ValidPath($new_path)) {
                    return JsonResponse::getErrorResponse("Invalid path or filename");
                }

                $requested_path = explode("/", $requested_path);
                if (count($requested_path) > 1) {
                    $requested_path_directories = $requested_path;
                    array_pop($requested_path_directories);
                    $requested_path_directories = implode("/", $requested_path_directories);
                    $full_dir_path = explode("/", $new_path);
                    array_pop($full_dir_path);
                    $full_dir_path = implode("/", $full_dir_path);



                    //ADD IN NEW DIRECTORIES IF IT DOESN'T EXIST


                    if (!FileUtils::createDir($full_dir_path, true)) {
                        return JsonResponse::getErrorResponse("Invalid requested path");
                    }


                    $dirs_to_make = [];
                    $this->addDirs($requested_path_directories, $upload_path, $dirs_to_make);


                    if ($dirs_to_make != null) {
                        $new_paths = [];
                        foreach ($dirs_to_make as $dir) {
                            $cm = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)->findOneBy(
                                ['path' => $dir]
                            );
                            if ($cm === null && !in_array($dir, $new_paths)) {
                                $course_material_dir = new CourseMaterial(
                                    2,
                                    $dir,
                                    $course_material->getReleaseDate(),
                                    $course_material->isHiddenFromStudents(),
                                    $course_material->getPriority(),
                                    null,
                                    null,
                                    $course_material->getUploadedBy(),
                                    $course_material->getUploadedDate(),
                                    null,
                                    null
                                );
                                $this->core->getCourseEntityManager()->persist($course_material_dir);
                                $all_sections = $course_material->getSections()->getValues();

                                if (count($all_sections) > 0) {
                                    foreach ($all_sections as $section) {
                                        $course_material_section = new CourseMaterialSection($section->getSectionID(), $course_material_dir);
                                        $course_material_dir->addSection($course_material_section);
                                    }
                                }
                                $new_paths[] = $dir;
                            }
                        }
                    }
                }
                $overwrite = false;
                if (isset($_POST['overwrite']) && $_POST['overwrite'] === 'true') {
                    $overwrite = true;
                }

                $dir = dirname($new_path);
                $clash_resolution = $this->resolveClashingMaterials($dir, [$file_name], $overwrite);
                if ($clash_resolution !== true) {
                    return JsonResponse::getErrorResponse(
                        'Name clash',
                        $clash_resolution
                    );
                }

                rename($course_material->getPath(), $new_path);
                $course_material->setPath($new_path);
                if (isset($_POST['original_title'])) {
                    $course_material->setTitle($_POST['original_title']);
                }
            }
        }


        if (isset($_POST['link_url']) && isset($_POST['title']) && $course_material->isLink()) {
            $course_material->setUrl($_POST['link_url']);
        }

        if ($flush) {
            $this->core->getCourseEntityManager()->flush();
        }

        return JsonResponse::getSuccessResponse("Successfully uploaded!");
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/course_materials/upload", methods: ["POST"])]
    public function ajaxUploadCourseMaterialsFiles(): JsonResponse {
        $details = [];
        $expand_zip = "";
        if (isset($_POST['expand_zip'])) {
            $expand_zip = $_POST['expand_zip'];

        }


        //configure upload path & requested path

        $upload_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");

        $details['path'][0] = $this->getRequestedPath($upload_path, $_POST['requested_path']);

        //------------------------------------------------------


        $overwrite_all = false;
        if (isset($_POST['overwrite_all']) && $_POST['overwrite_all'] === 'true') {
            $overwrite_all = true;
        }

        $n = strpos($requested_path, '..');
        if ($n !== false) {
            return JsonResponse::getErrorResponse("Invalid filepath.");
        }

        $title = null;
        if (isset($_POST['title'])) {
            $title = $_POST['title'];
        }

        $title_name = $title;
        if (isset($_POST['original_title'])) {
            $title_name = $_POST['original_title'];
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

        if (isset($title) && isset($url_url)) {
            $details['type'][0] = CourseMaterial::LINK;
            $final_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");
            if (!empty($requested_path)) {
                $final_path = FileUtils::joinPaths($final_path, $requested_path);
                if (!FileUtils::createDir($final_path)) {
                    return JsonResponse::getErrorResponse("Failed to make path.");
                }
            }
            $file_name = $title;
            $clash_resolution = $this->resolveClashingMaterials($final_path, [$file_name], $overwrite_all);
            if ($clash_resolution !== true) {
                return JsonResponse::getErrorResponse(
                    'Name clash',
                    $clash_resolution
                );
            }
            $details['path'][0] = FileUtils::joinPaths($final_path, $file_name);
            FileUtils::writeFile($details['path'][0], "");
        }
        else {
            $uploaded_files = [];
            if (isset($_FILES["files1"])) {
                $uploaded_files[1] = $_FILES["files1"];
            }

            if (empty($uploaded_files) && !(isset($url_url) && isset($title))) {
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
                $clash_resolution = $this->resolveClashingMaterials($upload_path, $uploaded_files[1]['name'], $overwrite_all);
                if ($clash_resolution !== true) {
                    return JsonResponse::getErrorResponse(
                        'Name clash',
                        $clash_resolution
                    );
                }
                $index = 0;
                for ($j = 0; $j < $count_item; $j++) {
                    if (is_uploaded_file($uploaded_files[1]["tmp_name"][$j])) {
                        $dst = FileUtils::joinPaths($upload_path, $uploaded_files[1]["name"][$j]);

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
                            $double_dot = ["../","..\\","/..","\\.."];
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $entries[] = $zip->getNameIndex($i);
                                //check to ensure that entry name doesn't have ..
                                $dot_check = array_filter($double_dot, function ($dot) use ($entries) {
                                    if (strpos($entries[count($entries) - 1], $dot) !== false) {
                                        return true;
                                    }
                                    return false;
                                });
                                if (count($dot_check) !== 0) {
                                    return JsonResponse::getErrorResponse("Uploaded zip archive contains at least one file with invalid name.");
                                }
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

                            $clash_resolution = $this->resolveClashingMaterials($upload_path, $zfiles, $overwrite_all);
                            if ($clash_resolution !== true) {
                                return JsonResponse::getErrorResponse(
                                    'Name clash',
                                    $clash_resolution
                                );
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
                $date_time = new \DateTime('9999-12-31 23:59:59'),
                false, //hide_from_students
                0.0, //priority
                $value === CourseMaterial::LINK ? $url_url : null,
                $value === CourseMaterial::LINK ? $title_name : null,
                uploaded_by: $this->core->getUser()->getId(),
                uploaded_date: DateUtils::parseDateTime($this->core->getDateTimeNow(), $this->core->getDateTimeNow()->getTimezone()),
                last_edit_by: null,
                last_edit_date: null
            );
            $this->handleSectionLock($course_material, $_POST);
            $this->updateCourseMaterial($course_material, $_POST['hide_from_students'], $_POST['sort_priority'], $_POST['release_time']);

            $this->core->getCourseEntityManager()->persist($course_material);
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

    /**
     * @return array<int, string>|bool true
     */
    private function resolveClashingMaterials(string $upload_path, array $file_names, bool $overwrite_all) {
        $prepend_path = function ($elem) use ($upload_path) {
            return FileUtils::joinPaths($upload_path, $elem);
        };
        $file_names = array_map($prepend_path, $file_names);
        $c_materials = $this->core->getCourseEntityManager()->getRepository(CourseMaterial::class)->
            findBy(['path' => $file_names]);
        $clashing_materials = [];
        foreach ($c_materials as $cm) {
            $clashing_materials[$cm->getId()] = $cm->getPath();
        }
        if ($clashing_materials !== []) {
            if ($overwrite_all) {
                $em = $this->core->getCourseEntityManager();
                foreach ($clashing_materials as $id => $path) {
                    $cm_ref = $em->getReference(CourseMaterial::class, $id);
                    $em->remove($cm_ref);
                    unlink($path);
                }
                $em->flush();
                return true;
            }
            return array_values($clashing_materials);
        }
        return true;
    }

    private function updateCourseMaterial($course_material, $hide_from_students, $sort_priority, $release_time = null) {
        // Update visibility
        if (isset($hide_from_students)) {
            $course_material->setHiddenFromStudents($hide_from_students == 'on');
        }
    
        // Update sorting priority
        if (isset($sort_priority)) {
            $course_material->setPriority($sort_priority);
        }
    
        // Update release time if provided
        if (isset($release_time) && $release_time != '') {
            $date_time = DateUtils::parseDateTime($release_time, $this->core->getDateTimeNow()->getTimezone());
            $course_material->setReleaseDate($date_time);
        }
    }
    
    
    private function handleSectionLock($course_material, $post_data) {
        // Default section lock to false
        $sections_lock = isset($post_data['sections_lock']) && $post_data['sections_lock'] == "true";
        $details = ['section_lock' => $sections_lock];
    
        // Handle sections if section lock is enabled
        if ($sections_lock) {
            // Handle sections from POST data
            if (isset($post_data['sections'])) {
                $sections = explode(",", $post_data['sections']);
                
                // If no sections are selected
                if (empty($sections[0])) {
                    return JsonResponse::getErrorResponse("Select at least one section");
                }
                
                // Populate details with exploded sections
                $details['sections'] = $sections;
    
                // Handle section addition and removal
                $keep_ids = $sections;
                $partial_sections = isset($post_data['partial_sections']) ? explode(",", $post_data['partial_sections']) : [];
    
                // Add new sections to course material
                foreach ($sections as $section) {
                    if (!$this->sectionExists($course_material, $section)) {
                        $course_material->addSection(new CourseMaterialSection($section, $course_material));
                    }
                }
    
                // Remove sections that are no longer valid
                foreach ($course_material->getSections() as $section) {
                    if (!in_array($section->getSectionId(), $keep_ids) && !in_array($section->getSectionId(), $partial_sections)) {
                        $course_material->removeSection($section);
                    }
                }
            } else {
                $details['sections'] = null;
            }
        } else {
            // If section lock is disabled, clear all sections
            $course_material->getSections()->clear();
            $details['sections'] = null;
        }
    
        return $details;
    }
    
    // Helper function to check if a section exists
    private function sectionExists($course_material, $section_id) {
        foreach ($course_material->getSections() as $course_section) {
            if ($course_section->getSectionId() === $section_id) {
                return true;
            }
        }
        return false;
    }

    private function getRequestedPath($upload_path, $post_requested_path) {
        $requested_path = "";
        if (!empty(post_requested_path)) {
            $requested_path = $post_requested_path;
            $tmp_path = $upload_path . "/" . $requested_path;
            $dirs = explode("/", $tmp_path);
            for ($i = 1; $i < count($dirs); $i++) {
                if ($dirs[$i] === "") {
                    return JsonResponse::getErrorResponse("Invalid requested path");
                }
            }
        }
        return $requested_path;
    }
    

}

<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\response\RedirectResponse;
use app\libraries\routers\AccessControl;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\models\gradeable\Gradeable;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AutogradingConfigController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 */
class AutogradingConfigController extends AbstractController {
    /**
     * @Route("/courses/{_semester}/{_course}/autograding_config", methods={"GET"})
     * @param string $g_id gradeable Id
     * @return MultiResponse
     */
    public function showConfig($g_id = '') {
        $target_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "config_upload");
        $all_files = FileUtils::getAllFiles($target_dir);
        $all_paths = [];
        foreach ($all_files as $file) {
            $all_paths[] = $file['path'];
        }
        $inuse_config = [];
        foreach ($this->core->getQueries()->getGradeableConfigs(null) as $gradeable) {
            foreach ($all_paths as $path) {
                if ($gradeable->getAutogradingConfigPath() === $path) {
                    $inuse_config[] = $path;
                }
            }
        }
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                ['admin', 'Gradeable'],
                'uploadConfigForm',
                $target_dir,
                $all_files,
                $g_id,
                $inuse_config
            )
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/autograding_config/upload", methods={"POST"})
     * @param string $g_id gradeable Id
     */
    public function uploadConfig($g_id = ''): MultiResponse {
        $redirect_url = empty($g_id) ? $this->core->buildCourseUrl((['autograding_config']))
            : $this->core->buildCourseUrl(['autograding_config']) . '?g_id=' . $g_id;

        if (empty($_FILES) || !isset($_FILES['config_upload'])) {
            $msg = 'Upload failed: No file to upload';
            $this->core->addErrorMessage($msg);
            return new MultiResponse(
                JsonResponse::getErrorResponse($msg),
                null,
                new RedirectResponse($redirect_url)
            );
        }

        $upload = $_FILES['config_upload'];
        if (!isset($upload['tmp_name']) || $upload['tmp_name'] === "") {
            $msg = 'Upload failed: Empty tmp name for file';
            $this->core->addErrorMessage($msg);
            return new MultiResponse(
                JsonResponse::getErrorResponse($msg),
                null,
                new RedirectResponse($redirect_url)
            );
        }

        $target_dir = $this->createConfigDirectory();

        if (mime_content_type($upload["tmp_name"]) == "application/zip") {
            $zip = new \ZipArchive();
            $res = $zip->open($upload['tmp_name']);
            if ($res === true) {
                $zip->extractTo($target_dir);
                $zip->close();
            }
            else {
                FileUtils::recursiveRmdir($target_dir);
                $error_message = ($res == 19) ? "Invalid or uninitialized Zip object" : $zip->getStatusString();
                $msg = "Upload failed: {$error_message}";
                $this->core->addErrorMessage($msg);
                return new MultiResponse(
                    JsonResponse::getErrorResponse($msg),
                    null,
                    new RedirectResponse($redirect_url)
                );
            }
        }
        else {
            if (!@copy($upload['tmp_name'], FileUtils::joinPaths($target_dir, $upload['name']))) {
                FileUtils::recursiveRmdir($target_dir);
                $msg = 'Upload failed: Could not copy file';
                $this->core->addErrorMessage($msg);
                return new MultiResponse(
                    JsonResponse::getErrorResponse($msg),
                    null,
                    new RedirectResponse($redirect_url)
                );
            }
        }
        $msg = 'Gradeable config uploaded';
        $this->core->addSuccessMessage($msg);
        return new MultiResponse(
            JsonResponse::getSuccessResponse([
                'config_path' => $target_dir
            ]),
            null,
            new RedirectResponse($redirect_url)
        );
    }

    /**
     * Generates a new configuration directory in the course's 'config_upload' directory.  The name for the new
     * directory starts at '1' with additional created directories being named incrementally.
     *
     * @return string The absolute path to/including the new directory
     */
    public function createConfigDirectory(): string {
        $target_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "config_upload");
        $counter = count(scandir($target_dir)) - 1;
        $try_dir = FileUtils::joinPaths($target_dir, $counter);
        while (is_dir($try_dir)) {
            $counter++;
            $try_dir = FileUtils::joinPaths($target_dir, $counter);
        }
        $target_dir = $try_dir;
        FileUtils::createDir($target_dir);
        return $target_dir;
    }

    /**
     * @Route("/courses/{_semester}/{_course}/autograding_config/rename", methods={"POST"})
     * @param string $g_id gradeable Id
     * @return MultiResponse
     */
    public function renameConfig($g_id = '') {
        $config_file_path = $_POST['curr_config_name'] ?? null;
        if ($config_file_path == null) {
            $this->core->addErrorMessage("Unable to find file");
        }
        elseif (strpos($config_file_path, FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "config_upload")) === false) {
            $this->core->addErrorMessage("This action can't be completed.");
        }
        else {
            $new_name = $_POST['new_config_name'] ?? "";
            if ($new_name === "") {
                $this->core->addErrorMessage("Could not rename upload because no name was entered.");
            }
            elseif (!ctype_alnum(str_replace(['_','-'], '', $new_name))) {
                $this->core->addErrorMessage("Name can only contain alphanumeric characters, dashes, and underscores.");
            }
            else {
                $new_dir = FileUtils::joinPaths(dirname($config_file_path, 1), $new_name);
                if (rename($config_file_path, $new_dir)) {
                    $this->core->addSuccessMessage("Successfully renamed file");
                }
                else {
                    $this->core->addErrorMessage("Directory already exist, please choose another name.");
                }
            }
        }
        $redirect_url = empty($g_id) ? $this->core->buildCourseUrl((['autograding_config']))
            : $this->core->buildCourseUrl(['autograding_config']) . '?g_id=' . $g_id;
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($redirect_url)
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/autograding_config/delete", methods={"POST"})
     * @param string $g_id gradeable Id
     * @return MultiResponse
     */
    public function deleteConfig($g_id = '') {
        $config_path = $_POST['config_path'] ?? null;
        $in_use = false;
        foreach ($this->core->getQueries()->getGradeableConfigs(null) as $gradeable) {
            if ($gradeable->getAutogradingConfigPath() === $config_path) {
                $in_use = true;
                break;
            }
        }
        if ($config_path == null) {
            $this->core->addErrorMessage("Selecting config failed.");
        }
        elseif (strpos($config_path, FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "config_upload")) === false) {
            $this->core->addErrorMessage("This action can't be completed.");
        }
        elseif ($in_use) {
            $this->core->addErrorMessage("This config is currently in use.");
        }
        else {
            if (FileUtils::recursiveRmdir($config_path)) {
                $this->core->addSuccessMessage("The config folder has been succesfully deleted");
            }
            else {
                $this->core->addErrorMessage("Deleting config failed.");
            }
        }
        $redirect_url = empty($g_id) ? $this->core->buildCourseUrl((['autograding_config']))
            : $this->core->buildCourseUrl(['autograding_config']) . '?g_id=' . $g_id;
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($redirect_url)
        );
    }

    /**
     * @param $config_path
     * @Route("/courses/{_semester}/{_course}/autograding_config/usage", methods={"GET"})
     * @return MultiResponse
     */
    public function configUsedBy($config_path = null) {
        $config_path = urldecode($config_path);
        // Returns a list of gradeables that are using this config
        if ($config_path) {
            $inuse_config = [];
            foreach ($this->core->getQueries()->getGradeableConfigs(null) as $gradeable) {
                if ($gradeable->getAutogradingConfigPath() === $config_path) {
                    $inuse_config[] = $gradeable->getId();
                }
            }
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getSuccessResponse($inuse_config)
            );
        }
        return MultiResponse::JsonOnlyResponse(
            JsonResponse::getFailResponse("Config path can't be empty.")
        );
    }
}

<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\response\RedirectResponse;
use app\libraries\routers\AccessControl;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\Utils;
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

        $target_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "config_upload");
        $counter = count(scandir($target_dir)) - 1;
        $try_dir = FileUtils::joinPaths($target_dir, $counter);
        while (is_dir($try_dir)) {
            $counter++;
            $try_dir = FileUtils::joinPaths($target_dir, $counter);
        }
        $target_dir = $try_dir;
        FileUtils::createDir($target_dir);

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

    /**
     * @Route("/courses/{_semester}/{_course}/notebook_builder/{g_id}/{mode<new|edit>}", methods={"GET"})
     * @param string $g_id Gradeable ID
     * @param string $mode The mode notebook builder should open in.  May be either 'new' or 'edit', this lets
     * notebook builder know to save a new configuration or edit the existing one
     * @AccessControl(role="INSTRUCTOR")
     */
    public function notebookBuilder(string $g_id, string $mode) {
        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($g_id);
        }
        catch (\Exception $exception) {
            return new RedirectResponse($this->core->buildUrl());
        }

        $failure_url = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'update']) . '?nav_tab=1';

        if ($gradeable->isUsingDefaultConfig() && $mode === 'edit') {
            $this->core->addErrorMessage("You may not edit a provided configuration.  Press the 'Start New' button to start a new configuration instead.");
            return new RedirectResponse($failure_url);
        }

        $json_path = $gradeable->getAutogradingConfigPath() . '/config.json';
        $json_contents = file_get_contents($json_path);

        $config_string = $mode === 'edit' ? $json_contents : '{"notebook": [], "testcases": []}';
        $config_string = Utils::stripComments($config_string);

        // Remove pretty print by decoding and re-encoding
        $config_string = json_decode($config_string);
        $config_string = json_encode($config_string);

        if ($config_string === 'null') {
            $this->core->addErrorMessage('Failure attempting to load the current configuration.');
            return new RedirectResponse($failure_url);
        }

        $config_string = Utils::escapeDoubleQuotes($config_string);

        $this->core->getOutput()->addInternalJs('notebook_builder/notebook-builder.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/widget.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/selector-widget.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/form-options-widget.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/markdown-widget.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/multiple-choice-widget.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/short-answer-widget.js');
        $this->core->getOutput()->addInternalCss('notebook-builder.css');

        $this->core->getOutput()->renderTwigOutput('admin/NotebookBuilder.twig', [
            'gradeable' => $gradeable,
            'config_string' => $config_string,
            'mode' => $mode
        ]);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/notebook_builder/save", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function notebookBuilderSave(): JsonResponse {
        if ($_POST['mode'] === 'new') {
            return $this->notebookBuilderSaveNew();
        }
        elseif ($_POST['mode'] === 'edit') {
            return $this->notebookBuilderSaveExisting();
        }

        return JsonResponse::getErrorResponse('Illegal "mode" was passed.');
    }

    /**
     * Helper function for saving a new config.json using notebook builder
     *
     * @return JsonResponse
     */
    private function notebookBuilderSaveNew(): JsonResponse {
        $result = $this->uploadConfig();

        if ($result->json_response->json['status'] === 'success') {
            $config_path = $result->json_response->json['data']['config_path'];

            // Update current gradeable to use this new configuration
            $gradeable = $this->core->getQueries()->getGradeableConfig($_POST['g_id']);
            $gradeable->setAutogradingConfigPath($config_path);
            $this->core->getQueries()->updateGradeable($gradeable);

            $this->notebookBuilderRebuildGradeable($gradeable);
        }

        return $result->json_response;
    }

    /**
     * Helper function used for editing and saving an existing config.json using notebook builder
     *
     * @return JsonResponse
     */
    private function notebookBuilderSaveExisting(): JsonResponse {
        // Overwrite existing configuration with newly uploaded one
        $gradeable = $this->core->getQueries()->getGradeableConfig($_POST['g_id']);
        $json_path = FileUtils::joinPaths($gradeable->getAutogradingConfigPath(), 'config.json');
        $move_res = move_uploaded_file($_FILES['config_upload']['tmp_name'], $json_path);

        // Update group permission
        $group = $this->core->getConfig()->getCourse() . '_tas_www';
        $permission_res = chgrp($json_path, $group);

        $this->notebookBuilderRebuildGradeable($gradeable);

        return $move_res && $permission_res ? JsonResponse::getSuccessResponse() : JsonResponse::getErrorResponse('An error occurred saving the modified config.json.');
    }

    /**
     * Helper function used to trigger a gradeable rebuild used in the notebook builder save process.
     */
    private function notebookBuilderRebuildGradeable(Gradeable $gradeable): void {
        $admin_gradeable_controller = new AdminGradeableController($this->core);
        $admin_gradeable_controller->enqueueBuild($gradeable);
    }
}

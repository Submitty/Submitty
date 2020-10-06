<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\CodeMirrorUtils;
use app\libraries\FileUtils;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\Utils;
use app\models\gradeable\Gradeable;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;

class NotebookBuilderController extends AbstractController {

    /**
     * @Route("/courses/{_semester}/{_course}/notebook_builder/{g_id}/{mode<new|edit>}", methods={"GET"})
     * @param string $g_id Gradeable ID
     * @param string $mode The mode notebook builder should open in.  May be either 'new' or 'edit', this lets
     * notebook builder know to save a new configuration or edit the existing one.
     * @AccessControl(role="INSTRUCTOR")
     */
    public function builder(string $g_id, string $mode) {
        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($g_id);
        }
        catch (\Exception $exception) {
            $this->core->addErrorMessage('Invalid Gradeable ID.');
            return new RedirectResponse($this->core->buildUrl());
        }

        $failure_url = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'update']) . '?nav_tab=1';

        if (!$gradeable->isUsingUploadedConfig()) {
            $this->core->addErrorMessage("Notebook builder may only edit uploaded configurations for the current course and semester.");
            return new RedirectResponse($failure_url);
        }

        // If mode is new then generate a new config directory and place a default config.json inside of it
        if ($mode === 'new') {
            $autograding_config_controller = new AutogradingConfigController($this->core);
            $config_dir = $autograding_config_controller->createConfigDirectory();

            // Verify new directory was created
            $permission_failures = FileUtils::checkForPermissionErrors($config_dir, 'submitty_php', $this->core->getConfig()->getCourse() . '_tas_www');
            if ($permission_failures) {
                foreach ($permission_failures as $failure) {
                    $this->core->addErrorMessage($failure);
                }

                return new RedirectResponse($failure_url);
            }

            $gradeable->setAutogradingConfigPath($config_dir);
            $this->core->getQueries()->updateGradeable($gradeable);
            $json_path = FileUtils::joinPaths($config_dir, 'config.json');
            file_put_contents($json_path, '{"notebook": [], "testcases": []}');

            // Verify default json was created
            $permission_failures = FileUtils::checkForPermissionErrors($json_path, 'submitty_php', $this->core->getConfig()->getCourse() . '_tas_www');
            if ($permission_failures) {
                foreach ($permission_failures as $failure) {
                    $this->core->addErrorMessage($failure);
                }

                return new RedirectResponse($failure_url);
            }

            // Redirect to same page but with mode now set to 'edit'
            return new RedirectResponse($this->core->buildCourseUrl(['notebook_builder', $gradeable->getId(), 'edit']));
        }

        $images = json_encode($this->getFiles(FileUtils::joinPaths($gradeable->getAutogradingConfigPath(), 'test_input')));

        $json_path = $gradeable->getAutogradingConfigPath() . '/config.json';

        $json_contents = file_get_contents($json_path);
        $config_string = Utils::stripComments($json_contents);

        // Remove pretty print by decoding and re-encoding
        $config_string = json_decode($config_string);
        $config_string = json_encode($config_string);

        if ($config_string === 'null') {
            $this->core->addErrorMessage('Failure attempting to load the current configuration.');
            return new RedirectResponse($failure_url);
        }

        $config_string = Utils::escapeDoubleQuotes($config_string);

        $this->loadDependencies();

        $this->core->getOutput()->renderTwigOutput('admin/NotebookBuilder.twig', [
            'gradeable' => $gradeable,
            'config_string' => $config_string,
            'images' => $images,
            'codemirror_languages' => json_encode(CodeMirrorUtils::MIME_TYPE_MAP)
        ]);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/notebook_builder/save", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function save(): JsonResponse {
        $gradeable = $this->getValidGradeable($_POST['g_id']);

        if (is_string($gradeable)) {
            return JsonResponse::getErrorResponse($gradeable);
        }

        // Overwrite existing configuration with newly uploaded one
        $json_path = FileUtils::joinPaths($gradeable->getAutogradingConfigPath(), 'config.json');
        move_uploaded_file($_FILES['config_upload']['tmp_name'], $json_path);
        $this->updateGroupPermission($json_path);

        // Check for permission failures
        $permission_failures = FileUtils::checkForPermissionErrors($json_path, 'submitty_php', $this->core->getConfig()->getCourse() . '_tas_www');
        if ($permission_failures) {
            return JsonResponse::getErrorResponse('An error occurred saving the modified config.json.', $permission_failures);
        }

        $this->rebuildGradeable($gradeable);

        return JsonResponse::getSuccessResponse();
    }

    /**
     * @Route("/courses/{_semester}/{_course}/notebook_builder/file", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function file(): JsonResponse {
        $gradeable = $this->getValidGradeable($_POST['g_id']);

        if (is_string($gradeable)) {
            return JsonResponse::getErrorResponse($gradeable);
        }

        if ($_POST['operation'] === 'upload') {
            return $this->fileUpload($gradeable);
        }

        return JsonResponse::getErrorResponse('Invalid operation.');
    }

    /**
     * Helper function which handles loading all the js / css dependencies notebook builder needs
     */
    private function loadDependencies(): void {
        // Builders
        $this->core->getOutput()->addInternalJs('notebook_builder/builders/abstract-builder.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/builders/root-builder.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/builders/itempool-builder.js');

        // Widgets
        $this->core->getOutput()->addInternalJs('notebook_builder/widgets/widget.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/widgets/selector-widget.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/widgets/form-options-widget.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/widgets/markdown-widget.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/widgets/multiple-choice-widget.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/widgets/short-answer-widget.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/widgets/image-widget.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/widgets/itempool-widget.js');
        $this->core->getOutput()->addInternalJs('notebook_builder/widgets/item-widget.js');

        // Others
        CodeMirrorUtils::loadDefaultDependencies($this->core);
        $this->core->getOutput()->addInternalJs('notebook_builder/notebook-builder-utils.js');
        $this->core->getOutput()->addInternalCss('notebook-builder.css');
    }

    /**
     * Helper function which deals with capturing files uploaded via the notebook builder 'file' endpoint
     *
     * @param Gradeable $gradeable
     * @return JsonResponse
     */
    private function fileUpload(Gradeable $gradeable): JsonResponse {
        // Create directory if it doesn't exist
        $directory_path = FileUtils::joinPaths($gradeable->getAutogradingConfigPath(), $_POST['directory']);
        FileUtils::createDir($directory_path);

        // Move uploaded file
        $full_path = FileUtils::joinPaths($directory_path, $_FILES['file']['name']);
        $move_res = move_uploaded_file($_FILES['file']['tmp_name'], $full_path);
        $permission_res = $this->updateGroupPermission($full_path);

        return $move_res && $permission_res ? JsonResponse::getSuccessResponse() : JsonResponse::getErrorResponse('Failure uploading file.');
    }

    /**
     * Helper function to get and validate gradeable has a configuration which notebook builder is allowed to edit.
     *
     * @param string $g_id
     * @return Gradeable|string If a gradeable was found for the passed gradeable id, then that gradeable will be
     *                          returned.  Otherwise an error string is returned.  Calling code should check the return
     *                          type.
     */
    private function getValidGradeable(string $g_id) {
        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($g_id);
        }
        catch (\Exception $exception) {
            return 'Invalid Gradeable ID.';
        }

        if (!$gradeable->isUsingUploadedConfig()) {
            return 'Unable to make configuration changes outside of the current course and semester config_upload folder.';
        }

        return $gradeable;
    }

    /**
     * Helper function to fix the group permission on newly uploaded files.
     *
     * @param string $path Absolute path to file.
     * @return bool True if operation was successful, false otherwise.
     */
    private function updateGroupPermission(string $path): bool {
        $group = $this->core->getConfig()->getCourse() . '_tas_www';
        return chgrp($path, $group);
    }

    /**
     * Helper function which fires off a rebuild for the given gradeable.
     *
     * @param Gradeable $gradeable
     */
    private function rebuildGradeable(Gradeable $gradeable): void {
        $admin_gradeable_controller = new AdminGradeableController($this->core);
        $admin_gradeable_controller->enqueueBuild($gradeable);
    }

    /**
     * Generate an associative array of files in the given directory.  The file contents are encoded into a base64
     * data url.  The result array can be easily json encoded and has the form:
     *
     * [<file_name> => <data_url>]
     *
     * @param string $directory Absolute path to the directory.
     * @return array
     */
    private function getFiles(string $directory): array {
        $result = [];
        $paths = FileUtils::getAllFilesTrimSearchPath($directory, 0);

        foreach ($paths as $path) {
            $parts = explode('/', $path);
            $result[$parts[count($parts) - 1]] = FileUtils::readAsDataURL($path);
        }

        return $result;
    }
}

<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\response\RedirectResponse;
use app\libraries\routers\AccessControl;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class WrapperController
 * @package app\controllers\admin
 * @AccessControl(permission="admin.wrapper")
 */
class WrapperController extends AbstractController {

    const WRAPPER_FILES = [
        'top_bar.html',
        'left_sidebar.html',
        'right_sidebar.html',
        'bottom_bar.html',
        'override.css',
        'sidebar.json'
    ];

    /**
     * @Route("/{_semester}/{_course}/theme")
     * @return MultiResponse
     */
    public function uploadWrapperPage() {
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                ['admin', 'Wrapper'],
                'displayPage',
                $this->core->getConfig()->getWrapperFiles()
            )
        );
    }

    /**
     * @Route("/{_semester}/{_course}/theme/upload", methods={"POST"})
     * @return MultiResponse
     */
    public function processUploadHTML() {
        $filename = $_POST['location'];
        $location = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'site', $filename);

        if (!$this->core->getAccess()->canI("path.write.site", ["dir" => "site", "path" => $location])) {
            return MultiResponse::webOnlyResponse(
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }

        if (empty($_FILES) || !isset($_FILES['wrapper_upload'])) {
            $this->core->addErrorMessage("Upload failed: No file to upload");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['theme']))
            );
        }
        $upload = $_FILES['wrapper_upload'];

        if (!isset($_POST['location']) || !in_array($_POST['location'], WrapperController::WRAPPER_FILES)) {
            $this->core->addErrorMessage("Upload failed: Invalid location");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['theme']))
            );
        }

        if (!@copy($upload['tmp_name'], $location)) {
            $this->core->addErrorMessage("Upload failed: Could not copy file");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['theme']))
            );
        }

        $this->core->addSuccessMessage("Uploaded " . $upload['name'] . " as " . $filename);
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['theme']))
        );
    }

    /**
     * @Route("/{_semester}/{_course}/theme/delete", methods={"POST"})
     * @return MultiResponse
     */
    public function deleteUploadedHTML() {
        $filename = $_POST['location'];
        $location = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'site', $filename);

        if (!$this->core->getAccess()->canI("path.write.site", ["dir" => "site", "path" => $location])) {
            return MultiResponse::webOnlyResponse(
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }

        if (!isset($_POST['location']) || !in_array($_POST['location'], WrapperController::WRAPPER_FILES)) {
            $this->core->addErrorMessage("Delete failed: Invalid filename");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['theme']))
            );
        }
        if (!@unlink($location)) {
            $this->core->addErrorMessage("Deletion failed: Could not unlink file");
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['theme']))
            );
        }

        $this->core->addSuccessMessage("Deleted " . $filename);
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['theme']))
        );
    }
}

<?php

declare(strict_types=1);

namespace app\controllers\banner;

use app\controllers\AbstractController;
use app\controllers\GlobalController;
use app\libraries\response\JsonResponse;
use app\libraries\response\WebResponse;
use app\views\banner\BannerView;
use Symfony\Component\Routing\Annotation\Route;
use app\entities\banner\BannerImage;
use app\libraries\DateUtils;
use app\libraries\FileUtils;
use app\libraries\routers\AccessControl;

/**
 * @AccessControl(level="FACULTY")
 */
class BannerController extends AbstractController {
    /**
     *
     * @Route("/banner")
     *
     * @return WebResponse
     * @see GlobalController::prep_user_sidebar
     * @see BannerView::showEventBanners
     */
    public function viewCommunityEvents(): WebResponse {
        $communityEventBanners = $this->core->getSubmittyEntityManager()->getRepository(BannerImage::class)->findAll();
        return new WebResponse(BannerView::class, 'showEventBanners', $communityEventBanners);
    }



    /**
     * @Route("/banner/upload", methods={"POST"})
     */
    public function ajaxUploadEventFiles(): JsonResponse {
        $upload_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "community_events");

        if (isset($_POST['release_time'])) {
            $release_date = DateUtils::parseDateTime($_POST['release_time'], $this->core->getDateTimeNow()->getTimezone());
        }
        else {
            return JsonResponse::getErrorResponse("No release date.");
        }


        if (isset($_POST['close_time'])) {
            $close_date = DateUtils::parseDateTime($_POST['close_time'], $this->core->getDateTimeNow()->getTimezone());
        }
        else {
            return JsonResponse::getErrorResponse("No release date.");
        }
        if (!isset($_FILES["files1"]) || $_FILES["files1"] === []) {
            return JsonResponse::getErrorResponse("No files were submitted.");
        }

        $uploaded_files = $_FILES["files1"];
        $count_item = count($uploaded_files["name"]);
        $extra_name = $_POST['extra_name'];
        $link_name = $_POST['link_name'];

        if ($extra_name === "") {
            if ($count_item !== 1) {
                return JsonResponse::getErrorResponse("You can only have one banner submitted.");
            }
        }
        else {
            if ($count_item > 2) {
                return JsonResponse::getErrorResponse("Can't have more than two banners submitted.");
            }
        }


        $specificPath = $close_date->format("Y");
        $actual_banner_name = "";

        for ($j = 0; $j < $count_item; $j++) {
            if ($uploaded_files['name'][$j] != $extra_name) {
                $actual_banner_name = $uploaded_files['name'][$j];
            }
        }

        $currentDate = new \DateTime();
        $folder_made_name = $actual_banner_name . "Folder" . $currentDate->format('Y-m-d_H-i-s');


        $full_path = FileUtils::joinPaths($upload_path, $specificPath, $folder_made_name);
        $full_path1= FileUtils::joinPaths($full_path, $actual_banner_name);
        

        $full_path1 = $this->core->getAccess()->resolveDirPath("community_events", $full_path1);
        

        $full_path2 = "empty";
        if ($extra_name !== "") {
            $full_path2= FileUtils::joinPaths($full_path, $extra_name);
            $full_path2 = $this->core->getAccess()->resolveDirPath("community_events", $full_path2);
        }

        if ($full_path1 === false || $full_path2 === false) {
            return JsonResponse::getErrorResponse("Path is bad.");
        }


        if (!is_dir($full_path)) {
            // Create a new folder for the current month
            if (!mkdir($full_path, 0755, true)) {
                return JsonResponse::getErrorResponse("Failed to create a new folder for the current year.");
            }
        }
        else {
            return JsonResponse::getErrorResponse("Please wait a few minutes before uploading, you are matching another uploaded file");
        }

        for ($j = 0; $j < $count_item; $j++) {
            $all_match = false;
            if ($uploaded_files['name'][$j] == $extra_name) {
                $all_match = true;
            }


            if (is_uploaded_file($uploaded_files["tmp_name"][$j])) {
                $dst = FileUtils::joinPaths($full_path, $uploaded_files["name"][$j]);

                if (!$all_match) {
                    [$width, $height] = getimagesize($uploaded_files["tmp_name"][$j]);


                    if ($width > 800 || $height > 100) {
                        return JsonResponse::getErrorResponse("File dimensions must be no more than 800x100 pixels.");
                    }
                    if ($width < 200 || $height < 10) {
                        return JsonResponse::getErrorResponse("File dimensions must be no less than 200x10 pixels.");
                    }
                }

                if (strlen($dst) > 255) {
                    return JsonResponse::getErrorResponse("Path cannot have a string length of more than 255 chars.");
                }

                if (!@copy($uploaded_files["tmp_name"][$j], $dst)) {
                    return JsonResponse::getErrorResponse("Failed to copy uploaded file '{$uploaded_files['name'][$j]}' to current location.");
                }
            }
            else {
                return JsonResponse::getErrorResponse("The temporary file '{$uploaded_files['name'][$j]}' was not properly uploaded.");
            }

            if (!@unlink($uploaded_files["tmp_name"][$j])) {
                return JsonResponse::getErrorResponse("Failed to delete the uploaded file '{$uploaded_files['name'][$j]}' from temporary storage.");
            }


            if ($all_match) {
                continue;
            }
            $community_event_image = new BannerImage(
                $specificPath,
                $actual_banner_name,
                $extra_name,
                $link_name,
                $release_date,
                $close_date,
                $folder_made_name
            );
            $this->core->getSubmittyEntityManager()->persist($community_event_image);
            $this->core->getSubmittyEntityManager()->flush();
        }

        return JsonResponse::getSuccessResponse("Successfully uploaded!");
    }

    /**
     * @Route("/banner/delete", methods={"POST"})
     */
    public function ajaxDeleteEventFiles(): JsonResponse {

        $entity_manager = $this->core->getSubmittyEntityManager();

        $event_repository = $entity_manager->getRepository(BannerImage::class);

        $event_items = $event_repository->findBy(['name' => $_POST['name'] ]);
        if (count($event_items) === 0) {
            $error_message = "Banner item with name '" . $_POST['name'] . "' not found in the database.";
            return JsonResponse::getErrorResponse($error_message);
        }

        $event_item = $event_items[0];
        $entity_manager->remove($event_item);
        $entity_manager->flush();

        $upload_path =  FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "community_events");


        $folder_name = $_POST['path'];
        $event_name = $_POST['name'];

        $full_path = FileUtils::joinPaths($upload_path, $folder_name, $event_item->getFolderName(), $event_name);

        $full_path = $this->core->getAccess()->resolveDirPath("community_events", $full_path);

        if ($full_path === false) {
            return JsonResponse::getErrorResponse("Path is bad.");
        }
        if (is_file($full_path)) {
            $entity_manager->remove($event_item);
            $entity_manager->flush();
            // Check if the file exists before attempting to delete it
            if (!unlink($full_path)) {
                return JsonResponse::getErrorResponse("Failed to delete the file.");
            }
        }
        else {
            return JsonResponse::getErrorResponse("File not found.");
        }


        return JsonResponse::getSuccessResponse("Successfully uploaded!");
    }
}

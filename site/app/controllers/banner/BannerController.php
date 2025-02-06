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
 * @AccessControl(level="SUPERUSER")
 */
class BannerController extends AbstractController {
    /**
     *
     *
     * @return WebResponse
     * @see GlobalController::prep_user_sidebar
     * @see BannerView::showEventBanners
     */
    #[Route("/community_events")]
    public function viewCommunityEvents(): WebResponse {
        $communityEventBanners = $this->core->getSubmittyEntityManager()->getRepository(BannerImage::class)->findAll();
        return new WebResponse(BannerView::class, 'showEventBanners', $communityEventBanners);
    }

    #[Route("/community_event/upload_svg", methods: ["POST"])]
    public function ajaxUploadSvg(): JsonResponse {
        if (count($_FILES["files2"]["name"]) !== 1) {
            return JsonResponse::getErrorResponse("You can only upload one svg file");
        }

        $upload_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "community_events");
        if (!isset($_FILES["files2"]) || $_FILES["files2"]["name"] === '') {
            return JsonResponse::getErrorResponse("No files were submitted.");
        }

        foreach ($_FILES["files2"]["name"] as $index => $file_name) {
            if (is_uploaded_file($_FILES["files2"]["tmp_name"][$index])) {
                $destination_path = FileUtils::joinPaths($upload_path, $file_name);

                if ($file_name !== "moorthy_chat_gif.gif") {
                    return JsonResponse::getErrorResponse("The name has to be: moorthy_chat_gif.gif");
                }

                if (strlen($destination_path) > 255) {
                    return JsonResponse::getErrorResponse("File path is too long.");
                }

                if (!is_dir($upload_path)) {
                    if (!mkdir($upload_path, 0755, true)) {
                        return JsonResponse::getErrorResponse("Failed to create the directory '{$upload_path}'.");
                    }
                }

                if (!@copy($_FILES["files2"]["tmp_name"][$index], $destination_path)) {
                    $error = error_get_last();
                    return JsonResponse::getErrorResponse(
                        "Failed to upload file '{$file_name}'. Error: {$error['message']} in {$error['file']} on line {$error['line']}"
                    );
                }

                if (!@unlink($_FILES["files2"]["tmp_name"][$index])) {
                    return JsonResponse::getErrorResponse("Failed to delete the temporary file '{$file_name}'.");
                }
            }
            else {
                return JsonResponse::getErrorResponse("The file '{$file_name}' was not properly uploaded.");
            }
        }
        return JsonResponse::getSuccessResponse("Files uploaded successfully.");
    }

    #[Route("/community_event/upload", methods: ["POST"])]
    public function ajaxUploadEventFiles(): JsonResponse {


        $upload_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "community_events");

        if (isset($_POST['release_time'])) {
            $release_date = DateUtils::parseDateTime($_POST['release_time'], $this->core->getDateTimeNow()->getTimezone());
        }
        else {
            return JsonResponse::getErrorResponse("Please make sure you have a release date for the banner for when the banner will start being displayed.");
        }


        if (isset($_POST['close_time'])) {
            $close_date = DateUtils::parseDateTime($_POST['close_time'], $this->core->getDateTimeNow()->getTimezone());
        }
        else {
            return JsonResponse::getErrorResponse("Please make sure you have an end date for the banner for when the banner will stop being displayed.");
        }
        if (!isset($_FILES["files1"]) || $_FILES["files1"] === []) {
            return JsonResponse::getErrorResponse("No files were submitted.");
        }

        $uploaded_files = $_FILES["files1"];


        $count_item = count($uploaded_files["name"]);
        $bigger_banner_name = $_POST['extra_name'];
        $link_name = $_POST['link_name'];

        if ($bigger_banner_name === "" && $count_item !== 1) {
            return JsonResponse::getErrorResponse("You can only have one banner submitted.");
        }
        elseif ($count_item > 2) {
            return JsonResponse::getErrorResponse("Can't have more than two banners submitted.");
        }


        $specificPath = $close_date->format("Y");
        $actual_banner_name = "";

        //since the user could upload 2 banners (one being the bigger banner), we want to find what the actual banner name is so we loop
        //through the banners to find the right one (we have a loop so that in case if later we want to expand the number of allowed banners)
        foreach ($uploaded_files['name'] as $uploaded_file_name) {
            if ($uploaded_file_name !== $bigger_banner_name) {
                $actual_banner_name = $uploaded_file_name;
                break; // Exit the loop once a valid name is found
            }
        }

        $currentDate = new \DateTime();
        $folder_made_name = $actual_banner_name . "_" . $currentDate->format('Y-m-d_H-i-s');


        $full_path = FileUtils::joinPaths($upload_path, $specificPath, $folder_made_name);
        $full_path1 = FileUtils::joinPaths($full_path, $actual_banner_name);
        $full_path1 = $this->core->getAccess()->resolveDirPath("community_events", $full_path1);

        $full_path2 = "empty";
        if ($bigger_banner_name !== "") {
            $full_path2 = FileUtils::joinPaths($full_path, $bigger_banner_name);
            $full_path2 = $this->core->getAccess()->resolveDirPath("community_events", $full_path2);
        }

        if ($full_path1 === false || $full_path2 === false) {
            return JsonResponse::getErrorResponse("Path is invalid.");
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
            if ($uploaded_files['name'][$j] === $bigger_banner_name) {
                $all_match = true;
            }

            if (is_uploaded_file($uploaded_files["tmp_name"][$j])) {
                $dst = FileUtils::joinPaths($full_path, $uploaded_files["name"][$j]);

                if (!$all_match) {
                    [$width, $height] = getimagesize($uploaded_files["tmp_name"][$j]);
                    if ($width !== 500 || $height !== 100) {
                        return JsonResponse::getErrorResponse("File dimensions must be no more than 500x100 pixels.");
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
                $bigger_banner_name,
                $link_name,
                $release_date,
                $close_date,
                $folder_made_name
            );
            $this->core->getSubmittyEntityManager()->persist($community_event_image);
        }
        $this->core->getSubmittyEntityManager()->flush();
        return JsonResponse::getSuccessResponse("Successfully uploaded!");
    }

    /**
     */
    #[Route("/community_events/delete", methods: ["POST"])]
    public function ajaxDeleteEventFiles(): JsonResponse {
        $entity_manager = $this->core->getSubmittyEntityManager();
        $event_repository = $entity_manager->getRepository(BannerImage::class);
        $event_item = $event_repository->findOneBy(['id' => $_POST['id']]);

        if ($event_item === null) {
            $error_message = "Banner item with name '" . $_POST['name'] . "' not found in the database.";
            return JsonResponse::getErrorResponse($error_message);
        }
        $upload_path =  FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "community_events");
        $releaseDateInt = $event_item->getClosingDate();
        $folder_name = $releaseDateInt->format('Y');
        $event_name = $event_item->getName();
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
        return JsonResponse::getSuccessResponse("Successfully deleted!");
    }
}

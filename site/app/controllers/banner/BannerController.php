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

class BannerController extends AbstractController {
    /**
     *
     * @Route("/banner")
     *
     * @return WebResponse
     * @see GlobalController::prep_user_sidebar
     * @see BannerView::showBanner
     */
    public function viewBanner(): WebResponse {
        $bannerImages = $this->core->getSubmittyEntityManager()->getRepository(BannerImage::class) ->findall();
        return new WebResponse(BannerView::class, 'showBanner', $bannerImages);
        //EVEN WHEN I REPLACE BannerView::class with 'app\views\banner\BannerView', webresponse still treats the parameter as 'app\\controllers\\Banner\\BannerView'
    }



    /**
     * @Route("/banner/upload", methods={"POST"})
     */
    public function ajaxUploadBannerFiles(): JsonResponse {
        $upload_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "banner_images");
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
        if (!isset($_FILES["files1"]) || empty($_FILES["files1"])) {
            return JsonResponse::getErrorResponse("No files were submitted.");
        }

        $uploaded_files = $_FILES["files1"];
        $count_item = count($uploaded_files["name"]);
        $extra_name = $_POST['extra_name'];

        if ($extra_name == "..") {
            return JsonResponse::getErrorResponse("invalid name");
        }

        if (empty($extra_name) || strpos($extra_name, 'http') !== false) {
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
            if ($uploaded_files['name'][$j] == "..") {
                return JsonResponse::getErrorResponse("invalid name");
            }

            if ($uploaded_files['name'][$j] != $extra_name) {
                $actual_banner_name = $uploaded_files['name'][$j];
            }
        }

        $currentDate = new \DateTime();
        $folder_made_name = $actual_banner_name . "Folder" . $currentDate->format('Y-m-d_H-i-s');

        $full_path = FileUtils::joinPaths($upload_path, $specificPath, $folder_made_name);
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
            if ($uploaded_files['name'][$j] == "..") {
                return JsonResponse::getErrorResponse("invalid name");
            }
            $all_match = false;
            if ($uploaded_files['name'][$j] == $extra_name) {
                $all_match = true;
            }


            if (is_uploaded_file($uploaded_files["tmp_name"][$j])) {
                $dst = FileUtils::joinPaths($full_path, $uploaded_files["name"][$j]);

                if (!$all_match) {
                    [$width, $height] = getimagesize($uploaded_files["tmp_name"][$j]);


                    if ($width !== 800 || $height > 70) {
                        return JsonResponse::getErrorResponse("File dimensions must be 800x70 pixels.");
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
            $banner_image = new BannerImage(
                $specificPath,
                $actual_banner_name,
                $extra_name,
                $release_date,
                $close_date, 
                $folder_made_name
            );
            $this->core->getSubmittyEntityManager()->persist($banner_image);
            $this->core->getSubmittyEntityManager()->flush();
        }

        return JsonResponse::getSuccessResponse("Successfully uploaded!");
    }

    /**
     * @Route("/banner/delete", methods={"POST"})
     */
    public function ajaxDeleteBannerFiles(): JsonResponse {

        $entity_manager = $this->core->getSubmittyEntityManager();

        $banner_repository = $entity_manager->getRepository(BannerImage::class);

        $banner_items = $banner_repository->findBy(['name' => $_POST['name'] ]);
        if (empty($banner_items)) {
            $error_message = "Banner item with name '" . $_POST['name'] . "' not found in the database.";
            return JsonResponse::getErrorResponse($error_message);
        }

        $banner_item = $banner_items[0];
        $entity_manager->remove($banner_item);
        $entity_manager->flush();

        $full_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "banner_images");

        $folder_name = $_POST['path'];
        $banner_name = $_POST['name'];

        $full_path = FileUtils::joinPaths($full_path, $folder_name, $banner_item->getFolderName(), $banner_name);


        if (is_file($full_path)) {
            // Check if the file exists before attempting to delete it
            if (!unlink($full_path)) {
                return JsonResponse::getErrorResponse("Failed to delete the file.");
            }
            // Maybe implemement later to get rid of the folder, i dunno?
            // $folder_path = FileUtils::joinPaths($full_path, $banner_item->getFolderName());
            // if (is_dir($folder_path)) {
            //     FileUtils::deleteDir($folder_path); 
            // }
        }
        else {
            return JsonResponse::getErrorResponse("File not found.");
        }


        return JsonResponse::getSuccessResponse("Successfully uploaded!");
    }
}

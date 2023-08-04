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
        return new WebResponse(BannerView::class, 'showBanner');
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

        if ($count_item > 2) {
            return JsonResponse::getErrorResponse("Can't have more than two banners submitted.");
        }

        $specificPath = $close_date->format("Y");
        $full_path = FileUtils::joinPaths($upload_path, $specificPath);



        if (!is_dir($full_path)) {
            // Create a new folder for the current month
            if (!mkdir($full_path, 0755, true)) {
                return JsonResponse::getErrorResponse("Failed to create a new folder for the current year.");
            }
        }
        $extra_name = $_POST['extra_name'];
        for ($j = 0; $j < $count_item; $j++) {
            // for some reason why I try to simply use a condition to compare two strings, I always get false?!? So I have to loop through each character now
            $all_match = true;
            for ($i = 0; $i < strlen($uploaded_files['name'][$j]); $i++) {
                if (strlen($uploaded_files['name'][$j]) != strlen($extra_name)) {
                    $all_match = false;
                    break;
                }

                if ($uploaded_files['name'][$j][$i] != $extra_name[$i]) {
                    $all_match = false;
                    break;
                }
            }


            if (is_uploaded_file($uploaded_files["tmp_name"][$j])) {
                $dst = FileUtils::joinPaths($full_path, $uploaded_files["name"][$j]);

                if (!$all_match) {
                    [$width, $height] = getimagesize($uploaded_files["tmp_name"][$j]);


                    if ($width !== 800 || $height !== 70) {
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
                $uploaded_files["name"][$j],
                $extra_name,
                $release_date,
                $close_date
            );
            $this->core->getBannerEntityManager()->persist($banner_image);
            $this->core->getBannerEntityManager()->flush();
        }

        return JsonResponse::getSuccessResponse("Successfully uploaded!");
    }

    /**
     * @Route("/banner/delete", methods={"POST"})
     */
    public function ajaxDeleteBannerFiles(): JsonResponse {

        $entity_manager = $this->core->getBannerEntityManager();

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

        $full_path = FileUtils::joinPaths($full_path, $folder_name, $banner_name);


        if (is_file($full_path)) {
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

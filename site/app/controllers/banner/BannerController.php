<?php

declare(strict_types=1);

namespace app\controllers\banner;

use app\controllers\AbstractController;
use app\controllers\GlobalController;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\ResponseInterface;
use app\models\gradeable\GradeableUtils;
use app\views\banner\BannerView;
use Symfony\Component\Routing\Annotation\Route;

use app\entities\course\BannerImage;
use app\repositories\course\BannerImageRepository;


use app\controllers\MiscController;
use app\libraries\Core;
use app\libraries\CourseMaterialsUtils;
use app\libraries\DateUtils;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\views\ErrorView;
use app\views\MiscView;

use app\libraries\routers\AccessControl;

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

        if (!isset($_FILES["files1"]) || empty($_FILES["files1"])) {
            return JsonResponse::getErrorResponse("No files were submitted.");
        }

        $uploaded_files = $_FILES["files1"];
        $count_item = count($uploaded_files["name"]);

        for ($j = 0; $j < $count_item; $j++) {
            if (is_uploaded_file($uploaded_files["tmp_name"][$j])) {
                $dst = FileUtils::joinPaths($upload_path, $uploaded_files["name"][$j]);

                if (strlen($dst) > 255) {
                    return JsonResponse::getErrorResponse("Path cannot have a string length of more than 255 chars.");
                }

                if (!@copy($uploaded_files["tmp_name"][$j], $dst)) {
                    return JsonResponse::getErrorResponse("Failed to copy uploaded file '{$uploaded_files['name'][$j]}' to current location.");
                }
            } else {
                return JsonResponse::getErrorResponse("The temporary file '{$uploaded_files['name'][$j]}' was not properly uploaded.");
            }

            if (!@unlink($uploaded_files["tmp_name"][$j])) {
                return JsonResponse::getErrorResponse("Failed to delete the uploaded file '{$uploaded_files['name'][$j]}' from temporary storage.");
            }


            $banner_image = new BannerImage(
                $uploaded_files["name"][$j],
                "howdy"
            );
            $this->core->getBannerEntityManager()->persist($banner_image);
            
        
            $this->core->getBannerEntityManager()->flush();


        }

        return JsonResponse::getSuccessResponse("Successfully uploaded!");
    }



}







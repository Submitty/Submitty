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
}
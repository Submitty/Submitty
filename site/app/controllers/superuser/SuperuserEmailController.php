<?php

namespace app\controllers\superuser;

use app\libraries\Core;
use app\libraries\response\JsonResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\MultiResponse;
use app\controllers\AbstractController;
use app\libraries\routers\AccessControl;
use app\libraries\User;
use app\views\superuser\SuperuserEmailView;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @AccessControl(level="SUPERUSER")
 */
class SuperuserEmailController extends AbstractController {

    public function __construct(Core $core){
        parent::__construct($core);
    }

    /**
     * @Route("/superuser/email")
     */
    public function showEmailPage(){
        return MultiResponse::webOnlyResponse(
            new WebResponse(SuperuserEmailView::class, 'showEmailPage')
        );
    }
}

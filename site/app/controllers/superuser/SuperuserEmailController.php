<?php

namespace app\controllers\superuser;

use app\libraries\Core;
use app\libraries\response\WebResponse;
use app\libraries\response\MultiResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
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
     * @return MultiResponse::webOnlyResponse
     */
    public function showEmailPage(){
        return MultiResponse::webOnlyResponse(
            new WebResponse(SuperuserEmailView::class, 'showEmailPage')
        );
    }
    /**
     * @Route("/superuser/email/send", methods={"POST"})
     */
    public function sendEmail(){
        if (!isset($_POST['emailContent']) || $_POST['emailContent'] == ''){
            return JsonResponse::getFailResponse("Email content is empty.");
        }
        else {
            $notificationFactory = $this->core->getNotificationFactory();
            # getRecipients
            $activeUserIds = $this->core->getQueries()->getActiveUserIds($_POST['semester']);
            # Set up email here
            
            return JsonResponse::getSuccessResponse([
                "message" => "Email Sent!",
                "data" => $activeUserIds
            ]);
        }
    }
}

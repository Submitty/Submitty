<?php

namespace app\controllers\superuser;

use app\libraries\Core;
use app\libraries\response\WebResponse;
use app\libraries\response\MultiResponse;
use app\libraries\response\JsonResponse;
use app\controllers\AbstractController;
use app\libraries\routers\AccessControl;
use app\libraries\User;
use app\views\superuser\SuperuserEmailView;
use Symfony\Component\Routing\Annotation\Route;
use app\models\SuperuserEmail;

/**
 * @AccessControl(level="SUPERUSER")
 */
class SuperuserEmailController extends AbstractController {

    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @Route("/superuser/email")
     * @return MultiResponse
     */
    public function showEmailPage(): MultiResponse {
        return MultiResponse::webOnlyResponse(
            new WebResponse(SuperuserEmailView::class, 'showEmailPage')
        );
    }
    /**
     * @Route("/superuser/email/send", methods={"POST"})
     * @return JsonResponse
     */
    public function sendEmail(): JsonResponse {
        if (!isset($_POST['emailContent']) || $_POST['emailContent'] == '') {
            return JsonResponse::getFailResponse("Email content is empty.");
        }
        elseif (!isset($_POST['emailSubject']) || $_POST['emailSubject'] == '') {
            return JsonResponse::getFailResponse("Email subject is empty.");
        }
        else {
            // Because AJAX stringifies everthing
            $emailInstructor = $_POST['options'][0];
            $emailFullAccess = $_POST['options'][1];
            $emailLimitedAccess = $_POST['options'][2];
            $emailStudent = $_POST['options'][3];
            $emailFaculty = $_POST['options'][4];
            $emailToSecondary = $_POST['options'][5];
            # getRecipients
            $activeUserIds = $this->core->getQueries()->getActiveUserIds(
                $emailInstructor,
                $emailFullAccess,
                $emailLimitedAccess,
                $emailStudent,
                $emailFaculty
            );
            # Set up email here
            $notificationFactory = $this->core->getNotificationFactory();
            $emails = [];
            foreach ($activeUserIds as $userId) {
                $details = ['body' => $_POST['emailContent'], 'subject' => $_POST['emailSubject'], 'to_user_id' => $userId];
                $emails[] = new SuperuserEmail($this->core, $details);
            }
            $notificationFactory->sendEmails($emails, $emailToSecondary);
            return JsonResponse::getSuccessResponse([
                "message" => "Email queued to be sent!",
                "data" => json_encode($activeUserIds)
            ]);
        }
    }
}

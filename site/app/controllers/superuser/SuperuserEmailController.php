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


    /**
     * @Route("/superuser/email")
     * @return MultiResponse
     */
    public function showEmailPage(): MultiResponse {
        $num_faculty = count($this->core->getQueries()->getActiveUserIds(false, false, false, false, true));
        $num_instructor = count($this->core->getQueries()->getActiveUserIds(true, false, false, false, false));
        $num_full_access = count($this->core->getQueries()->getActiveUserIds(true, true, false, false, false)) - $num_instructor;
        $num_limited_access = count($this->core->getQueries()->getActiveUserIds(true, true, true, false, false)) - $num_instructor - $num_full_access;
        $num_student = count($this->core->getQueries()->getActiveUserIds(true, true, true, true, false)) - $num_instructor - $num_full_access - $num_limited_access;
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                SuperuserEmailView::class,
                'showEmailPage',
                $num_faculty,
                $num_instructor,
                $num_full_access,
                $num_limited_access,
                $num_student
            )
        );
    }
    /**
     * @Route("/superuser/email/send", methods={"POST"})
     * @return JsonResponse
     */
    public function sendEmail(): JsonResponse {
        if (!isset($_POST['email_content']) || $_POST['email_content'] == '') {
            return JsonResponse::getFailResponse("Email content is empty.");
        }
        elseif (!isset($_POST['email_subject']) || $_POST['email_subject'] == '') {
            return JsonResponse::getFailResponse("Email subject is empty.");
        }
        else {
            // Because AJAX stringifies everthing
            $email_instructor =  isset($_POST['email_instructor']) && $_POST['email_instructor'] == "true";
            $email_full_access = isset($_POST['email_full_access']) && $_POST['email_full_access'] == "true";
            $emailLimitedAccess = isset($_POST['email_limited_access']) && $_POST['email_limited_access'] == "true";
            $email_student = isset($_POST['email_student']) && $_POST['email_student'] == "true";
            $email_faculty = isset($_POST['email_faculty']) && $_POST['email_faculty'] == "true";
            $email_to_secondary =  isset($_POST['email_to_secondary']) && $_POST['email_to_secondary'] == "true";
            # getRecipients
            $active_user_ids = $this->core->getQueries()->getActiveUserIds(
                $email_instructor,
                $email_full_access,
                $emailLimitedAccess,
                $email_student,
                $email_faculty
            );
            # Set up email here
            $notification_factory = $this->core->getNotificationFactory();
            $emails = [];
            foreach ($active_user_ids as $user_id) {
                $details = ['body' => $_POST['email_content'], 'subject' => $_POST['email_subject'], 'to_user_id' => $user_id];
                $emails[] = new SuperuserEmail($this->core, $details);
            }
            $count = $notification_factory->sendEmails($emails, $email_to_secondary, true);
            return JsonResponse::getSuccessResponse([
                "message" => $count . " emails queued to be sent!",
                "data" => json_encode($active_user_ids)
            ]);
        }
    }
}

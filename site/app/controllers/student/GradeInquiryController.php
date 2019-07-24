<?php

namespace app\controllers\student;

use app\controllers\AbstractController;
use app\models\Notification;
use app\models\Email;
use app\libraries\response\Response;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;


class GradeInquiryController extends AbstractController {
    public function run() {
        return null;
    }

    /**
     * @param $gradeable_id
     * @Route("/{_semester}/{_course}/gradeable/{gradeable_id}/grade_inquiry/new", methods={"POST"})
     * @return Response|null null is for tryGetGradeable and tryGetGradedGradeable
     */
    public function requestGradeInquiry($gradeable_id) {
        $content = $_POST['replyTextArea'] ?? '';
        $submitter_id = $_POST['submitter_id'] ?? '';

        $user = $this->core->getUser();

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return null;
        }

        if(!$gradeable->isRegradeOpen()) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse('Grade inquiries are not enabled for this gradeable')
            );
        }

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return null;
        }

        $can_inquiry = $this->core->getAccess()->canI("grading.electronic.grade_inquiry", ['graded_gradeable' => $graded_gradeable]);
        if (!$graded_gradeable->getSubmitter()->hasUser($user) && !$can_inquiry) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse('Insufficient permissions to request regrade')
            );
        }

        try {
            $this->core->getQueries()->insertNewRegradeRequest($graded_gradeable, $user, $content);
            $this->notifyGradeInquiryEvent($graded_gradeable, $gradeable_id, $content, 'new');
            return Response::JsonOnlyResponse(
                JsonResponse::getSuccessResponse()
            );
        } catch (\InvalidArgumentException $e) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse($e->getMessage())
            );
        } catch (\Exception $e) {
            return Response::JsonOnlyResponse(
                JsonResponse::getErrorResponse($e->getMessage())
            );
        }
    }

    /**
     * @param $gradeable_id
     * @Route("/{_semester}/{_course}/gradeable/{gradeable_id}/grade_inquiry/post", methods={"POST"})
     * @return Response|null null is for tryGetGradeable and tryGetGradedGradeable
     */
    public function makeGradeInquiryPost($gradeable_id) {
        $content = str_replace("\r", "", $_POST['replyTextArea']);
        $submitter_id = $_POST['submitter_id'] ?? '';

        $user = $this->core->getUser();

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return null;
        }

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return null;
        }

        if (!$graded_gradeable->hasRegradeRequest()) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse('Submitter has not made a grade inquiry')
            );
        }

        $can_inquiry = $this->core->getAccess()->canI("grading.electronic.grade_inquiry", ['graded_gradeable' => $graded_gradeable]);
        if (!$graded_gradeable->getSubmitter()->hasUser($user) && !$can_inquiry) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse('Insufficient permissions to make grade inquiry post')
            );
        }

        try {
            $this->core->getQueries()->insertNewRegradePost($graded_gradeable->getRegradeRequest()->getId(), $user->getId(), $content);
            $this->notifyGradeInquiryEvent($graded_gradeable, $gradeable_id, $content, 'reply');
            $this->core->getQueries()->saveRegradeRequest($graded_gradeable->getRegradeRequest());
            return Response::JsonOnlyResponse(
                JsonResponse::getSuccessResponse()
            );
        } catch (\InvalidArgumentException $e) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse($e->getMessage())
            );
        } catch (\Exception $e) {
            return Response::JsonOnlyResponse(
                JsonResponse::getErrorResponse($e->getMessage())
            );
        }
    }

    /**
     * @param $gradeable_id
     * @Route("/{_semester}/{_course}/gradeable/{gradeable_id}/grade_inquiry/toggle_status", methods={"POST"})
     * @return Response|null null is for tryGetGradeable and tryGetGradedGradeable
     */
    public function changeGradeInquiryStatus($gradeable_id) {
        $content = str_replace("\r", "", $_POST['replyTextArea']);
        $submitter_id = $_POST['submitter_id'] ?? '';

        $user = $this->core->getUser();

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return null;
        }

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $submitter_id);
        if ($graded_gradeable === false) {
            return null;
        }

        if (!$graded_gradeable->hasRegradeRequest()) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse('Submitter has not made a grade inquiry')
            );
        }

        $can_inquiry = $this->core->getAccess()->canI("grading.electronic.grade_inquiry", ['graded_gradeable' => $graded_gradeable]);
        if (!$graded_gradeable->getSubmitter()->hasUser($user) && !$can_inquiry) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse('Insufficient permissions to change grade inquiry status')
            );
        }

        // toggle status
        $status = $graded_gradeable->getRegradeRequest()->getStatus() === -1 ? 0 : -1;

        try {
            $graded_gradeable->getRegradeRequest()->setStatus($status);
            $this->core->getQueries()->saveRegradeRequest($graded_gradeable->getRegradeRequest());
            if ($content != "") {
                $this->core->getQueries()->insertNewRegradePost($graded_gradeable->getRegradeRequest()->getId(), $user->getId(), $content);
            }
            return Response::JsonOnlyResponse(
                JsonResponse::getSuccessResponse()
            );
        } catch (\InvalidArgumentException $e) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse($e->getMessage())
            );
        } catch (\Exception $e) {
            return Response::JsonOnlyResponse(
                JsonResponse::getErrorResponse($e->getMessage())
            );
        }
    }

    private function notifyGradeInquiryEvent($graded_gradeable, $gradeable_id, $content, $type){
        //TODO: send notification to grader per component
        if($graded_gradeable->hasTaGradingInfo()){
            $course = $this->core->getConfig()->getCourse();
            $ta_graded_gradeable = $graded_gradeable->getOrCreateTaGradedGradeable();
            $graders = $ta_graded_gradeable->getGraders();
            $submitter = $graded_gradeable->getSubmitter();
            $user_id = $this->core->getUser()->getId();

            if ($type == 'new') {
                // instructor/TA/Mentor submitted
                if ($this->core->getUser()->accessGrading()) {
                    $email_subject = "[Submitty $course] New Regrade Request";
                    $email_body = "A Instructor/TA/Mentor submitted a grade inquiry for gradeable $gradeable_id.\n$user_id writes:\n$content\n\nPlease visit Submitty to follow up on this request";
                    $n_content = "An Instructor/TA/Mentor has made a new Grade Inquiry for ".$gradeable_id;
                }
                // student submitted
                else {
                    $email_subject = "[Submitty $course] New Regrade Request";
                    $email_body = "A student has submitted a grade inquiry for gradeable $gradeable_id.\n$user_id writes:\n$content\n\nPlease visit Submitty to follow up on this request";
                    $n_content = "A student has submitted a new grade inquiry for ".$gradeable_id;
                }
            } else if ($type == 'reply') {
                if ($this->core->getUser()->accessGrading()) {
                    $email_subject = "[Submitty $course] New Regrade Request";
                    $email_body = "A Instructor/TA/Mentor made a post in a grade inquiry for gradeable $gradeable_id.\n$user_id writes:\n$content\n\nPlease visit Submitty to follow up on this request";
                    $n_content = "A instructor has replied to your Grade Inquiry for ".$gradeable_id;
                }
                // student submitted
                else {
                    $email_subject = "[Submitty $course] New Regrade Request";
                    $email_body = "A student has made a post in a grade inquiry for gradeable $gradeable_id.\n$user_id writes:\n$content\n\nPlease visit Submitty to follow up on this request";
                    $n_content = "New reply in Grade Inquiry for ".$gradeable_id;
                }

            }

            // make graders' notifications and emails
            $metadata = json_encode(['url' => $this->core->buildUrl(['component' => 'grading', 'page' => 'electronic', 'action' => 'grade', 'gradeable_id' => $gradeable_id, 'who_id' => $submitter->getId()])]);
            foreach ($graders as $grader) {
                if ($grader->accessFullGrading() && $grader->getId() != $user_id){
                    $details = ['component' => 'grading', 'metadata' => $metadata, 'content' => $n_content, 'body' => $email_body, 'subject' => $email_subject, 'sender_id' => $user_id, 'to_user_id' => $grader->getId()];
                    $notifications[] = Notification::createNotification($this->core, $details);
                    $emails[] = new Email($this->core,$details);
                }
            }

            // make students' notifications and emails
            $metadata = json_encode(['url' => $this->core->buildUrl(['component' => 'student', 'gradeable_id' => $gradeable_id])]);
            if($submitter->isTeam()){
                $submitting_team = $submitter->getTeam()->getMemberUsers();
                foreach($submitting_team as $submitting_user){
                    if($submitting_user->getId() != $user_id) {
                        $details = ['component' => 'student', 'metadata' => $metadata, 'content' => $n_content, 'body' => $email_body, 'subject' => $email_subject, 'sender_id' => $user_id, 'to_user_id' => $submitting_user->getId()];
                        $notifications[] = Notification::createNotification($this->core, $details);
                        $emails[] = new Email($this->core,$details);
                    }
                }
            } else {
                if ($submitter->getUser()->getId() != $user_id) {
                    $details = ['component' => 'student', 'metadata' => $metadata, 'content' => $n_content, 'body' => $email_body, 'subject' => $email_subject, 'sender_id' => $user_id, 'to_user_id' => $submitter->getId()];
                    $notifications[] = Notification::createNotification($this->core, $details);
                    $emails[] = new Email($this->core,$details);
                }
            }
            $this->core->getNotificationFactory()->sendNotifications($notifications);
            if ($this->core->getConfig()->isEmailEnabled()) {
                $this->core->getNotificationFactory()->sendEmails($emails);
            }
        }
    }

}
